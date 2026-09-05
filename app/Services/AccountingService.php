<?php

namespace App\Services;

use App\Models\GeneralExpense;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemCharge;
use App\Models\OrderItemReturn;
use App\Models\PlatformPayment;
use App\Models\Product;
use App\Models\StockBatch;
use App\Repositories\AccountingRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function __construct(
        protected AccountingRepository $repo
    ) {}

    // --- Platform Payments ---

    public function paginatedPayments(int $perPage = 15, ?int $platformId = null): LengthAwarePaginator
    {
        return $this->repo->paginatedPayments($perPage, $platformId);
    }

    public function findPayment(int $id): PlatformPayment
    {
        return $this->repo->findPayment($id);
    }

    public function createPayment(array $data): PlatformPayment
    {
        return $this->repo->createPayment($data);
    }

    public function updatePayment(PlatformPayment $payment, array $data): PlatformPayment
    {
        return $this->repo->updatePayment($payment, $data);
    }

    public function deletePayment(PlatformPayment $payment): bool
    {
        return $this->repo->deletePayment($payment);
    }

    // --- General Expenses ---

    public function paginatedExpenses(int $perPage = 15, ?string $category = null): LengthAwarePaginator
    {
        return $this->repo->paginatedExpenses($perPage, $category);
    }

    public function findExpense(int $id): GeneralExpense
    {
        return $this->repo->findExpense($id);
    }

    public function createExpense(array $data): GeneralExpense
    {
        return $this->repo->createExpense($data);
    }

    public function updateExpense(GeneralExpense $expense, array $data): GeneralExpense
    {
        return $this->repo->updateExpense($expense, $data);
    }

    public function deleteExpense(GeneralExpense $expense): bool
    {
        return $this->repo->deleteExpense($expense);
    }

    // --- Ledger / Profit-Loss Summary ---

    public function ledgerSummary(?string $from = null, ?string $to = null): array
    {
        // ==========================================
        // 1. REVENUE (what we earned from sales)
        // ==========================================
        $successfulIds = OrderItem::where('status', 'successful')->pluck('id');
        $orderItemQuery = OrderItem::whereIn('id', $successfulIds);
        $pendingQuery = OrderItem::where('status', 'pending');
        $chargeQuery = OrderItemCharge::whereIn('order_item_id', $successfulIds);
        $paymentQuery = PlatformPayment::query();
        $expenseQuery = GeneralExpense::query();

        if ($from && $to) {
            $orderItemQuery->whereHas('order', fn($q) => $q->whereBetween('created_at', [$from, $to]));
            $pendingQuery->whereHas('order', fn($q) => $q->whereBetween('created_at', [$from, $to]));
            $chargeQuery->whereHas('orderItem', fn($q) => $q->whereHas('order', fn($q2) => $q2->whereBetween('created_at', [$from, $to])));
            $paymentQuery->whereBetween('created_at', [$from, $to]);
            $expenseQuery->whereBetween('created_at', [$from, $to]);
        }

        $totalRevenue = (float) $orderItemQuery->sum(DB::raw('quantity * selling_price'));
        $totalCharges = (float) $chargeQuery->sum('amount');
        $totalPaymentsReceived = (float) $paymentQuery->sum('amount');
        $totalExpenses = (float) $expenseQuery->sum('amount');

        $pendingRevenue = (float) $pendingQuery->sum(DB::raw('quantity * selling_price'));
        $expectedRevenue = $totalRevenue + $pendingRevenue;

        // ==========================================
        // 2. COGS (cost of items actually sold)
        // ==========================================
        // For items with FIFO batch trace → use actual batch cost
        // For pre-FIFO items → fallback to product.cost_price

        $fifoCogs = 0;
        $fallbackCogs = 0;

        if ($successfulIds->isNotEmpty()) {
            // FIFO-traced COGS
            $fifoCogs = (float) OrderItemBatch::whereIn('order_item_id', $successfulIds)
                ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
                ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));

            // Pre-FIFO items (no batch trace) → use product.cost_price
            $withBatchIds = OrderItemBatch::whereIn('order_item_id', $successfulIds)
                ->distinct('order_item_id')
                ->pluck('order_item_id');
            $withoutBatchIds = $successfulIds->diff($withBatchIds);

            if ($withoutBatchIds->isNotEmpty()) {
                $fallbackCogs = (float) OrderItem::whereIn('order_items.id', $withoutBatchIds)
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->sum(DB::raw('order_items.quantity * products.cost_price'));
            }
        }

        $totalCogs = $fifoCogs + $fallbackCogs;

        // ==========================================
        // 3. LOSSES (missing items cost)
        // ==========================================
        $missingQuery = OrderItem::where('status', 'missing');
        if ($from && $to) {
            $missingQuery->whereHas('order', fn($q) => $q->whereBetween('created_at', [$from, $to]));
        }
        $missingIds = $missingQuery->pluck('id');
        $missingValue = (float) $missingQuery->sum(DB::raw('quantity * selling_price'));

        $missingCost = 0;
        if ($missingIds->isNotEmpty()) {
            // FIFO-traced missing cost
            $missingCost = (float) OrderItemBatch::whereIn('order_item_id', $missingIds)
                ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
                ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));

            // Pre-FIFO missing items → fallback
            $missingWithBatch = OrderItemBatch::whereIn('order_item_id', $missingIds)
                ->distinct('order_item_id')->pluck('order_item_id');
            $missingWithout = $missingIds->diff($missingWithBatch);
            if ($missingWithout->isNotEmpty()) {
                $missingCost += (float) OrderItem::whereIn('order_items.id', $missingWithout)
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->sum(DB::raw('order_items.quantity * products.cost_price'));
            }
        }

        // ==========================================
        // 4. RETURNS (actual returned quantities)
        // ==========================================
        $returnItemQuery = OrderItemReturn::query()
            ->whereHas('orderItem', fn($q) => $q->whereIn('status', ['customer_return', 'rto']));
        if ($from && $to) {
            $returnItemQuery->whereHas('orderItem.order', fn($q) => $q->whereBetween('created_at', [$from, $to]));
        }
        $returnsQty = (float) $returnItemQuery->sum('quantity_returned');
        $returnCharges = (float) $returnItemQuery->sum('return_charges');
        $orphanReturnQty = OrderItem::whereIn('status', ['customer_return', 'rto'])
            ->whereDoesntHave('returnDetail')
            ->sum('quantity');

        // ==========================================
        // 5. PROFIT CALCULATION
        // ==========================================
        // Net Profit = Revenue - COGS - Charges - Return Shipping - Expenses - Missing Cost
        $grossProfit = $totalRevenue - $totalCogs;
        $netProfit = $totalRevenue - $totalCogs - $totalCharges - $returnCharges - $totalExpenses - $missingCost;

        // Expected Profit (if pending orders succeed)
        // Pending COGS: estimate using product.cost_price
        $pendingIds = OrderItem::where('status', 'pending')->pluck('id');
        $pendingCogs = 0;
        if ($pendingIds->isNotEmpty()) {
            $pendingCogs = (float) OrderItem::whereIn('order_items.id', $pendingIds)
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->sum(DB::raw('order_items.quantity * products.cost_price'));
        }
        $expectedProfit = $expectedRevenue - $totalCogs - $pendingCogs - $totalCharges - $returnCharges - $totalExpenses - $missingCost;

        // ==========================================
        // 6. CASH FLOW (actual money in/out)
        // ==========================================
        $totalInvested = (float) \App\Models\BatchOrderItem::sum('total_cost');
        if ($from && $to) {
            $totalInvested = (float) \App\Models\BatchOrderItem::whereHas('batchOrder', function ($q) use ($from, $to) {
                $q->whereBetween('order_date', [$from, $to]);
            })->sum('total_cost');
        }

        $orderPaymentsQuery = PlatformPayment::fromOrders();
        $manualPaymentsQuery = PlatformPayment::manual();
        if ($from && $to) {
            $orderPaymentsQuery->whereBetween('created_at', [$from, $to]);
            $manualPaymentsQuery->whereBetween('created_at', [$from, $to]);
        }
        $orderPayments = (float) $orderPaymentsQuery->sum('amount');
        $manualPayments = (float) $manualPaymentsQuery->sum('amount');

        $cashPosition = $totalPaymentsReceived - $totalInvested - $totalExpenses;

        // Inventory value (unsold stock at cost)
        $inventoryValue = (float) StockBatch::sum(DB::raw('remaining_quantity * unit_cost'));

        return [
            // Revenue
            'total_revenue' => $totalRevenue,
            'pending_revenue' => $pendingRevenue,
            'expected_revenue' => $expectedRevenue,

            // Costs
            'total_cogs' => $totalCogs,
            'fifo_cogs' => $fifoCogs,
            'fallback_cogs' => $fallbackCogs,
            'pending_cogs' => $pendingCogs,
            'total_charges' => $totalCharges,
            'return_charges' => $returnCharges,
            'total_expenses' => $totalExpenses,
            'missing_cost' => $missingCost,
            'missing_value' => $missingValue,

            // Returns
            'returns_qty' => $returnsQty,
            'orphan_returns' => $orphanReturnQty,

            // Profit
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'expected_profit' => $expectedProfit,

            // Cash Flow
            'total_invested' => $totalInvested,
            'total_payments_received' => $totalPaymentsReceived,
            'order_payments' => $orderPayments,
            'manual_payments' => $manualPayments,
            'cash_position' => $cashPosition,

            // Inventory
            'inventory_value' => $inventoryValue,

            // Balancing
            'pending_payments' => $expectedRevenue - $totalPaymentsReceived,

            'from' => $from,
            'to' => $to,
        ];
    }

    public function productProfitability(int $productId): array
    {
        $product = Product::with(['orderItems.order.platform', 'batchOrderItems'])->findOrFail($productId);

        $totalInvested = $product->total_invested;
        $totalReceived = $product->total_received;
        $totalCharges = $product->total_charges;

        return [
            'product' => $product,
            'total_invested' => $totalInvested,
            'total_received' => $totalReceived,
            'total_charges' => $totalCharges,
            'net_profit' => $totalReceived - $totalInvested - $totalCharges,
            'pending_payment' => $totalReceived - $this->repo->totalPayments(),
        ];
    }
}
