<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_search_returns_cheapest_offer(): void
    {
        $supplierA = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $supplierB = Supplier::factory()->create([
            'code' => 'supplier-b',
        ]);

        $property = Property::create([
            'code' => 'TEST-SEARCH-001',
            'name' => 'Test Search Hotel',
            'city' => 'Barcelona',
        ]);

        $importA = Import::create([
            'supplier_id' => $supplierA->id,
            'external_import_id' => 'test-search-import-a',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => ImportStatus::Completed,
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $importB = Import::create([
            'supplier_id' => $supplierB->id,
            'external_import_id' => 'test-search-import-b',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => ImportStatus::Completed,
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        Offer::create([
            'import_id' => $importA->id,
            'supplier_id' => $supplierA->id,
            'property_id' => $property->id,
            'external_id' => 'test-search-offer-a',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 70000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(10),
        ]);

        Offer::create([
            'import_id' => $importB->id,
            'supplier_id' => $supplierB->id,
            'property_id' => $property->id,
            'external_id' => 'test-search-offer-b',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 65000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query([
                'check_in' => '2026-10-01',
                'check_out' => '2026-10-05',
                'guests' => 2,
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $property->id)
            ->assertJsonPath('data.0.offer.id', Offer::where('external_id', 'test-search-offer-b')->first()->id)
            ->assertJsonPath('data.0.offer.supplier', 'supplier-b')
            ->assertJsonPath('data.0.offer.price', 65000);
    }

    public function test_property_search_excludes_unavailable_offers(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-filter-import',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => ImportStatus::Completed,
            'total_offers' => 4,
            'processed_offers' => 4,
        ]);

        $availableProperty = Property::create([
            'code' => 'TEST-FILTER-001',
            'name' => 'Available Hotel',
            'city' => 'Barcelona',
        ]);

        $zeroUnitsProperty = Property::create([
            'code' => 'TEST-FILTER-002',
            'name' => 'No Units Hotel',
            'city' => 'Barcelona',
        ]);

        $guestsProperty = Property::create([
            'code' => 'TEST-FILTER-003',
            'name' => 'Small Hotel',
            'city' => 'Barcelona',
        ]);

        $expiredProperty = Property::create([
            'code' => 'TEST-FILTER-004',
            'name' => 'Expired Hotel',
            'city' => 'Barcelona',
        ]);

        Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $availableProperty->id,
            'external_id' => 'test-filter-offer-valid',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 70000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(10),
        ]);

        Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $zeroUnitsProperty->id,
            'external_id' => 'test-filter-offer-zero-units',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 60000,
            'currency' => 'EUR',
            'available_units' => 0,
            'expires_at' => now()->addDays(10),
        ]);

        Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $guestsProperty->id,
            'external_id' => 'test-filter-offer-guests',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 1,
            'price' => 50000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(10),
        ]);

        Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $expiredProperty->id,
            'external_id' => 'test-filter-offer-expired',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 40000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/properties?' . http_build_query([
                'check_in' => '2026-10-01',
                'check_out' => '2026-10-05',
                'guests' => 2,
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $availableProperty->id)
            ->assertJsonPath('data.0.offer.price', 70000);
    }
}
