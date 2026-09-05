<?php

namespace Database\Seeders;

use App\Models\Alert;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        $alerts = [
            [
                'type' => 'return_reminder',
                'title' => 'Check return status — AMZ-2026-004',
                'message' => 'Order AMZ-2026-004 (LED Desk Lamp) was shipped 3 days ago. Please check delivery status.',
                'entity_type' => 'order',
                'is_read' => false,
            ],
            [
                'type' => 'return_reminder',
                'title' => 'Check return status — FLP-2026-004',
                'message' => 'Order FLP-2026-004 (Bluetooth Earbuds) was shipped 5 days ago. Please check delivery status.',
                'entity_type' => 'order',
                'is_read' => false,
            ],
            [
                'type' => 'low_stock',
                'title' => 'Low stock — USB-C Fast Charging Cable',
                'message' => 'SKU ELEC-USBC-001 has only 3 units left (reorder threshold: 25). Consider reordering.',
                'entity_type' => 'product',
                'is_read' => false,
            ],
            [
                'type' => 'low_stock',
                'title' => 'Out of stock — Stainless Steel Water Bottle',
                'message' => 'SKU KIT-SSB-001 has only 8 units left (reorder threshold: 20). Reorder from Patel Wholesale Hub.',
                'entity_type' => 'product',
                'is_read' => false,
            ],
            [
                'type' => 'low_stock',
                'title' => 'Out of stock — Men Polyester Belt',
                'message' => 'SKU CLO-BLT-001 is completely out of stock (0 units). Reorder from Shree Ganesh Traders.',
                'entity_type' => 'product',
                'is_read' => true,
                'read_at' => now()->subDays(1),
            ],
        ];

        foreach ($alerts as $data) {
            Alert::create($data);
        }
    }
}
