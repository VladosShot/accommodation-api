<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportJob;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_can_be_created(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $response = $this->postJson('/api/imports', [
            'supplier' => $supplier->code,
            'external_import_id' => 'test-import-001',
            'sent_at' => '2026-09-10T12:00:00Z',
            'offers' => [
                [
                    'external_id' => 'test-offer-001',
                    'property' => [
                        'code' => 'TEST-001',
                        'name' => 'Test Hotel',
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
            ],
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('imports', [
            'supplier_id' => $supplier->id,
            'external_import_id' => 'test-import-001',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessImportJob::class);
    }

    public function test_duplicate_import_is_not_created_again(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'code' => 'supplier-a',
        ]);

        $payload = [
            'supplier' => $supplier->code,
            'external_import_id' => 'test-import-duplicate',
            'sent_at' => '2026-09-10T12:00:00Z',
            'offers' => [
                [
                    'external_id' => 'test-offer-duplicate',
                    'property' => [
                        'code' => 'TEST-002',
                        'name' => 'Test Hotel 2',
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
            ],
        ];

        $firstResponse = $this->postJson('/api/imports', $payload);

        $firstResponse
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(ProcessImportJob::class, 1);

        $secondResponse = $this->postJson('/api/imports', $payload);

        $secondResponse
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('imports', 1);

        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_import_requires_existing_supplier(): void
    {
        $response = $this->postJson('/api/imports', [
            'supplier' => 'unknown-supplier',
            'external_import_id' => 'test-import-invalid-supplier',
            'sent_at' => '2026-09-10T12:00:00Z',
            'offers' => [
                [
                    'external_id' => 'test-offer-invalid-supplier',
                    'property' => [
                        'code' => 'TEST-003',
                        'name' => 'Test Hotel',
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
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier']);

        $this->assertDatabaseCount('imports', 0);
    }
}
