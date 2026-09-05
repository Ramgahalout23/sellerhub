<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'charge_structure' => [
                    'commission_percent' => 5,
                    'shipping_fee' => 45,
                    'closing_fee' => 25,
                    'gst_percent' => 18,
                    'pick_and_pack_fee' => 30,
                ],
                'is_active' => true,
                'notes' => 'Amazon India marketplace',
            ],
            [
                'name' => 'Flipkart',
                'slug' => 'flipkart',
                'charge_structure' => [
                    'commission_percent' => 4,
                    'shipping_fee' => 40,
                    'closing_fee' => 20,
                    'gst_percent' => 18,
                    'collection_fee' => 2,
                ],
                'is_active' => true,
                'notes' => 'Flipkart marketplace',
            ],
            [
                'name' => 'Meesho',
                'slug' => 'meesho',
                'charge_structure' => [
                    'commission_percent' => 0,
                    'shipping_fee' => 35,
                    'closing_fee' => 0,
                    'gst_percent' => 18,
                    'return_shipping_charge' => 50,
                ],
                'is_active' => true,
                'notes' => 'Meesho — zero commission model',
            ],
            [
                'name' => 'JioMart',
                'slug' => 'jiomart',
                'charge_structure' => [
                    'commission_percent' => 3,
                    'shipping_fee' => 30,
                    'closing_fee' => 15,
                    'gst_percent' => 18,
                ],
                'is_active' => false,
                'notes' => 'Reliance JioMart — not active yet',
            ],
        ];

        foreach ($platforms as $data) {
            Platform::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
