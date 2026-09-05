<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Wireless Bluetooth Earbuds TWS',
                'sku' => 'ELEC-TWS-001',
                'description' => 'True wireless earbuds with charging case, 20hr battery',
                'cost_price' => 280,
                'selling_price' => 699,
                'stock_quantity' => 45,
                'reorder_threshold' => 15,
                'image' => 'products/earbuds-tws.jpg',
                'is_active' => true,
                'suppliers' => ['Rajesh Traders', 'Balaji Enterprises'],
            ],
            [
                'name' => 'Silicone Phone Case - iPhone 15',
                'sku' => 'CASE-IP15-001',
                'description' => 'Shockproof silicone case for iPhone 15',
                'cost_price' => 65,
                'selling_price' => 249,
                'stock_quantity' => 120,
                'reorder_threshold' => 30,
                'image' => 'products/iphone15-case.jpg',
                'is_active' => true,
                'suppliers' => ['Balaji Enterprises'],
            ],
            [
                'name' => 'Stainless Steel Water Bottle 1L',
                'sku' => 'KIT-SSB-001',
                'description' => 'Double-wall insulated water bottle, 1 litre',
                'cost_price' => 150,
                'selling_price' => 449,
                'stock_quantity' => 8,
                'reorder_threshold' => 20,
                'image' => 'products/water-bottle-1l.jpg',
                'is_active' => true,
                'suppliers' => ['Patel Wholesale Hub'],
            ],
            [
                'name' => 'Cotton Round Neck T-Shirt Men',
                'sku' => 'CLO-TSH-001',
                'description' => '100% cotton round neck t-shirt, available in multiple colors',
                'cost_price' => 120,
                'selling_price' => 399,
                'stock_quantity' => 200,
                'reorder_threshold' => 50,
                'image' => 'products/tshirt-round.jpg',
                'is_active' => true,
                'suppliers' => ['Shree Ganesh Traders'],
            ],
            [
                'name' => 'USB-C Fast Charging Cable 1.5m',
                'sku' => 'ELEC-USBC-001',
                'description' => 'Type-C to Type-C 60W fast charge cable',
                'cost_price' => 45,
                'selling_price' => 179,
                'stock_quantity' => 3,
                'reorder_threshold' => 25,
                'image' => 'products/usbc-cable.jpg',
                'is_active' => true,
                'suppliers' => ['Rajesh Traders', 'Balaji Enterprises'],
            ],
            [
                'name' => 'LED Desk Lamp Foldable',
                'sku' => 'KIT-LED-001',
                'description' => 'Foldable LED desk lamp with 3 brightness levels',
                'cost_price' => 220,
                'selling_price' => 599,
                'stock_quantity' => 35,
                'reorder_threshold' => 10,
                'image' => 'products/desk-lamp.jpg',
                'is_active' => true,
                'suppliers' => ['Patel Wholesale Hub'],
            ],
            [
                'name' => 'Men Polyester Belt - Black',
                'sku' => 'CLO-BLT-001',
                'description' => 'Formal polyester belt for men, auto-lock buckle',
                'cost_price' => 85,
                'selling_price' => 299,
                'stock_quantity' => 0,
                'reorder_threshold' => 20,
                'image' => 'products/mens-belt.jpg',
                'is_active' => true,
                'suppliers' => ['Shree Ganesh Traders'],
            ],
            [
                'name' => 'Notebook A4 Lined 200 Pages',
                'sku' => 'STA-NB-001',
                'description' => 'Premium A4 ruled notebook, 200 pages, hardcover',
                'cost_price' => 55,
                'selling_price' => 199,
                'stock_quantity' => 0,
                'reorder_threshold' => 30,
                'image' => 'products/notebook-a4.jpg',
                'is_active' => false,
                'suppliers' => [],
            ],
        ];

        foreach ($products as $data) {
            $supplierNames = $data['suppliers'];
            unset($data['suppliers']);

            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                $data
            );

            // Attach suppliers via pivot
            if (!empty($supplierNames)) {
                $supplierIds = Supplier::whereIn('name', $supplierNames)->pluck('id');
                $product->suppliers()->syncWithPivotValues($supplierIds, [
                    'last_known_price' => $product->cost_price,
                ]);
            }
        }
    }
}
