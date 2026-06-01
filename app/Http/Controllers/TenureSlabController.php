<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\TenureSlab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TenureSlabController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/pricing/tenure-slabs",
        summary: "List Tenure Slabs",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        parameters: [
            new OA\Parameter(name: "emi_type_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = TenureSlab::with('emiType');
        if ($request->has('emi_type_id')) {
            $query->where('emi_type_id', $request->emi_type_id);
        }
        return response()->json($query->get());
    }

    #[OA\Post(
        path: "/api/v1/admin/pricing/tenure-slabs",
        summary: "Create Tenure Slab",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "emi_type_id", type: "integer", example: 1),
                    new OA\Property(property: "tenure_months", type: "integer", example: 6),
                    new OA\Property(property: "base_interest_rate", type: "number", example: 12.5),
                    new OA\Property(property: "processing_fee_type", type: "string", example: "percentage"),
                    new OA\Property(property: "processing_fee_value", type: "number", example: 1.5),
                    new OA\Property(property: "processing_fee_cap", type: "number", example: 1500),
                    new OA\Property(property: "tier_overrides", type: "object", example: ["Gold" => ["base_interest_rate" => 10.5]])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'emi_type_id' => 'required|exists:emi_types,id',
            'tenure_months' => 'required|integer',
            'base_interest_rate' => 'required|numeric',
            'processing_fee_type' => 'required|in:flat,percentage',
            'processing_fee_value' => 'required|numeric',
            'processing_fee_cap' => 'nullable|numeric',
            'tier_overrides' => 'nullable|array',
        ]);

        $slab = TenureSlab::create($validated);
        return response()->json($slab, 201);
    }

    #[OA\Get(
        path: "/api/v1/admin/pricing/tenure-slabs/{id}",
        summary: "Show Tenure Slab",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($id)
    {
        return response()->json(TenureSlab::with('emiType')->findOrFail($id));
    }

    #[OA\Put(
        path: "/api/v1/admin/pricing/tenure-slabs/{id}",
        summary: "Update Tenure Slab",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "emi_type_id", type: "integer", example: 1),
                    new OA\Property(property: "tenure_months", type: "integer", example: 6),
                    new OA\Property(property: "base_interest_rate", type: "number", example: 11.0)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $slab = TenureSlab::findOrFail($id);

        $validated = $request->validate([
            'emi_type_id' => 'sometimes|exists:emi_types,id',
            'tenure_months' => 'sometimes|integer',
            'base_interest_rate' => 'sometimes|numeric',
            'processing_fee_type' => 'sometimes|in:flat,percentage',
            'processing_fee_value' => 'sometimes|numeric',
            'processing_fee_cap' => 'nullable|numeric',
            'tier_overrides' => 'nullable|array',
        ]);

        $slab->update($validated);
        return response()->json($slab);
    }

    #[OA\Delete(
        path: "/api/v1/admin/pricing/tenure-slabs/{id}",
        summary: "Delete Tenure Slab",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Success")
        ]
    )]
    public function destroy($id)
    {
        TenureSlab::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    #[OA\Get(
        path: "/api/v1/admin/pricing/tenure-slabs/export",
        summary: "Export Tenure Slabs to CSV",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        responses: [
            new OA\Response(response: 200, description: "CSV file download")
        ]
    )]
    public function exportCsv()
    {
        $slabs = TenureSlab::with('emiType')->get();
        
        $csvData = "ID,EMI Type,Tenure (Months),Base Interest Rate,Proc Fee Type,Proc Fee Value,Tier Overrides\n";
        
        foreach ($slabs as $slab) {
            $overrides = json_encode($slab->tier_overrides);
            $emiName = $slab->emiType ? $slab->emiType->name : '';
            $csvData .= "{$slab->id},{$emiName},{$slab->tenure_months},{$slab->base_interest_rate},{$slab->processing_fee_type},{$slab->processing_fee_value},\"{$overrides}\"\n";
        }

        return Response::make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tenure_slabs.csv"',
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/pricing/tenure-slabs/import",
        summary: "Import Tenure Slabs from CSV",
        security: [["sanctum" => []]],
        tags: ["TenureSlab"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "csv_file", type: "string", format: "binary", description: "CSV file to import")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));
        
        $header = array_shift($data);
        
        $importedCount = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            if (count($row) < 7) continue;
            
            $id = $row[0];
            $tierOverrides = json_decode($row[6], true) ?: null;

            if ($id && is_numeric($id)) {
                $slab = TenureSlab::find($id);
                if ($slab) {
                    // Prevent 500 DB error by ensuring types are somewhat valid before updating
                    if (!is_numeric($row[2]) || !is_numeric($row[3]) || !is_numeric($row[5])) {
                        $errors[] = "Row " . ($index + 2) . ": Invalid numeric format for tenure, interest rate, or fee value.";
                        continue;
                    }

                    $slab->update([
                        'tenure_months' => $row[2],
                        'base_interest_rate' => $row[3],
                        'processing_fee_type' => $row[4],
                        'processing_fee_value' => $row[5],
                        'tier_overrides' => $tierOverrides,
                    ]);
                    $importedCount++;
                }
            }
        }

        return response()->json([
            'message' => "Imported/Updated {$importedCount} records.",
            'errors'  => $errors
        ]);
    }
}
