<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_can_be_reserved(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-reservation-import',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $property = Property::create([
            'code' => 'TEST-RESERVATION-001',
            'name' => 'Reservation Hotel',
            'city' => 'Barcelona',
        ]);

        $offer = Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'external_id' => 'test-reservation-offer',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 70000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(10),
        ]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'client-ref-001',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.client_reference', 'client-ref-001')
            ->assertJsonPath('data.customer_name', 'John Doe')
            ->assertJsonPath('data.customer_email', 'john@example.com');

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'client-ref-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 1,
        ]);
    }

    public function test_offer_cannot_be_reserved_when_no_units_are_available(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-reservation-unavailable-import',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $property = Property::create([
            'code' => 'TEST-RESERVATION-002',
            'name' => 'Unavailable Hotel',
            'city' => 'Barcelona',
        ]);

        $offer = Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'external_id' => 'test-reservation-unavailable-offer',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 70000,
            'currency' => 'EUR',
            'available_units' => 0,
            'expires_at' => now()->addDays(10),
        ]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'client-ref-unavailable',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ]
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Offer is no longer available.',
            ]);

        $this->assertDatabaseCount('reservations', 0);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 0,
        ]);
    }

    public function test_expired_offer_cannot_be_reserved(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-reservation-expired-import',
            'sent_at' => '2026-09-10T12:00:00Z',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $property = Property::create([
            'code' => 'TEST-RESERVATION-003',
            'name' => 'Expired Hotel',
            'city' => 'Barcelona',
        ]);

        $offer = Offer::create([
            'import_id' => $import->id,
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'external_id' => 'test-reservation-expired-offer',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 70000,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson(
            "/api/offers/{$offer->id}/reservations",
            [
                'client_reference' => 'client-ref-expired',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ]
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Offer is no longer available.',
            ]);

        $this->assertDatabaseCount('reservations', 0);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 2,
        ]);
    }
}
