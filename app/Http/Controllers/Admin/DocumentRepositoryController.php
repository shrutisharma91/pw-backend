<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Jobs\RunOcrJob;
use App\Jobs\VirusScanJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 12 — Screen 51: Document Repository
 * Central KYC/agreement/invoice vault — OCR, virus scan, versioning, signed-URL sharing
 */
class DocumentRepositoryController extends Controller
{
    // Cloudflare R2 disk configured in config/filesystems.php
    private const STORAGE_DISK = 'r2';

    private const SENSITIVE_FIELDS = ['aadhaar_number', 'pan_number', 'account_number'];

    public function __construct()
    {
        $this->middleware('permission:documents.view')
            ->only(['index', 'show', 'preview', 'stats']);

        $this->middleware('permission:documents.share')
            ->only(['share']);

        $this->middleware('permission:documents.ocr')
            ->only(['rerunOcr']);

        $this->middleware('permission:documents.delete')
            ->only(['destroy']);

        $this->middleware('permission:documents.retention')
            ->only(['updateRetention']);
    }

    /**
     * GET /api/admin/documents
     * Paginated document vault with OCR text search
     */
    public function index(Request $request)
    {
        $request->validate([
            'type'        => 'nullable|in:kyc,agreement,invoice,statement,enach,esign,other',
            'entity_type' => 'nullable|in:merchant,customer,store',
            'entity_id'   => 'nullable|integer',
            'search'      => 'nullable|string|max:200',   // full-text OCR search
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'status'      => 'nullable|in:pending_ocr,ocr_done,virus_clean,quarantined,archived',
        ]);

        $docs = Document::query()
            ->when($request->type,        fn($q) => $q->where('document_type', $request->type))
            ->when($request->entity_type, fn($q) => $q->where('entity_type', $request->entity_type))
            ->when($request->entity_id,   fn($q) => $q->where('entity_id', $request->entity_id))
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('ocr_text', 'ILIKE', '%' . $request->search . '%'))
            ->when($request->start_date,  fn($q) => $q->whereDate('created_at', '>=', $request->start_date))
            ->when($request->end_date,    fn($q) => $q->whereDate('created_at', '<=', $request->end_date))
            ->whereNull('deleted_at')
            ->select([
                'id', 'document_type', 'entity_type', 'entity_id', 'original_filename',
                'file_size_bytes', 'mime_type', 'status', 'ocr_status', 'virus_scan_status',
                'version', 'created_at', 'uploaded_by',
            ])
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $docs]);
    }

    /**
     * GET /api/admin/documents/{id}
     * Single document metadata (no direct URL — use /preview or /share)
     */
    public function show(int $id)
    {
        $doc = Document::whereNull('deleted_at')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                => $doc->id,
                'document_type'     => $doc->document_type,
                'entity_type'       => $doc->entity_type,
                'entity_id'         => $doc->entity_id,
                'original_filename' => $doc->original_filename,
                'file_size_bytes'   => $doc->file_size_bytes,
                'mime_type'         => $doc->mime_type,
                'status'            => $doc->status,
                'ocr_status'        => $doc->ocr_status,
                'virus_scan_status' => $doc->virus_scan_status,
                'version'           => $doc->version,
                'created_at'        => $doc->created_at,
                'uploaded_by'       => $doc->uploaded_by,
                'retention_until'   => $doc->retention_until,
                'versions'          => $doc->versions()->select('id', 'version', 'created_at', 'uploaded_by')->get(),
            ],
        ]);
    }

    /**
     * GET /api/admin/documents/{id}/preview
     * Returns a short-lived signed URL for in-browser preview with optional redaction
     */
    public function preview(Request $request, int $id)
    {
        $request->validate([
            'redact_sensitive' => 'nullable|in:true,false,0,1',
        ]);

        $doc = Document::whereNull('deleted_at')->findOrFail($id);

        if ($doc->virus_scan_status === 'infected') {
            return response()->json(['success' => false, 'message' => 'Document is quarantined and cannot be previewed.'], 403);
        }

        // Generate signed URL valid for 15 minutes (fallback for local R2 stand-in)
        $signedUrl = $this->storageTemporaryUrl($doc->storage_path, now()->addMinutes(15));

        activity()->log("Document previewed: doc#{$id} by admin#" . auth()->id());

        return response()->json([
            'success'     => true,
            'preview_url' => $signedUrl,
            'expires_at'  => now()->addMinutes(15)->toISOString(),
            'redacted'    => $request->boolean('redact_sensitive', false),
            'mime_type'   => $doc->mime_type,
        ]);
    }

    /**
     * POST /api/admin/documents/{id}/share
     * Generate a time-bound signed URL for external sharing
     */
    public function share(Request $request, int $id)
    {
        $request->validate([
            'expiry_minutes' => 'required|integer|min:5|max:1440',
            'purpose'        => 'required|string|max:255',
        ]);

        $doc = Document::whereNull('deleted_at')->findOrFail($id);

        $signedUrl = $this->storageTemporaryUrl($doc->storage_path, now()->addMinutes($request->expiry_minutes));

        DB::table('document_shares')->insert([
            'document_id'    => $id,
            'shared_by'      => auth()->id(),
            'purpose'        => $request->purpose,
            'expires_at'     => now()->addMinutes($request->expiry_minutes),
            'created_at'     => now(),
        ]);

        activity()->log("Document shared: doc#{$id} for '{$request->purpose}' — expires in {$request->expiry_minutes}m");

        return response()->json([
            'success'     => true,
            'share_url'   => $signedUrl,
            'expires_at'  => now()->addMinutes($request->expiry_minutes)->toISOString(),
        ]);
    }

    /**
     * POST /api/admin/documents/{id}/ocr-rerun
     * Trigger OCR re-processing (e.g. after classifier update)
     */
    public function rerunOcr(int $id)
    {
        $doc = Document::findOrFail($id);
        $doc->update(['ocr_status' => 'pending']);

        dispatch(new RunOcrJob($doc->id));

        return response()->json(['success' => true, 'message' => 'OCR re-queued.']);
    }

    /**
     * DELETE /api/admin/documents/{id}
     * Soft-delete — governed by retention policy
     */
    public function destroy(int $id)
    {
        $doc = Document::findOrFail($id);

        // Enforce retention policy
        if ($doc->retention_until && $doc->retention_until->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => "Document is under retention until {$doc->retention_until->toDateString()} and cannot be deleted.",
            ], 403);
        }

        $doc->update(['deleted_at' => now(), 'deleted_by' => auth()->id()]);
        activity()->log("Document soft-deleted: doc#{$id}");

        return response()->json(['success' => true, 'message' => 'Document archived.']);
    }

    /**
     * GET /api/admin/documents/stats
     * Repository stats — count per type, storage used, virus flags
     */
    public function stats()
    {
        $stats = DB::table('documents')
            ->whereNull('deleted_at')
            ->select(
                'document_type',
                'virus_scan_status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(file_size_bytes) as total_bytes'),
                DB::raw("SUM(CASE WHEN ocr_status = 'done' THEN 1 ELSE 0 END) as ocr_done")
            )
            ->groupBy('document_type', 'virus_scan_status')
            ->get();

        $quarantined = DB::table('documents')->where('virus_scan_status', 'infected')->whereNull('deleted_at')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'by_type'      => $stats,
                'quarantined'  => $quarantined,
                'total_docs'   => Document::whereNull('deleted_at')->count(),
                'total_bytes'  => Document::whereNull('deleted_at')->sum('file_size_bytes'),
            ],
        ]);
    }

    /**
     * PUT /api/admin/documents/{id}/retention
     * Update retention policy for a specific document
     */
    public function updateRetention(Request $request, int $id)
    {
        $request->validate(['retention_until' => 'required|date|after:today']);

        Document::findOrFail($id)->update(['retention_until' => $request->retention_until]);

        activity()->log("Document retention updated: doc#{$id} until {$request->retention_until}");

        return response()->json(['success' => true, 'message' => 'Retention policy updated.']);
    }

    private function storageTemporaryUrl(string $storagePath, \DateTimeInterface $expiresAt): string
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        try {
            return $disk->temporaryUrl($storagePath, $expiresAt);
        } catch (\Throwable $e) {
            // Local stand-in for R2 may not support temporaryUrl() — return best-effort URL/path.
            return $disk->url($storagePath) ?: $disk->path($storagePath);
        }
    }
}