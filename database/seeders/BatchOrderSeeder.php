<?php

namespace Database\Seeders;

use App\Models\BatchOrder;
use App\Models\BatchOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class BatchOrderSeeder extends Seeder
{
    public function run(): void
    {
        $batches = [
            [
                'supplier' => 'Rajesh Traders',
                'order_date' => now()->subDays(30),
                'notes' => 'Monthly restock — electronics',
                'items' => [
                    ['sku' => 'ELEC-TWS-001', 'quantity' => 20, 'unit_cost' => 280],
                    ['sku' => 'ELEC-USBC-001', 'quantity' => 50, 'unit_cost' => 45],
                ],
            ],
            [
                'supplier' => 'Patel Wholesale Hub',
                'order_date' => now()->subDays(25),
                'notes' => 'Kitchen items restock',
                'items' => [
                    ['sku' => 'KIT-SSB-001', 'quantity' => 30, 'unit_cost' => 150],
                    ['sku' => 'KIT-LED-001', 'quantity' => 15, 'unit_cost' => 220],
                ],
            ],
            [
                'supplier' => 'Shree Ganesh Traders',
                'order_date' => now()->subDays(20),
                'notes' => 'Clothing batch order',
                'items' => [
                    ['sku' => 'CLO-TSH-001', 'quantity' => 100, 'unit_cost' => 120],
                    ['sku' => 'CLO-BLT-001', 'quantity' => 40, 'unit_cost' => 85],
                ],
            ],
            [
                'supplier' => 'Balaji Enterprises',
                'order_date' => now()->subDays(15),
                'notes' => 'Phone accessories top-up',
                'items' => [
                    ['sku' => 'CASE-IP15-001', 'quantity' => 60, 'unit_cost' => 65],
                    ['sku' => 'ELEC-TWS-001', 'quantity' => 25, 'unit_cost' => 270],
                    ['sku' => 'ELEC-USBC-001', 'quantity' => 40, 'unit_cost' => 42],
                ],
            ],
            [
                'supplier' => 'Rajesh Traders',
                'order_date' => now()->subDays(5),
                'notes' => 'Emergency restock — cables selling fast',
                'items' => [
                    ['sku' => 'ELEC-USBC-001', 'quantity' => 80, 'unit_cost' => 43],
                ],
            ],
            [
                'supplier' => 'Shree Ganesh Traders',
                'order_date' => now()->subDays(2),
                'notes' => 'Belt reorder — was out of stock',
                'items' => [
                    ['sku' => 'CLO-BLT-001', 'quantity' => 50, 'unit_cost' => 80],
                ],
            ],
        ];

        foreach ($batches as $batch) {
            $supplier = Supplier::where('name', $batch['supplier'])->first();
            if (!$supplier) continue;

            $totalCost = 0;
            foreach ($batch['items'] as $item) {
                $totalCost += $item['quantity'] * $item['unit_cost'];
            }

            $batchOrder = BatchOrder::create([
                'supplier_id' => $supplier->id,
                'order_date' => $batch['order_date'],
                'total_cost' => $totalCost,
                'notes' => $batch['notes'],
            ]);

            foreach ($batch['items'] as $item) {
                $product = Product::where('sku', $item['sku'])->first();
                if (!$product) continue;

                BatchOrderItem::create([
                    'batch_order_id' => $batchOrder->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['quantity'] * $item['unit_cost'],
                ]);
            }
        }
    }
}
