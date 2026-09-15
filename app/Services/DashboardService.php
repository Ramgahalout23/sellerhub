<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemReturn;
use App\Models\StockBatch;
use App\Repositories\AccountingRepository;
use App\Repositories\AlertRepository;
use App\Repositories\BatchOrderRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected ProductRepository $productRepo,
        protected OrderRepository $orderRepo,
        protected BatchOrderRepository $batchRepo,
        protected AccountingRepository $accountingRepo,
        protected AlertRepository $alertRepo
    ) {}

    /**
     * Line items in the given outcomes, excluding orders that were cancelled —
     * a cancelled sale must not inflate revenue, stock or return figures.
     *
     * @return Builder<OrderItem>
     */
    private function liveItems(string ...$statuses)
    {
        return OrderItem::query()
            ->whereIn('status', $statuses)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', Order::STATUS_CANCELLED));
    }

    public function summary(): array
    {
        $totalCharges = $this->orderRepo->totalCharges();
        $totalInvested = $this->batchRepo->totalInvested();
        $totalPayments = $this->accountingRepo->totalPayments();
        $totalExpenses = $this->accountingRepo->totalExpenses();

        // Revenue — only successful items on orders that actually happened
        $successfulIds = $this->liveItems('successful')->pluck('id');
        $totalRevenue = 0;
        $totalCogs = 0;
        $pendingRevenue = 0;
        $missingCost = 0;

        if ($successfulIds->isNotEmpty()) {
            $totalRevenue = (float) OrderItem::whereIn('id', $successfulIds)
                ->sum(DB::raw('quantity * selling_price'));

            // COGS — FIFO traced
            $totalCogs = (float) OrderItemBatch::whereIn('order_item_id', $successfulIds)
                ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
                ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));

            // Fallback COGS for pre-FIFO items
            $withBatchIds = OrderItemBatch::whereIn('order_item_id', $successfulIds)
                ->distinct('order_item_id')->pluck('order_item_id');
            $withoutBatchIds = $successfulIds->diff($withBatchIds);
            if ($withoutBatchIds->isNotEmpty()) {
                $totalCogs += (float) OrderItem::whereIn('order_items.id', $withoutBatchIds)
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->sum(DB::raw('order_items.quantity * products.cost_price'));
            }
        }

        // Pending revenue
        $pendingRevenue = (float) $this->liveItems('pending')
            ->sum(DB::raw('quantity * selling_price'));

        // Missing cost
        $missingIds = $this->liveItems('missing')->pluck('id');
        if ($missingIds->isNotEmpty()) {
            $missingCost = (float) OrderItemBatch::whereIn('order_item_id', $missingIds)
                ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
                ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));
        }

        // Return charges
        $returnCharges = (float) OrderItemReturn::whereHas('orderItem', fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', '!=', Order::STATUS_CANCELLED)))->sum('return_charges');

        // Inventory value
        $inventoryValue = (float) StockBatch::sum(DB::raw('remaining_quantity * unit_cost'));

        // Correct profit = Revenue - COGS - Charges - Return Shipping - Expenses - Missing
        $netProfit = $totalRevenue - $totalCogs - $totalCharges - $returnCharges - $totalExpenses - $missingCost;

        return [
            'total_products' => $this->productRepo->count(),
            'low_stock_count' => $this->productRepo->countLowStock(),
            'total_orders' => $this->orderRepo->count(),
            'cancelled_orders' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'pending_orders' => $this->liveItems('pending')->count(),
            'successful_orders' => $this->liveItems('successful')->count(),
            'returned_orders' => $this->liveItems('customer_return', 'rto')->count(),
            'missing_orders' => $this->liveItems('missing')->count(),

            'total_revenue' => $totalRevenue,
            'pending_revenue' => $pendingRevenue,
            'expected_revenue' => $totalRevenue + $pendingRevenue,
            'total_invested' => $totalInvested,
            'total_cogs' => $totalCogs,
            'total_charges' => $totalCharges,
            'return_charges' => $returnCharges,
            'total_expenses' => $totalExpenses,
            'missing_cost' => $missingCost,
            'inventory_value' => $inventoryValue,
            'total_payments' => $totalPayments,

            'net_profit' => $netProfit,
            'cash_position' => $totalPayments - $totalInvested - $totalExpenses,

            'unread_alerts' => $this->alertRepo->unreadCount(),
            'low_stock_products' => $this->productRepo->lowStock(),
            'orders_needing_reminder' => $this->orderRepo->needsReminder(),
        ];
    }
}
