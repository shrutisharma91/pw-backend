<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;

class VirusScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private int $documentId) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            \Log::warning("VirusScanJob: document #{$this->documentId} not found.");
            return;
        }

        try {
            $scanResult = $this->scan($document);

            $isInfected = $scanResult['infected'] ?? false;

            $document->update([
                'virus_scan_status' => $isInfected ? 'infected' : 'clean',
                'status'            => $isInfected ? 'quarantined' : $document->status,
            ]);

            if ($isInfected) {
                \Log::critical("VirusScanJob: INFECTED document detected — doc #{$this->documentId}, threat: " . ($scanResult['threat'] ?? 'unknown'));

                // Alert Super Admins
                $this->alertAdmins($document, $scanResult['threat'] ?? 'unknown');
            } else {
                // If OCR hasn't run yet, dispatch it now that the file is clean
                if ($document->ocr_status === 'pending') {
                    dispatch(new RunOcrJob($this->documentId));
                }
            }

        } catch (\Exception $e) {
            // On scan failure — mark as pending so it can be retried
            $document->update(['virus_scan_status' => 'pending']);
            \Log::error("VirusScanJob failed for document #{$this->documentId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function scan(Document $document): array
    {
        $scanApiUrl = config('services.virus_scan.base_url');
        $scanApiKey = config('services.virus_scan.api_key');

        if (empty($scanApiUrl)) {
            // Virus scan not configured — mark clean and log warning
            \Log::warning("VirusScanJob: virus scan service not configured. Marking doc #{$this->documentId} as clean.");
            return ['infected' => false];
        }

        // Get a short-lived URL to the file
        $fileUrl = Storage::disk('r2')->temporaryUrl($document->storage_path, now()->addMinutes(5));

        // Generic virus scan API — works with ClamAV REST, VirusTotal, or similar
        $response = Http::withHeaders(['x-api-key' => $scanApiKey])
            ->timeout(90)
            ->post($scanApiUrl . '/scan', [
                'url'       => $fileUrl,
                'mime_type' => $document->mime_type,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Virus scan API returned {$response->status()}");
        }

        return $response->json();
    }

    private function alertAdmins(Document $document, string $threat): void
    {
        $superAdmins = \Illuminate\Support\Facades\DB::table('users')
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->pluck('email');

        foreach ($superAdmins as $email) {
            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notify(new \App\Notifications\InfectedDocumentAlertNotification($document, $threat));
        }
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("VirusScanJob permanently failed for document #{$this->documentId}: " . $e->getMessage());
    }
}