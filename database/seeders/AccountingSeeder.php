<?php

namespace Database\Seeders;

use App\Models\GeneralExpense;
use App\Models\Platform;
use App\Models\PlatformPayment;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $amazon = Platform::where('slug', 'amazon')->first();
        $flipkart = Platform::where('slug', 'flipkart')->first();
        $meesho = Platform::where('slug', 'meesho')->first();

        if (!$amazon || !$flipkart || !$meesho) return;

        // --- Platform Payments (irregular payouts) ---
        $payments = [
            // Amazon payouts
            ['platform_id' => $amazon->id, 'amount' => 5200, 'payment_date' => now()->subDays(20), 'notes' => 'Weekly settlement — Amazon'],
            ['platform_id' => $amazon->id, 'amount' => 12800, 'payment_date' => now()->subDays(13), 'notes' => 'Weekly settlement — Amazon'],
            ['platform_id' => $amazon->id, 'amount' => 3400, 'payment_date' => now()->subDays(6), 'notes' => 'Partial payout — Amazon'],

            // Flipkart payouts
            ['platform_id' => $flipkart->id, 'amount' => 7500, 'payment_date' => now()->subDays(18), 'notes' => 'Bi-weekly payout — Flipkart'],
            ['platform_id' => $flipkart->id, 'amount' => 9200, 'payment_date' => now()->subDays(8), 'notes' => 'Bi-weekly payout — Flipkart'],

            // Meesho payouts
            ['platform_id' => $meesho->id, 'amount' => 4800, 'payment_date' => now()->subDays(15), 'notes' => 'Settlement — Meesho'],
            ['platform_id' => $meesho->id, 'amount' => 6100, 'payment_date' => now()->subDays(5), 'notes' => 'Settlement — Meesho'],
        ];

        foreach ($payments as $p) {
            PlatformPayment::create($p);
        }

        // --- General Business Expenses ---
        $expenses = [
            ['description' => 'Shop rent — August', 'amount' => 8000, 'category' => 'rent', 'expense_date' => now()->subDays(28)],
            ['description' => 'Internet + phone bill', 'amount' => 1200, 'category' => 'utilities', 'expense_date' => now()->subDays(25)],
            ['description' => 'Packaging material — boxes & tape', 'amount' => 2500, 'category' => 'packaging', 'expense_date' => now()->subDays(20)],
            ['description' => 'Courier bag rolls (200 pcs)', 'amount' => 1800, 'category' => 'packaging', 'expense_date' => now()->subDays(15)],
            ['description' => 'Canva Pro subscription', 'amount' => 4000, 'category' => 'subscription', 'expense_date' => now()->subDays(12)],
            ['description' => 'Bubble wrap roll (large)', 'amount' => 650, 'category' => 'packaging', 'expense_date' => now()->subDays(5)],
        ];

        foreach ($expenses as $expense) {
            GeneralExpense::create($expense);
        }
    }
}
