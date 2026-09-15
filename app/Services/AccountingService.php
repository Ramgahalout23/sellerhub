<?php

namespace App\Services;

use App\Models\BatchOrderItem;
use App\Models\GeneralExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemCharge;
use App\Models\OrderItemReturn;
use App\Models\Platform;
use App\Models\PlatformPayment;
use App\Models\PlatformSettlement;
use App\Models\Product;
use App\Models\StockBatch;
use App\Repositories\AccountingRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        // Every line uses one honest basis, or a month handed to a CA is subtly wrong:
        //   sales            → the date the sale happened (orders.created_at)
        //   money out/in     → expense_date / payment_date
        //   stock bought     → the date it was purchased (batch_orders.order_date)
        // Ranges are expanded to whole days, so "to = 15 Sep" includes the 15th.
        [$from, $to] = $this->normalizeRange($from, $to);

        // ==========================================
        // 1. REVENUE (what we earned from sales)
        // ==========================================
        $successfulIds = $this->itemIds(['successful'], $from, $to);
        $pendingIds = $this->itemIds(['pending'], $from, $to);
        $missingIds = $this->itemIds(['missing'], $from, $to);
        $returnableIds = $this->itemIds(['customer_return', 'rto'], $from, $to);

        $totalRevenue = $this->itemsValue($successfulIds);
        $pendingRevenue = $this->itemsValue($pendingIds);
        $expectedRevenue = $totalRevenue + $pendingRevenue;

        $totalCharges = (float) OrderItemCharge::whereIn('order_item_id', $successfulIds)->sum('amount');

        // Expenses are counted on the date they were incurred, not when they were typed in.
        $totalExpenses = (float) GeneralExpense::query()
            ->when($from, fn ($q) => $q->where('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('expense_date', '<=', $to))
            ->sum('amount');

        // Money in is counted on payment_date for the same reason.
        $paymentQuery = PlatformPayment::query()
            ->when($from, fn ($q) => $q->where('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('payment_date', '<=', $to));

        // ==========================================
        // 2. COGS (cost of items actually sold)
        // ==========================================
        // FIFO batch trace where it exists, product.cost_price for pre-FIFO sales.
        [$fifoCogs, $fallbackCogs] = $this->cogsFor($successfulIds);
        $totalCogs = $fifoCogs + $fallbackCogs;

        // ==========================================
        // 3. LOSSES (missing items cost)
        // ==========================================
        $missingValue = $this->itemsValue($missingIds);
        [$missingFifo, $missingFallback] = $this->cogsFor($missingIds);
        $missingCost = $missingFifo + $missingFallback;

        // ==========================================
        // 4. RETURNS (actual returned quantities)
        // ==========================================
        $returnsQty = (float) OrderItemReturn::whereIn('order_item_id', $returnableIds)->sum('quantity_returned');
        $returnCharges = (float) OrderItemReturn::whereIn('order_item_id', $returnableIds)->sum('return_charges');

        // Value of returned goods at the price they were sold for (revenue reversed by returns)
        $returnsRevenue = (float) OrderItemReturn::whereIn('order_item_returns.order_item_id', $returnableIds)
            ->join('order_items', 'order_item_returns.order_item_id', '=', 'order_items.id')
            ->sum(DB::raw('order_item_returns.quantity_returned * order_items.selling_price'));

        // Returned outcomes whose quantity/condition was never filled in
        $orphanReturnQty = (int) OrderItem::whereIn('id', $returnableIds)
            ->whereDoesntHave('returnDetail')
            ->sum('quantity');

        // ==========================================
        // 5. PROFIT CALCULATION
        // ==========================================
        // Net Profit = Revenue - COGS - Charges - Return Shipping - Expenses - Missing Cost
        $grossProfit = $totalRevenue - $totalCogs;
        $netProfit = $totalRevenue - $totalCogs - $totalCharges - $returnCharges - $totalExpenses - $missingCost;

        // Expected Profit (if pending orders succeed). Pending items already hold
        // their batch links, so their COGS is a real figure rather than an estimate.
        [$pendingFifo, $pendingFallback] = $this->cogsFor($pendingIds);
        $pendingCogs = $pendingFifo + $pendingFallback;
        $expectedProfit = $expectedRevenue - $totalCogs - $pendingCogs - $totalCharges - $returnCharges - $totalExpenses - $missingCost;

        // ==========================================
        // 6. CASH FLOW (actual money in/out)
        // ==========================================
        // Stock is money out on the day it was purchased.
        $totalInvested = (float) BatchOrderItem::query()
            ->when($from || $to, fn ($q) => $q->whereHas('batchOrder', function ($b) use ($from, $to) {
                $b->when($from, fn ($inner) => $inner->where('order_date', '>=', $from))
                    ->when($to, fn ($inner) => $inner->where('order_date', '<=', $to));
            }))
            ->sum('total_cost');

        // A successful sale auto-creates a payment row, but that is revenue *booked*, not
        // money in the bank. Only manual payouts and received settlements are real cash —
        // mixing the two is what made "still owed" impossible to answer.
        $orderPayments = (float) (clone $paymentQuery)->fromOrders()->sum('amount');
        $manualPayments = (float) (clone $paymentQuery)->manual()->sum('amount');
        $settledNet = (float) PlatformSettlement::query()->received()
            ->when($from, fn ($q) => $q->where('received_on', '>=', $from))
            ->when($to, fn ($q) => $q->where('received_on', '<=', $to))
            ->sum('net_amount');

        $totalPaymentsReceived = $manualPayments + $settledNet;

        $cashPosition = $totalPaymentsReceived - $totalInvested - $totalExpenses;

        // Inventory value (unsold stock at cost) — a snapshot "as of now", so it is
        // deliberately not date-filtered.
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
            'returns_revenue' => $returnsRevenue,
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
            'settled_net' => $settledNet,
            'cash_position' => $cashPosition,

            // Inventory
            'inventory_value' => $inventoryValue,

            // Balancing
            'pending_payments' => $expectedRevenue - $totalPaymentsReceived,

            'from' => $from,
            'to' => $to,
        ];
    }

    // --- Platform Settlements (gross / fees / net payouts) ---

    public function paginatedSettlements(int $perPage = 15, ?int $platformId = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->repo->paginatedSettlements($perPage, $platformId, $status);
    }

    public function createSettlement(array $data): PlatformSettlement
    {
        return $this->repo->createSettlement($this->fillSettlementTotals($data));
    }

    public function updateSettlement(PlatformSettlement $settlement, array $data): PlatformSettlement
    {
        return $this->repo->updateSettlement($settlement, $this->fillSettlementTotals($data));
    }

    public function deleteSettlement(PlatformSettlement $settlement): bool
    {
        return $this->repo->deleteSettlement($settlement);
    }

    /**
     * Mark a payout as banked (or undo that). received_on defaults to today.
     */
    public function receiveSettlement(PlatformSettlement $settlement, bool $received, ?string $receivedOn = null): PlatformSettlement
    {
        return $this->repo->updateSettlement($settlement, [
            'received_on' => $received ? ($receivedOn ?? now()->toDateString()) : null,
        ]);
    }

    /**
     * Fees default to zero and net is derived, so a form that only knows the payout
     * amount still records a balanced settlement.
     */
    protected function fillSettlementTotals(array $data): array
    {
        $data['fees_amount'] = $data['fees_amount'] ?? 0;

        if (! isset($data['net_amount']) || $data['net_amount'] === null || $data['net_amount'] === '') {
            $data['net_amount'] = round((float) $data['gross_amount'] - (float) $data['fees_amount'], 2);
        }

        return $data;
    }

    /**
     * What a platform should pay for a window of sales, read from the sales themselves:
     * gross = successful sale value, fees = the charges booked against those items.
     *
     * @return array<string, mixed>
     */
    public function suggestSettlement(int $platformId, ?string $from = null, ?string $to = null): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $ids = $this->successfulItemIds($platformId, $from, $to);

        $gross = $this->itemsValue($ids);
        $fees = (float) OrderItemCharge::whereIn('order_item_id', $ids)->sum('amount');

        return [
            'orders' => $ids->isEmpty() ? 0 : (int) OrderItem::whereIn('id', $ids)->distinct()->count('order_id'),
            'gross_amount' => round($gross, 2),
            'fees_amount' => round($fees, 2),
            'net_amount' => round($gross - $fees, 2),
            'period_start' => $from?->toDateString(),
            'period_end' => $to?->toDateString(),
        ];
    }

    /**
     * Per platform: what has been earned net of fees, what has actually been banked, and
     * what is still owed — with the age of the oldest unpaid payout.
     *
     * Deliberately all-time: money owed is a running balance, not a monthly figure.
     * Uses a fixed set of grouped aggregates, so the cost does not grow with the number
     * of platforms.
     *
     * @return array{platforms: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function settlementOverview(): array
    {
        $earned = $this->platformEarnedTotals();
        $fees = $this->platformFeesTotals();
        $manual = $this->bankedManualTotals();
        $settled = $this->bankedSettlementTotals();
        $pending = $this->pendingSettlementTotals();

        $rows = [];

        foreach (Platform::query()->orderBy('name')->get() as $platform) {
            $gross = (float) ($earned[$platform->id]->gross ?? 0);
            $platformFees = (float) ($fees[$platform->id]->fees ?? 0);
            $expectedNet = round($gross - $platformFees, 2);
            $banked = round((float) ($manual[$platform->id]->total ?? 0) + (float) ($settled[$platform->id]->total ?? 0), 2);
            $pendingRow = $pending[$platform->id] ?? null;

            $due = $pendingRow->oldest_due ?? null;
            $age = ($due && Carbon::parse($due)->startOfDay()->lessThan(Carbon::today()))
                ? (int) abs(Carbon::parse($due)->startOfDay()->diffInDays(Carbon::today()->startOfDay()))
                : 0;

            // Owed is whichever claim is larger: what the sales say is still due, or the
            // payouts already documented as in-flight. Taking the max avoids double-counting
            // a recorded payout against the sales it was earned from, while still counting
            // payouts for sales that predate the app.
            $pendingNet = round((float) ($pendingRow->pending_net ?? 0), 2);
            $owed = round(max($expectedNet - $banked, $pendingNet), 2);

            $rows[] = [
                'platform' => $platform,
                'orders' => (int) ($earned[$platform->id]->orders_count ?? 0),
                'gross' => round($gross, 2),
                'fees' => round($platformFees, 2),
                'expected_net' => $expectedNet,
                'banked' => $banked,
                'owed' => $owed,
                'pending_count' => (int) ($pendingRow->pending_count ?? 0),
                'pending_net' => $pendingNet,
                'oldest_due' => $due ? Carbon::parse($due) : null,
                'oldest_age_days' => $age,
            ];
        }

        usort($rows, fn ($a, $b) => $b['owed'] <=> $a['owed']);

        return [
            'platforms' => $rows,
            'totals' => [
                'gross' => round(array_sum(array_column($rows, 'gross')), 2),
                'fees' => round(array_sum(array_column($rows, 'fees')), 2),
                'expected_net' => round(array_sum(array_column($rows, 'expected_net')), 2),
                'banked' => round(array_sum(array_column($rows, 'banked')), 2),
                'owed' => round(array_sum(array_column($rows, 'owed')), 2),
                'pending_count' => (int) array_sum(array_column($rows, 'pending_count')),
            ],
        ];
    }

    /**
     * Ids of successful sale lines for a platform in a window, on live orders only.
     *
     * @return Collection<int, int>
     */
    protected function successfulItemIds(int $platformId, ?Carbon $from, ?Carbon $to): Collection
    {
        return OrderItem::query()
            ->where('status', 'successful')
            ->whereHas('order', function ($query) use ($platformId, $from, $to) {
                $query->where('platform_id', $platformId)
                    ->where('status', '!=', Order::STATUS_CANCELLED)
                    ->when($from, fn ($inner) => $inner->where('created_at', '>=', $from))
                    ->when($to, fn ($inner) => $inner->where('created_at', '<=', $to));
            })
            ->pluck('id');
    }

    /** @return array<int, object> */
    protected function platformEarnedTotals(): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.status', 'successful')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->groupBy('orders.platform_id')
            ->selectRaw('orders.platform_id')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('SUM(order_items.quantity * order_items.selling_price) as gross')
            ->get()->keyBy('platform_id')->all();
    }

    /** @return array<int, object> */
    protected function platformFeesTotals(): array
    {
        return DB::table('order_item_charges')
            ->join('order_items', 'order_items.id', '=', 'order_item_charges.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.status', 'successful')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->groupBy('orders.platform_id')
            ->selectRaw('orders.platform_id, SUM(order_item_charges.amount) as fees')
            ->get()->keyBy('platform_id')->all();
    }

    /** @return array<int, object> */
    protected function bankedManualTotals(): array
    {
        return PlatformPayment::query()->manual()
            ->groupBy('platform_id')
            ->selectRaw('platform_id, SUM(amount) as total')
            ->get()->keyBy('platform_id')->all();
    }

    /** @return array<int, object> */
    protected function bankedSettlementTotals(): array
    {
        return PlatformSettlement::query()->received()
            ->groupBy('platform_id')
            ->selectRaw('platform_id, SUM(net_amount) as total')
            ->get()->keyBy('platform_id')->all();
    }

    /** @return array<int, object> */
    protected function pendingSettlementTotals(): array
    {
        return PlatformSettlement::query()->pending()
            ->groupBy('platform_id')
            ->selectRaw('platform_id, COUNT(*) as pending_count, SUM(net_amount) as pending_net')
            ->selectRaw('MIN(COALESCE(expected_on, period_end)) as oldest_due')
            ->get()->keyBy('platform_id')->all();
    }

    /**
     * Expand a user-supplied range into whole days so the end date is inclusive.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function normalizeRange(?string $from, ?string $to): array
    {
        return [
            $from ? Carbon::parse($from)->startOfDay() : null,
            $to ? Carbon::parse($to)->endOfDay() : null,
        ];
    }

    /**
     * Line-item ids for the given outcome states, limited to sales that happened in the
     * period. Items on a cancelled order are never included — that sale didn't happen.
     *
     * @param  array<int, string>  $statuses
     * @return Collection<int, int>
     */
    protected function itemIds(array $statuses, ?Carbon $from, ?Carbon $to): Collection
    {
        return OrderItem::query()
            ->whereIn('status', $statuses)
            ->whereHas('order', function ($query) use ($from, $to) {
                $query->where('status', '!=', Order::STATUS_CANCELLED)
                    ->when($from, fn ($inner) => $inner->where('created_at', '>=', $from))
                    ->when($to, fn ($inner) => $inner->where('created_at', '<=', $to));
            })
            ->pluck('id');
    }

    /**
     * Gross sale value (quantity × price) of the given line items.
     *
     * @param  Collection<int, int>  $ids
     */
    protected function itemsValue(Collection $ids): float
    {
        if ($ids->isEmpty()) {
            return 0.0;
        }

        return (float) OrderItem::whereIn('id', $ids)->sum(DB::raw('quantity * selling_price'));
    }

    /**
     * Cost of goods for the given line items: the FIFO batch cost where the sale was
     * traced to its batches, plus a product.cost_price fallback for pre-FIFO sales.
     *
     * @param  Collection<int, int>  $ids
     * @return array{0: float, 1: float} [fifo cost, fallback cost]
     */
    protected function cogsFor(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [0.0, 0.0];
        }

        $fifo = (float) OrderItemBatch::whereIn('order_item_id', $ids)
            ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
            ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));

        $traced = OrderItemBatch::whereIn('order_item_id', $ids)->distinct()->pluck('order_item_id');
        $untraced = $ids->diff($traced);

        $fallback = 0.0;
        if ($untraced->isNotEmpty()) {
            $fallback = (float) OrderItem::whereIn('order_items.id', $untraced)
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->sum(DB::raw('order_items.quantity * products.cost_price'));
        }

        return [$fifo, $fallback];
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
