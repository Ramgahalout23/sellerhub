<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\OrderItemReturn;
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
            if (!$supplier) continue;

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
                ->whereHas('orderItem', fn($q) => $q->where('status', 'successful'))
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

        usort($results, fn($a, $b) => $b['net_profit'] <=> $a['net_profit']);
        return ['product' => $product, 'suppliers' => $results];
    }

    public function topProfitableProducts(): array { return $this->getProductRankings('profit_desc'); }
    public function topLossMakingProducts(): array { return $this->getProductRankings('loss_desc'); }
    public function lowestReturnRatioProducts(): array { return $this->getProductRankings('return_ratio_asc'); }

    public function healthScoreRankings(): array
    {
        $products = $this->getProductRankings('profit_desc');
        if (empty($products)) return [];

        $maxProfit = max(array_map(fn($p) => abs($p['net_profit']), $products));
        if ($maxProfit == 0) $maxProfit = 1;

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

        usort($products, fn($a, $b) => $b['health_score'] <=> $a['health_score']);
        foreach ($products as $i => &$p) { $p['rank'] = $i + 1; }
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
                'end'   => $cursor->copy()->endOfMonth(),
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
            'profit_desc' => usort($rankings, fn($a, $b) => $b['net_profit'] <=> $a['net_profit']),
            'loss_desc' => usort($rankings, fn($a, $b) => $a['net_profit'] <=> $b['net_profit']),
            'return_ratio_asc' => usort($rankings, fn($a, $b) => $a['return_rate'] <=> $b['return_rate']),
        };

        foreach ($rankings as $i => &$r) { $r['rank'] = $i + 1; }
        unset($r);
        return $rankings;
    }
}
