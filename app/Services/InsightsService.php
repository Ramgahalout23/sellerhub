<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemReturn;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsightsService
{
    public function supplierComparisonForProduct(int $productId): array
    {
        $product = Product::findOrFail($productId);
        $batches = StockBatch::where('product_id', $productId)->with('supplier')->get();
        $supplierGroups = $batches->groupBy('supplier_id');
        $results = [];

        foreach ($supplierGroups as $supplierId => $supplierBatches) {
            $supplier = $supplierBatches->first()->supplier;
            if (! $supplier) {
                continue;
            }

            $batchIds = $supplierBatches->pluck('id');
            $originalQty = $supplierBatches->sum('original_quantity');
            $avgCost = $supplierBatches->avg('unit_cost');

            $orderItemIds = OrderItemBatch::whereIn('stock_batch_id', $batchIds)->pluck('order_item_id');

            $soldQty = OrderItem::whereIn('id', $orderItemIds)->where('status', 'successful')->sum('quantity');
            $revenue = OrderItem::whereIn('id', $orderItemIds)->where('status', 'successful')->sum(DB::raw('quantity * selling_price'));

            $returnableItemIds = OrderItem::whereIn('id', $orderItemIds)->whereIn('status', ['customer_return', 'rto'])->pluck('id');
            $returnedQty = OrderItemReturn::whereIn('order_item_id', $returnableItemIds)->sum('quantity_returned');
            $rtoQty = OrderItem::whereIn('id', $orderItemIds)->where('status', 'rto')->sum('quantity');
            $missingQty = OrderItem::whereIn('id', $orderItemIds)->where('status', 'missing')->sum('quantity');

            $cogs = OrderItemBatch::whereIn('stock_batch_id', $batchIds)
                ->whereHas('orderItem', fn ($q) => $q->where('status', 'successful'))
                ->sum(DB::raw('quantity_deducted * (SELECT unit_cost FROM stock_batches WHERE id = stock_batch_id)'));

            // Only count charges on successful items (not pending/missing)
            $successfulItemIds = OrderItem::whereIn('id', $orderItemIds)->where('status', 'successful')->pluck('id');
            $charges = DB::table('order_item_charges')->whereIn('order_item_id', $successfulItemIds)->sum('amount');
            $netProfit = $revenue - $cogs - $charges;
            $dispatched = $soldQty + $returnedQty + $missingQty;
            $returnRate = $dispatched > 0 ? round(($returnedQty / $dispatched) * 100, 1) : 0;

            $results[] = [
                'supplier' => $supplier, 'original_qty' => (int) $originalQty,
                'sold_qty' => (int) $soldQty, 'returned_qty' => (int) $returnedQty,
                'rto_qty' => (int) $rtoQty, 'missing_qty' => (int) $missingQty,
                'return_rate' => $returnRate, 'avg_cost' => round($avgCost, 2),
                'revenue' => round($revenue, 2), 'cogs' => round($cogs, 2),
                'charges' => round($charges, 2), 'net_profit' => round($netProfit, 2),
                'batch_count' => $supplierBatches->count(),
            ];
        }

        usort($results, fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);

        return ['product' => $product, 'suppliers' => $results];
    }

    public function topProfitableProducts(): array
    {
        return $this->getProductRankings('profit_desc');
    }

    public function topLossMakingProducts(): array
    {
        return $this->getProductRankings('loss_desc');
    }

    public function lowestReturnRatioProducts(): array
    {
        return $this->getProductRankings('return_ratio_asc');
    }

    public function healthScoreRankings(): array
    {
        $products = $this->getProductRankings('profit_desc');
        if (empty($products)) {
            return [];
        }

        $maxProfit = max(array_map(fn ($p) => abs($p['net_profit']), $products));
        if ($maxProfit == 0) {
            $maxProfit = 1;
        }

        foreach ($products as &$p) {
            $profitScore = $p['net_profit'] >= 0
                ? 50 + (50 * ($p['net_profit'] / $maxProfit))
                : 50 - (50 * (abs($p['net_profit']) / $maxProfit));
            $returnScore = max(0, 100 - ($p['return_rate'] * 2));
            $p['health_score'] = round(($profitScore * 0.6) + ($returnScore * 0.4), 1);
            $p['profit_score'] = round($profitScore, 1);
            $p['return_score'] = round($returnScore, 1);
        }
        unset($p);

        usort($products, fn ($a, $b) => $b['health_score'] <=> $a['health_score']);
        foreach ($products as $i => &$p) {
            $p['rank'] = $i + 1;
        }
        unset($p);

        return $products;
    }

    /**
     * Time trend: iterate FORWARD from oldest month to current month
     * to avoid Carbon subMonths edge cases with day-31 dates.
     */
    public function timeTrend(string $type, ?int $entityId = null, int $months = 6): array
    {
        $now = Carbon::now();

        // Build month list by going forward from (now - months) to now
        $startMonth = $now->copy()->subMonths($months)->startOfMonth();
        $endMonth = $now->copy()->startOfMonth();
        $monthWindows = [];
        $cursor = $startMonth->copy();

        while ($cursor->lte($endMonth)) {
            $monthWindows[] = [
                'start' => $cursor->copy()->startOfMonth(),
                'end' => $cursor->copy()->endOfMonth(),
                'label' => $cursor->format('M Y'),
            ];
            $cursor->addMonth();
        }

        $results = [];
        foreach ($monthWindows as $mw) {
            $itemQuery = OrderItem::whereBetween('created_at', [$mw['start'], $mw['end']])
                ->where('status', '!=', 'pending');

            if ($type === 'product' && $entityId) {
                $itemQuery->where('product_id', $entityId);
            }
            if ($type === 'supplier' && $entityId) {
                $productIds = StockBatch::where('supplier_id', $entityId)->pluck('product_id')->unique();
                $itemQuery->whereIn('product_id', $productIds);
                $supplierBatchIds = StockBatch::where('supplier_id', $entityId)->pluck('id');
                $itemQuery->whereHas('batches', function ($q) use ($supplierBatchIds) {
                    $q->whereIn('stock_batch_id', $supplierBatchIds);
                });
            }

            $itemIds = $itemQuery->pluck('id');

            $revenue = OrderItem::whereIn('id', $itemIds)->where('status', 'successful')
                ->sum(DB::raw('quantity * selling_price'));

            $cogs = DB::table('order_item_batches')
                ->whereIn('order_item_id', $itemIds)
                ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
                ->join('order_items', 'order_item_batches.order_item_id', '=', 'order_items.id')
                ->where('order_items.status', 'successful')
                ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));

            $charges = DB::table('order_item_charges')->whereIn('order_item_id', $itemIds)->sum('amount');
            $returnedQty = OrderItemReturn::whereIn('order_item_id', $itemIds)->sum('quantity_returned');
            $missingQty = OrderItem::whereIn('id', $itemIds)->where('status', 'missing')->sum('quantity');
            $soldQty = OrderItem::whereIn('id', $itemIds)->where('status', 'successful')->sum('quantity');
            $dispatched = OrderItem::whereIn('id', $itemIds)->sum('quantity');
            $returnRate = $dispatched > 0 ? round(($returnedQty / $dispatched) * 100, 1) : 0;
            $netProfit = $revenue - $cogs - $charges;

            $results[] = [
                'month' => $mw['label'], 'revenue' => round($revenue, 2),
                'cogs' => round($cogs, 2), 'charges' => round($charges, 2),
                'net_profit' => round($netProfit, 2), 'sold_qty' => (int) $soldQty,
                'returned_qty' => (int) $returnedQty, 'missing_qty' => (int) $missingQty,
                'return_rate' => $returnRate,
            ];
        }

        return $results;
    }

    /**
     * Which marketplace actually makes money, after fees and returns?
     *
     * Uses a fixed set of grouped aggregates (one query per metric family) so the cost
     * is the same whether you sell on 1 platform or 10 — never a query per platform.
     *
     * @return array<int, array<string, mixed>>
     */
    public function platformComparison(?int $months = null): array
    {
        $from = $months ? Carbon::now()->subMonths($months)->startOfMonth() : null;

        $totals = $this->platformItemTotals($from);
        $fees = $this->platformColumn($from, 'order_item_charges', 'SUM(order_item_charges.amount)', 'fees', function ($query) {
            return $query->where('order_items.status', 'successful');
        });
        $cogs = $this->platformColumn($from, 'order_item_batches', 'SUM(order_item_batches.quantity_deducted * stock_batches.unit_cost)', 'cogs', function ($query) {
            return $query->where('order_items.status', 'successful');
        }, joins: [['stock_batches', 'stock_batches.id', 'order_item_batches.stock_batch_id']]);
        $returns = $this->platformColumn($from, 'order_item_returns', 'SUM(order_item_returns.quantity_returned)', 'returned_qty');

        $results = [];

        foreach (Platform::query()->orderBy('name')->get() as $platform) {
            $row = $totals[$platform->id] ?? null;

            $soldQty = (int) ($row->sold_qty ?? 0);
            $revenue = (float) ($row->revenue ?? 0);
            $platformFees = (float) ($fees[$platform->id]->fees ?? 0);
            $platformCogs = (float) ($cogs[$platform->id]->cogs ?? 0);

            // Batches weren't always recorded, so fall back to the product's cost price.
            if ($platformCogs <= 0 && $soldQty > 0) {
                $platformCogs = (float) ($row->estimated_cogs ?? 0);
            }

            $returnedQty = (int) ($returns[$platform->id]->returned_qty ?? 0);
            $dispatched = (int) ($row->dispatched_qty ?? 0);
            $orders = (int) ($row->orders_count ?? 0);
            $netProfit = $revenue - $platformCogs - $platformFees;

            $results[] = [
                'platform' => $platform,
                'orders' => $orders,
                'sold_qty' => $soldQty,
                'dispatched_qty' => $dispatched,
                'revenue' => round($revenue, 2),
                'cogs' => round($platformCogs, 2),
                'fees' => round($platformFees, 2),
                'net_profit' => round($netProfit, 2),
                'returned_qty' => $returnedQty,
                'return_rate' => $dispatched > 0 ? round($returnedQty / $dispatched * 100, 1) : 0,
                'rto_qty' => (int) ($row->rto_qty ?? 0),
                'missing_qty' => (int) ($row->missing_qty ?? 0),
                'margin' => $revenue > 0 ? round($netProfit / $revenue * 100, 1) : 0,
                'profit_per_order' => $orders > 0 ? round($netProfit / $orders, 2) : 0,
                'has_sales' => $orders > 0,
            ];
        }

        usort($results, fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);

        return $results;
    }

    /**
     * Order/item/sales totals grouped by platform (one query).
     */
    private function platformItemTotals(?Carbon $from): array
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->groupBy('orders.platform_id')
            ->selectRaw('orders.platform_id')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw("SUM(CASE WHEN order_items.status = 'successful' THEN order_items.quantity ELSE 0 END) as sold_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'successful' THEN order_items.quantity * order_items.selling_price ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN order_items.status <> 'pending' THEN order_items.quantity ELSE 0 END) as dispatched_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'missing' THEN order_items.quantity ELSE 0 END) as missing_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'rto' THEN order_items.quantity ELSE 0 END) as rto_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'successful' THEN order_items.quantity * COALESCE(products.cost_price, 0) ELSE 0 END) as estimated_cogs");

        if ($from) {
            $query->where('orders.created_at', '>=', $from);
        }

        return $query->get()->keyBy('platform_id')->all();
    }

    /**
     * Generic "one grouped sum per platform" helper, so every extra metric costs
     * exactly one query rather than one per platform.
     *
     * @param  array<int, array{0:string,1:string,2:string}>  $joins  extra [table, first, second] joins
     */
    private function platformColumn(
        ?Carbon $from,
        string $table,
        string $expression,
        string $alias,
        ?callable $constrain = null,
        array $joins = []
    ): array {
        $query = DB::table($table)
            ->join('order_items', 'order_items.id', '=', $table.'.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->groupBy('orders.platform_id')
            ->selectRaw('orders.platform_id')
            ->selectRaw($expression.' as '.$alias);

        foreach ($joins as [$joinTable, $first, $second]) {
            $query->join($joinTable, $first, '=', $second);
        }

        if ($constrain) {
            $constrain($query);
        }

        if ($from) {
            $query->where('orders.created_at', '>=', $from);
        }

        return $query->get()->keyBy('platform_id')->all();
    }

    /**
     * Returns and RTO split by how the customer paid.
     *
     * COD is where nearly all RTO losses come from, so this is the number that decides
     * whether offering it is worth it. One grouped query, so the cost is constant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentModeBreakdown(?int $months = null): array
    {
        $from = $months ? Carbon::now()->subMonths($months)->startOfMonth() : null;

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->groupBy('orders.payment_mode')
            ->selectRaw('orders.payment_mode')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw("SUM(CASE WHEN order_items.status = 'successful' THEN order_items.quantity ELSE 0 END) as sold_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'successful' THEN order_items.quantity * order_items.selling_price ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN order_items.status <> 'pending' THEN order_items.quantity ELSE 0 END) as dispatched_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status IN ('customer_return','rto') THEN order_items.quantity ELSE 0 END) as returned_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'rto' THEN order_items.quantity ELSE 0 END) as rto_qty")
            ->selectRaw("SUM(CASE WHEN order_items.status = 'missing' THEN order_items.quantity ELSE 0 END) as missing_qty")
            ->get();

        return $rows->map(function ($row) {
            $mode = $row->payment_mode ?: Order::PAYMENT_MODE_PREPAID;
            $orders = (int) $row->orders_count;
            $revenue = (float) $row->revenue;
            $dispatched = (int) $row->dispatched_qty;
            $returned = (int) $row->returned_qty;
            $rto = (int) $row->rto_qty;

            return [
                'mode' => $mode,
                'label' => $mode === Order::PAYMENT_MODE_COD ? 'Cash on Delivery' : 'Prepaid',
                'orders' => $orders,
                'revenue' => round($revenue, 2),
                'avg_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0,
                'sold_qty' => (int) $row->sold_qty,
                'dispatched_qty' => $dispatched,
                'returned_qty' => $returned,
                'rto_qty' => $rto,
                'missing_qty' => (int) $row->missing_qty,
                'return_rate' => $dispatched > 0 ? round($returned / $dispatched * 100, 1) : 0,
                'rto_rate' => $dispatched > 0 ? round($rto / $dispatched * 100, 1) : 0,
            ];
        })->sortByDesc('orders')->values()->all();
    }

    private function getProductRankings(string $sortBy): array
    {
        $products = Product::all();
        $rankings = [];

        foreach ($products as $product) {
            $soldQty = $product->total_sold;
            $returnedQty = $product->total_returned;
            $missingQty = $product->total_missing;
            $revenue = $product->total_received;
            $charges = $product->total_charges;
            $dispatched = $soldQty + $returnedQty + $missingQty;

            $cogs = DB::table('order_item_batches')
                ->join('stock_batches', 'order_item_batches.stock_batch_id', '=', 'stock_batches.id')
                ->join('order_items', 'order_item_batches.order_item_id', '=', 'order_items.id')
                ->where('order_items.product_id', $product->id)
                ->where('order_items.status', 'successful')
                ->sum(DB::raw('order_item_batches.quantity_deducted * stock_batches.unit_cost'));

            if ($cogs == 0 && $soldQty > 0) {
                $cogs = $product->cost_price * $soldQty;
            }

            $netProfit = $revenue - $cogs - $charges;
            $returnRate = $dispatched > 0 ? round(($returnedQty / $dispatched) * 100, 1) : 0;

            $rankings[] = [
                'product' => $product, 'sold_qty' => (int) $soldQty,
                'returned_qty' => (int) $returnedQty, 'missing_qty' => (int) $missingQty,
                'dispatched' => (int) $dispatched, 'revenue' => round($revenue, 2),
                'cogs' => round($cogs, 2), 'charges' => round($charges, 2),
                'net_profit' => round($netProfit, 2), 'return_rate' => $returnRate,
            ];
        }

        match ($sortBy) {
            'profit_desc' => usort($rankings, fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']),
            'loss_desc' => usort($rankings, fn ($a, $b) => $a['net_profit'] <=> $b['net_profit']),
            'return_ratio_asc' => usort($rankings, fn ($a, $b) => $a['return_rate'] <=> $b['return_rate']),
        };

        foreach ($rankings as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        unset($r);

        return $rankings;
    }
}
