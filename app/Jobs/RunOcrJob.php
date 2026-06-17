<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;

class RunOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 180;  // OCR can be slow for large PDFs

    public function __construct(private int $documentId) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            \Log::warning("RunOcrJob: document #{$this->documentId} not found.");
            return;
        }

        // Mark as processing
        $document->update(['ocr_status' => 'processing']);

        try {
            $ocrText = $this->runOcr($document);

            $document->update([
                'ocr_status' => 'done',
                'ocr_text'   => $ocrText,
                'status'     => $document->virus_scan_status === 'clean' ? 'ocr_done' : $document->status,
            ]);

        } catch (\Exception $e) {
            $document->update(['ocr_status' => 'failed']);
            \Log::error("RunOcrJob failed for document #{$this->documentId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function runOcr(Document $document): string
    {
        // Get a temporary URL to the file in R2
        $fileUrl = Storage::disk('r2')->temporaryUrl($document->storage_path, now()->addMinutes(10));

        // Option A: Use Karza or similar Indian doc-OCR API
        // Option B: Use AWS Textract
        // Option C: Use Google Document AI
        // Below is a generic HTTP-based implementation — swap endpoint/auth as needed

        $ocrApiUrl = config('services.ocr.base_url');
        $ocrApiKey = config('services.ocr.api_key');

        if (empty($ocrApiUrl)) {
            // Fallback: return empty string — OCR service not configured yet
            \Log::info("RunOcrJob: OCR service not configured. Skipping for document #{$this->documentId}.");
            return '';
        }

        $response = Http::withHeaders(['Authorization' => "Bearer {$ocrApiKey}"])
            ->timeout(120)
            ->post($ocrApiUrl . '/extract', [
                'file_url'  => $fileUrl,
                'mime_type' => $document->mime_type,
                'language'  => 'en',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OCR API returned {$response->status()}: " . $response->body());
        }

        $data = $response->json();

        // Most OCR APIs return extracted text in a 'text' or 'content' key
        return $data['text'] ?? $data['content'] ?? $data['extracted_text'] ?? '';
    }

    public function failed(\Throwable $e): void
    {
        Document::where('id', $this->documentId)->update(['ocr_status' => 'failed']);
        \Log::error("RunOcrJob permanently failed for document #{$this->documentId}: " . $e->getMessage());
    }
}