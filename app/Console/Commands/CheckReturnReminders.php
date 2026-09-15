<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Order;
use Illuminate\Console\Command;

class CheckReturnReminders extends Command
{
    protected $signature = 'selling-hub:check-return-reminders';

    protected $description = 'Generate alerts for orders that need return status check';

    public function handle(): int
    {
        $orders = Order::needsReminder()->with(['items.product', 'platform'])->get();

        if ($orders->isEmpty()) {
            $this->info('No orders need return checking right now.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($orders as $order) {
            $daysSince = $order->shipped_at ? $order->shipped_at->diffInDays(now()) : 0;
            $productNames = $order->items->pluck('product.name')->filter()->implode(', ');

            // Don't create duplicate alerts for the same order
            $exists = Alert::where('type', Alert::TYPE_RETURN_REMINDER)
                ->where('entity_type', 'order')
                ->where('entity_id', $order->id)
                ->where('is_read', false)
                ->exists();

            if ($exists) {
                $this->line("  ⏭  Alert already exists for {$order->order_number}");

                continue;
            }

            Alert::create([
                'type' => Alert::TYPE_RETURN_REMINDER,
                'title' => "Check return status — {$order->order_number}",
                'message' => "Order {$order->order_number} ({$productNames}) was shipped {$daysSince} days ago on ".($order->platform->name ?? 'the platform').'. Please check delivery status.',
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'is_read' => false,
            ]);

            $created++;
            $this->line("  ✅ Created alert for {$order->order_number} ({$productNames})");
        }

        $this->info("Done. Created {$created} return reminder alerts.");

        return self::SUCCESS;
    }
}
