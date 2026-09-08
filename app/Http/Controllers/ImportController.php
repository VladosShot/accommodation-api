<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Http\Requests\StoreImportRequest;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $supplier = Supplier::where('code', $data['supplier'])->firstOrFail();

        $existingImport = Import::where('supplier_id', $supplier->id)
            ->where('external_import_id', $data['external_import_id'])
            ->first();

        if ($existingImport) {
            return response()->json([
                'data' => [
                    'id' => $existingImport->id,
                    'status' => $existingImport->status->value,
                ],
            ], 202);
        }

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => $data['external_import_id'],
            'sent_at' => $data['sent_at'],
            'status' => ImportStatus::Pending,
            'total_offers' => count($data['offers']),
        ]);

        ProcessImportJob::dispatch($import, $data['offers']);

        return response()->json([
            'data' => [
                'id' => $import->id,
                'status' => $import->status->value,
                'total_offers' => $import->total_offers,
            ],
        ], 202);
    }
}
