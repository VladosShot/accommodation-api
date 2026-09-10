<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Offer $offer)
    {
        $data = $request->validated();

        $reservation = DB::transaction(function () use ($offer, $data) {
            $offer = Offer::query()
                ->lockForUpdate()
                ->findOrFail($offer->id);

            if ($offer->available_units < 1 || $offer->expires_at->isPast()) {
                return null;
            }

            $offer->decrement('available_units');

            return Reservation::create([
                'offer_id' => $offer->id,
                'client_reference' => $data['client_reference'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
            ]);
        });

        if ($reservation === null) {
            return response()->json([
                'message' => 'Offer is no longer available.',
            ], 409);
        }

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }
}
