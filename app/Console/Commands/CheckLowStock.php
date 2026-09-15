<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Product;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'selling-hub:check-low-stock';

    protected $description = 'Generate alerts for products below reorder threshold';

    public function handle(): int
    {
        $lowStockProducts = Product::active()->lowStock()->get();

        if ($lowStockProducts->isEmpty()) {
            $this->info('All products are well stocked.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($lowStockProducts as $product) {
            // Don't create duplicate alerts for the same product
            $exists = Alert::where('type', Alert::TYPE_LOW_STOCK)
                ->where('entity_type', 'product')
                ->where('entity_id', $product->id)
                ->where('is_read', false)
                ->exists();

            if ($exists) {
                $this->line("  ⏭  Alert already exists for {$product->sku}");

                continue;
            }

            $supplierNames = $product->suppliers->pluck('name')->implode(', ');
            $stockStatus = $product->stock_quantity === 0 ? 'Out of stock' : "Only {$product->stock_quantity} units left";

            $message = "SKU {$product->sku} ({$product->name}) — {$stockStatus} (reorder threshold: {$product->reorder_threshold}).";

            if ($supplierNames) {
                $message .= " Reorder from: {$supplierNames}.";
            }

            Alert::create([
                'type' => Alert::TYPE_LOW_STOCK,
                'title' => ($product->stock_quantity === 0 ? 'Out of stock' : 'Low stock')." — {$product->name}",
                'message' => $message,
                'entity_type' => 'product',
                'entity_id' => $product->id,
                'is_read' => false,
            ]);

            $created++;
            $status = $product->stock_quantity === 0 ? '🔴' : '🟡';
            $this->line("  {$status} {$product->sku} — {$product->name} (stock: {$product->stock_quantity})");
        }

        $this->info("Done. Created {$created} low stock alerts.");

        return self::SUCCESS;
    }
}
