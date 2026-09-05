<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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
        $supplierComparison = app(\App\Services\OrderService::class)
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
            'stock_batches' => $stockBatches,
            'supplier_comparison' => $supplierComparison,
            'recent_orders' => $product->orderItems()->with(['order.platform'])->latest()->paginate(10),
            'custom_fields' => $this->repo->getCustomFieldValues($product),
        ];
    }

    public function productFields(): Collection
    {
        return $this->repo->productFields();
    }
}
