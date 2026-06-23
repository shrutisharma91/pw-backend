<?php

namespace App\Services;

use App\Jobs\RunOcrJob;
use App\Jobs\VirusScanJob;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    private const STORAGE_DISK = 'r2';

    public function upload(array $data, UploadedFile $file, int $uploadedBy): Document
    {
        $storagePath = $this->buildStoragePath($data['entity_type'], (int) $data['entity_id'], $file);

        return DB::transaction(function () use ($data, $file, $uploadedBy, $storagePath) {
            Storage::disk(self::STORAGE_DISK)->putFileAs(
                dirname($storagePath),
                $file,
                basename($storagePath)
            );

            try {
                $document = Document::create([
                    'title'             => $data['title'],
                    'description'       => $data['description'] ?? null,
                    'tags'              => $data['tags'] ?? null,
                    'document_type'     => $data['document_type'],
                    'entity_type'       => $data['entity_type'],
                    'entity_id'         => $data['entity_id'],
                    'original_filename' => $file->getClientOriginalName(),
                    'storage_path'      => $storagePath,
                    'file_size_bytes'   => $file->getSize(),
                    'mime_type'         => $file->getMimeType() ?? $file->getClientMimeType(),
                    'status'            => $data['status'] ?? 'pending_ocr',
                    'ocr_status'        => 'pending',
                    'virus_scan_status' => 'pending',
                    'version'           => 1,
                    'uploaded_by'       => $uploadedBy,
                ]);

                DocumentVersion::create([
                    'document_id'     => $document->id,
                    'version'         => 1,
                    'storage_path'    => $storagePath,
                    'file_size_bytes' => $file->getSize(),
                    'uploaded_by'     => $uploadedBy,
                ]);

                dispatch(new VirusScanJob($document->id));
                dispatch(new RunOcrJob($document->id));

                activity()->log("Document uploaded: doc#{$document->id} by admin#{$uploadedBy}");

                return $document->fresh();
            } catch (\Throwable $e) {
                Storage::disk(self::STORAGE_DISK)->delete($storagePath);

                throw $e;
            }
        });
    }

    private function buildStoragePath(string $entityType, int $entityId, UploadedFile $file): string
    {
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName = $safeName !== '' ? $safeName : 'document';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');

        return sprintf(
            'documents/%s/%d/%s_%s.%s',
            $entityType,
            $entityId,
            now()->format('YmdHis'),
            Str::uuid()->toString(),
            $extension
        );
    }
}
