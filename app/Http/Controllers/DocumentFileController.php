<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentFileController extends Controller
{
    public function serve(Request $request, int $id)
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired link.'], 403);
        }

        $doc = Document::whereNull('deleted_at')->findOrFail($id);

        if ($doc->virus_scan_status === 'infected') {
            return response()->json(['success' => false, 'message' => 'Document is quarantined.'], 403);
        }

        $disk = Storage::disk('r2');

        if (! $disk->exists($doc->storage_path)) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        return $disk->response($doc->storage_path, $doc->original_filename, [
            'Content-Type' => $doc->mime_type ?? 'application/octet-stream',
        ]);
    }
}
