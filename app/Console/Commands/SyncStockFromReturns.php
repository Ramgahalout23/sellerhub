<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStockFromReturns extends Command
{
    protected $signature = 'selling-hub:sync-stock-returns {--dry-run}';

    protected $description = 'Verify that each product\'s stock matches its stock batches and repair drift';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // The invariant: products.stock_quantity == SUM(stock_batches.remaining_quantity)
        // for every product that has at least one stock batch. Returns/orders keep
        // both sides in sync inside a transaction; this command catches any drift
        // caused by manual edits or interrupted jobs.
        $batchTotals = StockBatch::query()
            ->select('product_id', DB::raw('SUM(remaining_quantity) as remaining'))
            ->groupBy('product_id')
            ->pluck('remaining', 'product_id');

        if ($batchTotals->isEmpty()) {
            $this->info('No stock batches to check.');

            return self::SUCCESS;
        }

        $products = Product::whereIn('id', $batchTotals->keys())->get();
        $drift = [];

        foreach ($products as $product) {
            $expected = (int) $batchTotals[$product->id];
            if ((int) $product->stock_quantity !== $expected) {
                $drift[] = ['product' => $product, 'expected' => $expected];
            }
        }

        if (empty($drift)) {
            $this->info("All {$products->count()} products match their stock batches. Nothing to fix.");

            return self::SUCCESS;
        }

        foreach ($drift as $row) {
            /** @var Product $product */
            $product = $row['product'];
            $expected = $row['expected'];
            $actual = (int) $product->stock_quantity;

            $this->line(sprintf(
                '  %s %s: recorded %d, expected %d (%+d)',
                $dryRun ? '⚠' : '✏',
                $product->sku,
                $actual,
                $expected,
                $expected - $actual,
            ));

            if (! $dryRun) {
                $product->update(['stock_quantity' => $expected]);
            }
        }

        if ($dryRun) {
            $this->warn(count($drift).' product(s) drifted. Re-run without --dry-run to repair.');
        } else {
            $this->info('Repaired '.count($drift).' product(s).');
        }

        return self::SUCCESS;
    }
}
