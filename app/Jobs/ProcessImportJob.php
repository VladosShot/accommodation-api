<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Import $import, public array $offers)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->import->update([
            'status' => ImportStatus::Processing,
        ]);

        try {
            foreach ($this->offers as $offer) {
                $property = Property::updateOrCreate(
                    ['code' => $offer['property']['code']],
                    [
                        'name' => $offer['property']['name'],
                        'city' => $offer['property']['city'],
                    ]
                );

                Offer::updateOrCreate(
                    [
                        'supplier_id' => $this->import->supplier_id,
                        'external_id' => $offer['external_id'],
                    ],
                    [
                        'import_id' => $this->import->id,
                        'property_id' => $property->id,
                        'check_in' => $offer['check_in'],
                        'check_out' => $offer['check_out'],
                        'max_guests' => $offer['max_guests'],
                        'price' => $offer['price'],
                        'currency' => $offer['currency'],
                        'available_units' => $offer['available_units'],
                        'expires_at' => $offer['expires_at'],
                    ]
                );

                $this->import->increment('processed_offers');
            }

            $this->import->update([
                'status' => ImportStatus::Completed,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->import->update([
                'status' => ImportStatus::Failed,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
