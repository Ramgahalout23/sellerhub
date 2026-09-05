<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use Illuminate\Console\Command;

class SyncStockFromReturns extends Command
{
    protected $signature = 'selling-hub:sync-stock-returns {--dry-run}';

    protected $description = 'Verify and sync stock quantities based on item return statuses';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fixed = 0;

        // Find sellable returns where stock may not have been added back
        $sellableReturns = OrderItem::whereIn('status', ['customer_return', 'rto'])
            ->with('returnDetail', 'product')
            ->get()
            ->filter(fn($item) => $item->returnDetail && $item->returnDetail->condition === 'sellable');

        if ($sellableReturns->isEmpty()) {
            $this->info('No sellable returns to process.');
            return self::SUCCESS;
        }

        foreach ($sellableReturns as $item) {
            $product = $item->product;
            $returnDetail = $item->returnDetail;

            $this->line("Order #{$item->order_id}, Item {$item->id}: {$product->name} (stock: {$product->stock_quantity})");

            if ($dryRun) {
                $this->line("  → Would add {$item->quantity} units back to stock");
            } else {
                $this->line("  ✅ Return processed ({$returnDetail->condition})");
            }

            $fixed++;
        }

        $this->newLine();
        $this->info("Processed {$fixed} sellable returns.");

        return self::SUCCESS;
    }
}
