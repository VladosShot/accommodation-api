<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_job_processes_offers(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-job-import-001',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => ImportStatus::Pending,
            'total_offers' => 1,
        ]);

        $offers = [
            [
                'external_id' => 'test-job-offer-001',
                'property' => [
                    'code' => 'TEST-JOB-001',
                    'name' => 'Test Job Hotel',
                    'city' => 'Barcelona',
                ],
                'check_in' => '2026-10-01',
                'check_out' => '2026-10-05',
                'max_guests' => 2,
                'price' => 70000,
                'currency' => 'EUR',
                'available_units' => 2,
                'expires_at' => '2026-09-30T23:59:59Z',
            ],
        ];

        (new ProcessImportJob($import, $offers))->handle();

        $this->assertDatabaseHas('properties', [
            'code' => 'TEST-JOB-001',
            'name' => 'Test Job Hotel',
            'city' => 'Barcelona',
        ]);

        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'external_id' => 'test-job-offer-001',
            'price' => 70000,
            'available_units' => 2,
        ]);

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => ImportStatus::Completed->value,
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);
    }

    public function test_import_job_updates_existing_offer(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $firstImport = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-update-import-001',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => ImportStatus::Completed,
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $property = Property::create([
            'code' => 'TEST-UPDATE-001',
            'name' => 'Old Hotel Name',
            'city' => 'Barcelona',
        ]);

        $existingOffer = Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $firstImport->id,
            'property_id' => $property->id,
            'external_id' => 'test-update-offer-001',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 70000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => '2026-09-30T23:59:59Z',
        ]);

        $secondImport = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-update-import-002',
            'sent_at' => '2026-09-10T13:00:00Z',
            'status' => ImportStatus::Pending,
            'total_offers' => 1,
        ]);

        $offers = [
            [
                'external_id' => 'test-update-offer-001',
                'property' => [
                    'code' => 'TEST-UPDATE-001',
                    'name' => 'Updated Hotel Name',
                    'city' => 'Madrid',
                ],
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-05',
                'max_guests' => 3,
                'price' => 65000,
                'currency' => 'EUR',
                'available_units' => 5,
                'expires_at' => '2026-10-31T23:59:59Z',
            ],
        ];

        (new ProcessImportJob($secondImport, $offers))->handle();

        $this->assertDatabaseHas('offers', [
            'id' => $existingOffer->id,
            'supplier_id' => $supplier->id,
            'import_id' => $secondImport->id,
            'external_id' => 'test-update-offer-001',
            'price' => 65000,
            'available_units' => 5,
        ]);

        $this->assertDatabaseCount('offers', 1);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'code' => 'TEST-UPDATE-001',
            'name' => 'Updated Hotel Name',
            'city' => 'Madrid',
        ]);

        $this->assertDatabaseCount('properties', 1);
    }
}
