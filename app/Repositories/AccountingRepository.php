<?php

namespace App\Repositories;

use App\Models\GeneralExpense;
use App\Models\PlatformPayment;
use App\Models\PlatformSettlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountingRepository
{
    public function __construct(
        protected PlatformPayment $paymentModel = new PlatformPayment,
        protected GeneralExpense $expenseModel = new GeneralExpense,
        protected PlatformSettlement $settlementModel = new PlatformSettlement
    ) {}

    // --- Platform Settlements ---

    public function paginatedSettlements(int $perPage = 15, ?int $platformId = null, ?string $status = null): LengthAwarePaginator
    {
        $query = $this->settlementModel->with('platform')->latestFirst();

        if ($platformId) {
            $query->forPlatform($platformId);
        }

        if ($status === 'pending') {
            $query->pending();
        }

        if ($status === 'received') {
            $query->received();
        }

        return $query->paginate($perPage);
    }

    public function findSettlement(int $id): PlatformSettlement
    {
        return $this->settlementModel->with('platform')->findOrFail($id);
    }

    public function createSettlement(array $data): PlatformSettlement
    {
        return $this->settlementModel->create($data);
    }

    public function updateSettlement(PlatformSettlement $settlement, array $data): PlatformSettlement
    {
        $settlement->update($data);

        return $settlement->fresh('platform');
    }

    public function deleteSettlement(PlatformSettlement $settlement): bool
    {
        return $settlement->delete();
    }

    // --- Platform Payments ---

    public function paginatedPayments(int $perPage = 15, ?int $platformId = null): LengthAwarePaginator
    {
        $query = $this->paymentModel->with('platform')->recent();

        if ($platformId) {
            $query->forPlatform($platformId);
        }

        return $query->paginate($perPage);
    }

    public function findPayment(int $id): PlatformPayment
    {
        return $this->paymentModel->with('platform')->findOrFail($id);
    }

    public function createPayment(array $data): PlatformPayment
    {
        $data['type'] = $data['type'] ?? 'manual';

        return $this->paymentModel->create($data);
    }

    public function updatePayment(PlatformPayment $payment, array $data): PlatformPayment
    {
        $payment->update($data);

        return $payment->fresh('platform');
    }

    public function deletePayment(PlatformPayment $payment): bool
    {
        return $payment->delete();
    }

    public function totalPayments(): float
    {
        return (float) $this->paymentModel->sum('amount');
    }

    public function paymentsByPlatform(): Collection
    {
        return $this->paymentModel
            ->selectRaw('platform_id, SUM(amount) as total')
            ->with('platform')
            ->groupBy('platform_id')
            ->get();
    }

    // --- General Expenses ---

    public function paginatedExpenses(int $perPage = 15, ?string $category = null): LengthAwarePaginator
    {
        $query = $this->expenseModel->recent();

        if ($category) {
            $query->forCategory($category);
        }

        return $query->paginate($perPage);
    }

    public function findExpense(int $id): GeneralExpense
    {
        return $this->expenseModel->findOrFail($id);
    }

    public function createExpense(array $data): GeneralExpense
    {
        return $this->expenseModel->create($data);
    }

    public function updateExpense(GeneralExpense $expense, array $data): GeneralExpense
    {
        $expense->update($data);

        return $expense->fresh();
    }

    public function deleteExpense(GeneralExpense $expense): bool
    {
        return $expense->delete();
    }

    public function totalExpenses(): float
    {
        return (float) $this->expenseModel->sum('amount');
    }

    public function expensesByCategory(): Collection
    {
        return $this->expenseModel
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();
    }
}
