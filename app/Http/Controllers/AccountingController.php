<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGeneralExpenseRequest;
use App\Http\Requests\StorePlatformPaymentRequest;
use App\Http\Requests\StorePlatformSettlementRequest;
use App\Models\GeneralExpense;
use App\Models\PlatformPayment;
use App\Models\PlatformSettlement;
use App\Services\AccountingService;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        // All-time, because money owed is a running balance rather than a monthly figure.
        $overview = $this->service->settlementOverview();

        return view('accounting.index', compact('summary', 'platforms', 'overview'));
    }

    // --- Platform Payments ---

    public function payments()
    {
        $platformId = request('platform_id');
        $payments = $this->service->paginatedPayments(15, $platformId);
        $platforms = $this->platformService->allActive();

        return view('accounting.payments', compact('payments', 'platforms'));
    }

    public function storePayment(StorePlatformPaymentRequest $request)
    {
        $payment = $this->service->createPayment($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Platform payment recorded successfully.',
                'data' => $payment,
            ], 201);
        }

        return redirect()->route('accounting.payments')->with('success', 'Platform payment recorded successfully.');
    }

    public function updatePayment(StorePlatformPaymentRequest $request, PlatformPayment $payment)
    {
        $payment = $this->service->updatePayment($payment, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Payment updated successfully.',
                'data' => $payment,
            ]);
        }

        return redirect()->route('accounting.payments')->with('success', 'Payment updated successfully.');
    }

    public function destroyPayment(Request $request, PlatformPayment $payment)
    {
        $this->service->deletePayment($payment);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Payment deleted successfully.']);
        }

        return redirect()->route('accounting.payments')->with('success', 'Payment deleted successfully.');
    }

    // --- General Expenses ---

    public function expenses()
    {
        $category = request('category');
        $expenses = $this->service->paginatedExpenses(15, $category);

        return view('accounting.expenses', compact('expenses'));
    }

    public function storeExpense(StoreGeneralExpenseRequest $request)
    {
        $expense = $this->service->createExpense($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Expense recorded successfully.',
                'data' => $expense,
            ], 201);
        }

        return redirect()->route('accounting.expenses')->with('success', 'Expense recorded successfully.');
    }

    public function updateExpense(StoreGeneralExpenseRequest $request, GeneralExpense $expense)
    {
        $expense = $this->service->updateExpense($expense, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Expense updated successfully.',
                'data' => $expense,
            ]);
        }

        return redirect()->route('accounting.expenses')->with('success', 'Expense updated successfully.');
    }

    public function destroyExpense(Request $request, GeneralExpense $expense)
    {
        $this->service->deleteExpense($expense);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Expense deleted successfully.']);
        }

        return redirect()->route('accounting.expenses')->with('success', 'Expense deleted successfully.');
    }

    // --- Platform Settlements ---

    public function settlements()
    {
        $settlements = $this->service->paginatedSettlements(
            15,
            request('platform_id') ? (int) request('platform_id') : null,
            request('status')
        );
        $platforms = $this->platformService->allActive();
        $overview = $this->service->settlementOverview();

        return view('accounting.settlements', compact('settlements', 'platforms', 'overview'));
    }

    public function storeSettlement(StorePlatformSettlementRequest $request)
    {
        $settlement = $this->service->createSettlement($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Settlement recorded.', 'data' => $settlement], 201);
        }

        return redirect()->route('accounting.settlements')->with('success', 'Settlement recorded.');
    }

    public function updateSettlement(StorePlatformSettlementRequest $request, PlatformSettlement $settlement)
    {
        $settlement = $this->service->updateSettlement($settlement, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Settlement updated.', 'data' => $settlement]);
        }

        return redirect()->route('accounting.settlements')->with('success', 'Settlement updated.');
    }

    /**
     * Mark a payout as banked, or undo that if it was a mistake.
     */
    public function receiveSettlement(Request $request, PlatformSettlement $settlement)
    {
        $data = $request->validate([
            'received' => 'required|boolean',
            'received_on' => 'nullable|date',
        ]);

        $this->service->receiveSettlement($settlement, (bool) $data['received'], $data['received_on'] ?? null);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Settlement updated.']);
        }

        return redirect()->route('accounting.settlements')
            ->with('success', $data['received'] ? 'Payout marked as received.' : 'Payout moved back to pending.');
    }

    public function destroySettlement(Request $request, PlatformSettlement $settlement)
    {
        $this->service->deleteSettlement($settlement);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Settlement deleted.']);
        }

        return redirect()->route('accounting.settlements')->with('success', 'Settlement deleted.');
    }

    /**
     * What the sales themselves say a payout should be, so figures are never typed twice.
     */
    public function suggestSettlement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform_id' => 'required|integer|exists:platforms,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json($this->service->suggestSettlement(
            (int) $data['platform_id'],
            $data['from'] ?? null,
            $data['to'] ?? null,
        ));
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
