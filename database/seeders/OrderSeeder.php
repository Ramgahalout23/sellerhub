<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seeds demo orders using the current line-item schema.
     *
     * Requires PlatformSeeder, SupplierSeeder, ProductSeeder and
     * BatchOrderSeeder to have run first (for platforms and products).
     */
    public function run(): void
    {
        $platforms = [
            'amazon' => Platform::where('slug', 'amazon')->first(),
            'flipkart' => Platform::where('slug', 'flipkart')->first(),
            'meesho' => Platform::where('slug', 'meesho')->first(),
        ];

        if (collect($platforms)->filter()->isEmpty()) {
            $this->command?->warn('OrderSeeder skipped: seed platforms first.');

            return;
        }

        $orders = [
            // --- Successful orders ---
            [
                'order_number' => 'AMZ-2026-001',
                'sku' => 'ELEC-TWS-001',
                'platform' => 'amazon',
                'customer_name' => 'Amit Singh',
                'quantity' => 1,
                'selling_price' => 699,
                'shipped_at' => now()->subDays(28),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 45],
                    ['charge_name' => 'commission', 'amount' => 35],
                    ['charge_name' => 'gst', 'amount' => 14],
                ],
            ],
            [
                'order_number' => 'FLP-2026-001',
                'sku' => 'CASE-IP15-001',
                'platform' => 'flipkart',
                'customer_name' => 'Priya Verma',
                'quantity' => 2,
                'selling_price' => 249,
                'shipped_at' => now()->subDays(26),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 55],
                    ['charge_name' => 'commission', 'amount' => 20],
                    ['charge_name' => 'gst', 'amount' => 13],
                ],
            ],
            [
                'order_number' => 'MSH-2026-001',
                'sku' => 'CLO-TSH-001',
                'platform' => 'meesho',
                'customer_name' => 'Neha Gupta',
                'quantity' => 3,
                'selling_price' => 399,
                'shipped_at' => now()->subDays(24),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 80],
                    ['charge_name' => 'gst', 'amount' => 24],
                ],
            ],
            [
                'order_number' => 'AMZ-2026-002',
                'sku' => 'ELEC-USBC-001',
                'platform' => 'amazon',
                'customer_name' => 'Ravi Patel',
                'quantity' => 2,
                'selling_price' => 179,
                'shipped_at' => now()->subDays(22),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 40],
                    ['charge_name' => 'commission', 'amount' => 18],
                    ['charge_name' => 'gst', 'amount' => 11],
                ],
            ],
            [
                'order_number' => 'FLP-2026-002',
                'sku' => 'KIT-LED-001',
                'platform' => 'flipkart',
                'customer_name' => 'Sanjay Mehta',
                'quantity' => 1,
                'selling_price' => 599,
                'shipped_at' => now()->subDays(20),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 65],
                    ['charge_name' => 'commission', 'amount' => 24],
                    ['charge_name' => 'gst', 'amount' => 16],
                ],
            ],

            // --- Customer return ---
            [
                'order_number' => 'AMZ-2026-003',
                'sku' => 'ELEC-TWS-001',
                'platform' => 'amazon',
                'customer_name' => 'Vikram Joshi',
                'quantity' => 1,
                'selling_price' => 699,
                'shipped_at' => now()->subDays(18),
                'item_status' => 'customer_return',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 45],
                    ['charge_name' => 'commission', 'amount' => 35],
                    ['charge_name' => 'gst', 'amount' => 14],
                    ['charge_name' => 'return_shipping', 'amount' => 50],
                ],
                'return' => [
                    'return_type' => 'customer_return',
                    'condition' => 'sellable',
                    'return_charges' => 50,
                    'received_at' => now()->subDays(12),
                ],
            ],

            // --- RTO ---
            [
                'order_number' => 'MSH-2026-002',
                'sku' => 'KIT-SSB-001',
                'platform' => 'meesho',
                'customer_name' => 'Deepa Nair',
                'quantity' => 1,
                'selling_price' => 449,
                'shipped_at' => now()->subDays(16),
                'item_status' => 'rto',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 55],
                    ['charge_name' => 'gst', 'amount' => 27],
                    ['charge_name' => 'rto_charge', 'amount' => 60],
                ],
                'return' => [
                    'return_type' => 'rto',
                    'condition' => 'damaged',
                    'return_charges' => 60,
                    'received_at' => now()->subDays(10),
                ],
            ],

            // --- Missing ---
            [
                'order_number' => 'FLP-2026-003',
                'sku' => 'CLO-TSH-001',
                'platform' => 'flipkart',
                'customer_name' => 'Karan Malhotra',
                'quantity' => 2,
                'selling_price' => 399,
                'shipped_at' => now()->subDays(21),
                'item_status' => 'missing',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 60],
                    ['charge_name' => 'commission', 'amount' => 32],
                ],
                'return' => [
                    'return_type' => 'missing',
                    'condition' => null,
                    'return_charges' => 0,
                    'quantity_returned' => 0,
                    'received_at' => null,
                ],
            ],

            // --- Active orders (shipped / in transit) ---
            [
                'order_number' => 'AMZ-2026-004',
                'sku' => 'KIT-LED-001',
                'platform' => 'amazon',
                'customer_name' => 'Rohit Sharma',
                'quantity' => 1,
                'selling_price' => 599,
                'shipped_at' => now()->subDays(3),
                'order_status' => 'shipped',
                'item_status' => 'pending',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 55],
                    ['charge_name' => 'commission', 'amount' => 30],
                ],
            ],
            [
                'order_number' => 'FLP-2026-004',
                'sku' => 'ELEC-TWS-001',
                'platform' => 'flipkart',
                'customer_name' => 'Ananya Reddy',
                'quantity' => 1,
                'selling_price' => 699,
                'shipped_at' => now()->subDays(5),
                'order_status' => 'in_transit',
                'item_status' => 'pending',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 45],
                    ['charge_name' => 'commission', 'amount' => 28],
                ],
            ],

            // --- More successful orders ---
            [
                'order_number' => 'MSH-2026-003',
                'sku' => 'CASE-IP15-001',
                'platform' => 'meesho',
                'customer_name' => 'Pooja Das',
                'quantity' => 1,
                'selling_price' => 249,
                'shipped_at' => now()->subDays(15),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 40],
                    ['charge_name' => 'gst', 'amount' => 15],
                ],
            ],
            [
                'order_number' => 'AMZ-2026-005',
                'sku' => 'ELEC-USBC-001',
                'platform' => 'amazon',
                'customer_name' => 'Manoj Tiwari',
                'quantity' => 3,
                'selling_price' => 179,
                'shipped_at' => now()->subDays(10),
                'item_status' => 'successful',
                'charges' => [
                    ['charge_name' => 'shipping', 'amount' => 50],
                    ['charge_name' => 'commission', 'amount' => 27],
                    ['charge_name' => 'gst', 'amount' => 14],
                ],
            ],
        ];

        foreach ($orders as $data) {
            $product = Product::where('sku', $data['sku'])->first();
            $platform = $platforms[$data['platform']] ?? null;

            if (! $product || ! $platform) {
                continue;
            }

            if (Order::where('order_number', $data['order_number'])->exists()) {
                continue;
            }

            $order = Order::create([
                'order_number' => $data['order_number'],
                'platform_id' => $platform->id,
                'customer_name' => $data['customer_name'],
                'status' => $data['order_status'] ?? 'delivered',
                'shipped_at' => $data['shipped_at'],
                'status_updated_at' => $data['shipped_at'],
                'reminder_at' => ($data['order_status'] ?? null)
                    ? $data['shipped_at']->copy()->addDays(7)
                    : null,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
                'selling_price' => $data['selling_price'],
                'status' => $data['item_status'],
            ]);

            foreach ($data['charges'] ?? [] as $charge) {
                $item->charges()->create([
                    'charge_name' => $charge['charge_name'],
                    'amount' => $charge['amount'],
                ]);
            }

            if (! empty($data['return']) && in_array($data['item_status'], ['customer_return', 'rto', 'missing'], true)) {
                $item->returnDetail()->create(array_merge([
                    'quantity_returned' => $data['quantity'],
                ], $data['return']));
            }
        }
    }
}
