<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Supplier;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Supplier::create([
            'code' => 'supplier-a',
        ]);

        Supplier::create([
            'code' => 'supplier-b',
        ]);

        Property::create([
            'code' => 'BCN-0001',
            'name' => 'Hotel Barcelona Center',
            'city' => 'Barcelona',
        ]);

        Property::create([
            'code' => 'BCN-0002',
            'name' => 'Barcelona Apartments',
            'city' => 'Barcelona',
        ]);

        Property::create([
            'code' => 'MAD-0001',
            'name' => 'Madrid Grand Hotel',
            'city' => 'Madrid',
        ]);
    }
}
