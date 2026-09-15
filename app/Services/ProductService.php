<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        protected ProductRepository $repo
    ) {}

    public function paginated(int $perPage = 15, ?string $search = null, ?int $supplierId = null): LengthAwarePaginator
    {
        return $this->repo->paginated($perPage, $search, $supplierId);
    }

    public function allActive(): Collection
    {
        return $this->repo->allActive();
    }

    public function find(int $id): Product
    {
        return $this->repo->find($id);
    }

    public function create(array $data, ?array $supplierIds = null, ?array $customFieldValues = null): Product
    {
        $product = $this->repo->create($data);

        if ($supplierIds) {
            $this->repo->syncSuppliers($product, $supplierIds, $data['cost_price']);
        }

        if ($customFieldValues) {
            $this->repo->syncCustomFieldValues($product, $customFieldValues);
        }

        return $product->fresh(['suppliers']);
    }

    public function update(Product $product, array $data, ?array $supplierIds = null, ?array $customFieldValues = null): Product
    {
        $product = $this->repo->update($product, $data);

        if ($supplierIds !== null) {
            $this->repo->syncSuppliers($product, $supplierIds, $data['cost_price'] ?? $product->cost_price);
        }

        if ($customFieldValues !== null) {
            $this->repo->syncCustomFieldValues($product, $customFieldValues);
        }

        return $product->fresh(['suppliers']);
    }

    public function delete(Product $product): bool
    {
        return $this->repo->delete($product);
    }

    public function lowStock(): Collection
    {
        return $this->repo->lowStock();
    }

    public function getDetailSummary(Product $product): array
    {
        $totalInvested = $product->total_invested;
        $totalSold = $product->total_sold;
        $totalReturned = $product->total_returned;
        $totalReceived = $product->total_received;
        $totalCharges = $product->total_charges;

        // Stock batches for this product
        $stockBatches = StockBatch::forProduct($product->id)
            ->with('supplier')
            ->fifo()
            ->get();

        // Supplier comparison
        $supplierComparison = app(OrderService::class)
            ->getSupplierComparison($product->id);

        return [
            'product' => $product,
            'total_purchased' => $product->batchOrderItems->sum('quantity'),
            'total_sold' => $totalSold,
            'total_returned' => $totalReturned,
            'return_ratio' => $product->return_ratio,
            'total_invested' => $totalInvested,
            'total_received' => $totalReceived,
            'total_charges' => $totalCharges,
            'net_profit' => $totalReceived - $totalInvested - $totalCharges,
            'suppliers' => $product->suppliers,
            'price_history' => $this->supplierPriceHistory($product),
            'stock_batches' => $stockBatches,
            'supplier_comparison' => $supplierComparison,
            'recent_orders' => $product->orderItems()->with(['order.platform'])->latest()->paginate(10),
            'custom_fields' => $this->repo->getCustomFieldValues($product),
        ];
    }

    /**
     * What every supplier has charged for this product over time, cheapest first.
     *
     * Built from the purchase records themselves (batch_order_items) rather than a
     * separate price list, so it can never drift from the stock and cost data. One
     * query regardless of how many suppliers or purchases exist.
     */
    public function supplierPriceHistory(Product $product): array
    {
        $rows = DB::table('batch_order_items')
            ->join('batch_orders', 'batch_orders.id', '=', 'batch_order_items.batch_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'batch_orders.supplier_id')
            ->where('batch_order_items.product_id', $product->id)
            ->orderBy('batch_orders.order_date')
            ->orderBy('batch_order_items.id')
            ->get([
                'batch_order_items.quantity',
                'batch_order_items.unit_cost',
                'batch_orders.order_date',
                'suppliers.id as supplier_id',
                'suppliers.name as supplier_name',
            ]);

        $suppliers = $rows->groupBy('supplier_id')->map(function ($purchases) {
            $history = $purchases->map(fn ($row) => [
                'date' => $row->order_date ? Carbon::parse($row->order_date) : null,
                'unit_cost' => round((float) $row->unit_cost, 2),
                'quantity' => (int) $row->quantity,
            ])->values();

            $costs = $history->pluck('unit_cost');
            $last = (float) $costs->last();
            $previous = $costs->count() > 1 ? (float) $costs->slice(-2, 1)->first() : null;

            return [
                'supplier_id' => $purchases->first()->supplier_id,
                'supplier_name' => $purchases->first()->supplier_name ?? 'Unknown supplier',
                'purchases' => $history->count(),
                'total_qty' => (int) $history->sum('quantity'),
                'last_date' => $history->last()['date'],
                'last_cost' => $last,
                'previous_cost' => $previous,
                'change_percent' => ($previous && $previous > 0) ? round((($last - $previous) / $previous) * 100, 1) : null,
                'min_cost' => round((float) $costs->min(), 2),
                'max_cost' => round((float) $costs->max(), 2),
                'avg_cost' => round((float) $costs->avg(), 2),
                'history' => $history->all(),
            ];
        })->sortBy('last_cost')->values();

        $cheapest = $suppliers->first();
        $dearest = $suppliers->last();

        return [
            'suppliers' => $suppliers->all(),
            'cheapest' => $cheapest,
            'cheapest_cost' => $cheapest['last_cost'] ?? null,
            'spread' => ($cheapest && $dearest && $suppliers->count() > 1)
                ? round($dearest['last_cost'] - $cheapest['last_cost'], 2)
                : 0.0,
            'purchases' => $rows->count(),
        ];
    }

    public function productFields(): Collection
    {
        return $this->repo->productFields();
    }
}
