<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertySearchRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Offer;
use App\Models\Property;

class PropertyController extends Controller
{
    public function index(PropertySearchRequest $request)
    {
        $filters = $request->validated();

        $rankedOffers = Offer::query()
            ->join('suppliers', 'suppliers.id', '=', 'offers.supplier_id')
            ->select([
                'offers.id',
                'offers.property_id',
                'offers.supplier_id',
                'suppliers.code as supplier_code',
                'offers.check_in',
                'offers.check_out',
                'offers.max_guests',
                'offers.price',
                'offers.currency',
                'offers.available_units',
                'offers.expires_at',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (
            PARTITION BY property_id
            ORDER BY price ASC, id ASC
        ) as offer_rank'
            )
            ->where('check_in', $filters['check_in'])
            ->where('check_out', $filters['check_out'])
            ->where('max_guests', '>=', $filters['guests'])
            ->where('available_units', '>', 0)
            ->where('expires_at', '>', now())
            ->when(
                $filters['city'] ?? null,
                fn ($query, $city) => $query->whereHas(
                    'property',
                    fn ($query) => $query->where('city', $city)
                )
            );

        $properties = Property::query()
            ->joinSub($rankedOffers, 'ranked_offers', function ($join) {
                $join->on('properties.id', '=', 'ranked_offers.property_id');
            })
            ->where('ranked_offers.offer_rank', 1)
            ->select([
                'properties.*',
                'ranked_offers.id as offer_id',
                'ranked_offers.supplier_code as offer_supplier',
                'ranked_offers.check_in as offer_check_in',
                'ranked_offers.check_out as offer_check_out',
                'ranked_offers.max_guests as offer_max_guests',
                'ranked_offers.price as offer_price',
                'ranked_offers.currency as offer_currency',
                'ranked_offers.available_units as offer_available_units',
                'ranked_offers.expires_at as offer_expires_at',
            ])
            ->orderBy('ranked_offers.price')
            ->paginate(15);

        return PropertyResource::collection($properties);
    }
}
