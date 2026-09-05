<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Platform;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\BatchOrder;
use App\Models\BatchOrderItem;
use App\Models\StockBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\GeneralExpense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ════════════════════════════════════════════
        // STEP 1: User
        // ════════════════════════════════════════════
        User::factory()->create([
            'name' => 'Ram',
            'email' => 'ram@sellinghub.local',
            'password' => bcrypt('password'),
        ]);

        // ════════════════════════════════════════════
        // STEP 2: Platform (Amazon)
        // ════════════════════════════════════════════
        $amazon = Platform::create([
            'name' => 'Amazon',
            'slug' => 'amazon',
            'charge_structure' => [
                'commission_percent' => 5,
                'shipping_fee' => 45,
                'closing_fee' => 25,
            ],
            'is_active' => true,
        ]);

        $flipkart = Platform::create([
            'name' => 'Flipkart',
            'slug' => 'flipkart',
            'charge_structure' => [
                'commission_percent' => 4,
                'shipping_fee' => 40,
                'closing_fee' => 20,
            ],
            'is_active' => true,
        ]);

        // ════════════════════════════════════════════
        // STEP 3: Supplier (Rajesh)
        // ════════════════════════════════════════════
        $rajesh = Supplier::create([
            'name' => 'Rajesh Traders',
            'contact_person' => 'Rajesh Kumar',
            'phone' => '9876543210',
            'email' => 'rajesh@traders.in',
            'address' => 'Shop 45, Sadar Bazaar, Delhi',
            'is_active' => true,
        ]);

        $patel = Supplier::create([
            'name' => 'Patel Wholesale Hub',
            'contact_person' => 'Mahesh Patel',
            'phone' => '9123456780',
            'email' => 'mahesh@patelhub.com',
            'address' => '23, Manek Chowk, Ahmedabad',
            'is_active' => true,
        ]);

        // ════════════════════════════════════════════
        // STEP 4: Product (1 product — Earbuds)
        // Cost: ₹280, Sell: ₹699, Stock: 0 (will come from batch)
        // ════════════════════════════════════════════
        $earbuds = Product::create([
            'name' => 'Wireless Bluetooth Earbuds TWS',
            'sku' => 'ELEC-TWS-001',
            'description' => 'True wireless earbuds with charging case',
            'cost_price' => 280,
            'selling_price' => 699,
            'stock_quantity' => 0, // will be set by batch order
            'reorder_threshold' => 10,
            'is_active' => true,
        ]);

        $cable = Product::create([
            'name' => 'USB-C Fast Charging Cable',
            'sku' => 'ELEC-USBC-001',
            'description' => 'Type-C to Type-C 60W fast charge cable',
            'cost_price' => 45,
            'selling_price' => 179,
            'stock_quantity' => 0,
            'reorder_threshold' => 10,
            'is_active' => true,
        ]);

        // Attach suppliers
        $earbuds->suppliers()->attach($rajesh->id, ['last_known_price' => 280]);
        $earbuds->suppliers()->attach($patel->id, ['last_known_price' => 300]);
        $cable->suppliers()->attach($rajesh->id, ['last_known_price' => 45]);

        // ════════════════════════════════════════════
        // STEP 5: Batch Order 1 — Buy 50 Earbuds from Rajesh @ ₹280
        // ════════════════════════════════════════════
        $batch1 = BatchOrder::create([
            'supplier_id' => $rajesh->id,
            'order_date' => '2026-08-20',
            'notes' => 'First purchase — 50 earbuds',
            'total_cost' => 0, // will be calculated
        ]);

        $batch1Item = BatchOrderItem::create([
            'batch_order_id' => $batch1->id,
            'product_id' => $earbuds->id,
            'quantity' => 50,
            'unit_cost' => 280,
            'total_cost' => 50 * 280, // = 14,000
        ]);

        // Create stock batch for FIFO tracking
        $sb1 = StockBatch::create([
            'batch_order_item_id' => $batch1Item->id,
            'product_id' => $earbuds->id,
            'supplier_id' => $rajesh->id,
            'original_quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost' => 280,
        ]);

        // Update batch order total
        $batch1->update(['total_cost' => 14000]);

        // Update product stock
        $earbuds->update(['stock_quantity' => 50]);

        // ════════════════════════════════════════════
        // STEP 6: Batch Order 2 — Buy 30 Cables from Rajesh @ ₹45
        // ════════════════════════════════════════════
        $batch2 = BatchOrder::create([
            'supplier_id' => $rajesh->id,
            'order_date' => '2026-08-22',
            'notes' => 'Cables restock',
            'total_cost' => 0,
        ]);

        $batch2Item = BatchOrderItem::create([
            'batch_order_id' => $batch2->id,
            'product_id' => $cable->id,
            'quantity' => 30,
            'unit_cost' => 45,
            'total_cost' => 30 * 45, // = 1,350
        ]);

        $sb2 = StockBatch::create([
            'batch_order_item_id' => $batch2Item->id,
            'product_id' => $cable->id,
            'supplier_id' => $rajesh->id,
            'original_quantity' => 30,
            'remaining_quantity' => 30,
            'unit_cost' => 45,
        ]);

        $batch2->update(['total_cost' => 1350]);
        $cable->update(['stock_quantity' => 30]);

        // ════════════════════════════════════════════
        // STEP 7: Batch Order 3 — Buy 20 Earbuds from Patel @ ₹300
        // ════════════════════════════════════════════
        $batch3 = BatchOrder::create([
            'supplier_id' => $patel->id,
            'order_date' => '2026-08-25',
            'notes' => 'Patel se sasta earbuds',
            'total_cost' => 0,
        ]);

        $batch3Item = BatchOrderItem::create([
            'batch_order_id' => $batch3->id,
            'product_id' => $earbuds->id,
            'quantity' => 20,
            'unit_cost' => 300,
            'total_cost' => 20 * 300, // = 6,000
        ]);

        $sb3 = StockBatch::create([
            'batch_order_item_id' => $batch3Item->id,
            'product_id' => $earbuds->id,
            'supplier_id' => $patel->id,
            'original_quantity' => 20,
            'remaining_quantity' => 20,
            'unit_cost' => 300,
        ]);

        $batch3->update(['total_cost' => 6000]);
        $earbuds->update(['stock_quantity' => 70]); // 50 + 20

        // ════════════════════════════════════════════
        // STEP 8: General Expenses
        // ════════════════════════════════════════════
        GeneralExpense::create(['category' => 'rent', 'amount' => 5000, 'description' => 'August rent', 'expense_date' => '2026-08-01']);
        GeneralExpense::create(['category' => 'packaging', 'amount' => 2000, 'description' => 'Boxes and tape', 'expense_date' => '2026-08-15']);

        // ════════════════════════════════════════════
        // SUMMARY OF WHAT WE SEEDED
        // ════════════════════════════════════════════
        // Products: 2 (Earbuds, Cable)
        // Suppliers: 2 (Rajesh, Patel)
        // Platforms: 2 (Amazon, Flipkart)
        // Batch Orders: 3
        //   - #1: 50 Earbuds from Rajesh @ ₹280 = ₹14,000
        //   - #2: 30 Cables from Rajesh @ ₹45 = ₹1,350
        //   - #3: 20 Earbuds from Patel @ ₹300 = ₹6,000
        // Total Invested: ₹21,350
        // Stock: Earbuds 70, Cables 30
        // General Expenses: ₹7,000
        //
        // NO orders yet — user creates orders via the UI
        // This lets us test the full flow step by step
    }
}
