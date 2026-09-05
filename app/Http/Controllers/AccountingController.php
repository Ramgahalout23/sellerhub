<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGeneralExpenseRequest;
use App\Http\Requests\StorePlatformPaymentRequest;
use App\Models\GeneralExpense;
use App\Models\PlatformPayment;
use App\Services\AccountingService;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;

class AccountingController extends Controller
{
    public function __construct(
        protected AccountingService $service,
        protected PlatformService $platformService
    ) {}

    public function index()
    {
        $summary = $this->service->ledgerSummary(
            request('from'),
            request('to')
        );
        $platforms = $this->platformService->allActive();

        return view('accounting.index', compact('summary', 'platforms'));
    }

    // --- Platform Payments ---

    public function payments()
    {
        $platformId = request('platform_id');
        $payments = $this->service->paginatedPayments(15, $platformId);
        $platforms = $this->platformService->allActive();

        return view('accounting.payments', compact('payments', 'platforms'));
    }

    public function storePayment(StorePlatformPaymentRequest $request): JsonResponse
    {
        $payment = $this->service->createPayment($request->validated());
        return response()->json([
            'message' => 'Platform payment recorded successfully.',
            'data' => $payment,
        ], 201);
    }

    public function updatePayment(StorePlatformPaymentRequest $request, PlatformPayment $payment): JsonResponse
    {
        $payment = $this->service->updatePayment($payment, $request->validated());
        return response()->json([
            'message' => 'Payment updated successfully.',
            'data' => $payment,
        ]);
    }

    public function destroyPayment(PlatformPayment $payment): JsonResponse
    {
        $this->service->deletePayment($payment);
        return response()->json(['message' => 'Payment deleted successfully.']);
    }

    // --- General Expenses ---

    public function expenses()
    {
        $category = request('category');
        $expenses = $this->service->paginatedExpenses(15, $category);

        return view('accounting.expenses', compact('expenses'));
    }

    public function storeExpense(StoreGeneralExpenseRequest $request): JsonResponse
    {
        $expense = $this->service->createExpense($request->validated());
        return response()->json([
            'message' => 'Expense recorded successfully.',
            'data' => $expense,
        ], 201);
    }

    public function updateExpense(StoreGeneralExpenseRequest $request, GeneralExpense $expense): JsonResponse
    {
        $expense = $this->service->updateExpense($expense, $request->validated());
        return response()->json([
            'message' => 'Expense updated successfully.',
            'data' => $expense,
        ]);
    }

    public function destroyExpense(GeneralExpense $expense): JsonResponse
    {
        $this->service->deleteExpense($expense);
        return response()->json(['message' => 'Expense deleted successfully.']);
    }

    public function ledgerApi(): JsonResponse
    {
        $summary = $this->service->ledgerSummary(
            request('from'),
            request('to')
        );
        return response()->json(['data' => $summary]);
    }
}
