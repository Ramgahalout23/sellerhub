<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BatchOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Support\Facades\Route;

// --- Auth Routes (Guest only) ---
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// --- Logout (Auth required) ---
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// --- All Protected Routes (Auth required) ---
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Platforms
    Route::resource('platforms', PlatformController::class)->except(['edit', 'update']);

    // Suppliers
    Route::resource('suppliers', SupplierController::class);
    Route::get('suppliers/{supplier}/batch-orders-more', [SupplierController::class, 'loadMoreBatchOrders'])->name('suppliers.batch-orders-more');

    // Products
    Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('products/{product}/summary', [ProductController::class, 'detailSummary'])->name('products.summary');
    Route::resource('products', ProductController::class);

    // Batch Orders
    // Scoped binding ensures an invoice must belong to the batch order in the URL.
    Route::get('batch-orders/{batchOrder}/invoices/{invoice}', [BatchOrderController::class, 'downloadInvoice'])
        ->scopeBindings()
        ->name('batch-orders.invoices.download');
    Route::delete('batch-orders/{batchOrder}/invoices/{invoice}', [BatchOrderController::class, 'deleteInvoice'])
        ->scopeBindings()
        ->name('batch-orders.invoices.destroy');
    Route::resource('batch-orders', BatchOrderController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::delete('batch-orders/{batchOrder}/items/{item}', [BatchOrderController::class, 'deleteItem'])->name('batch-orders.delete-item');

    // Orders
    Route::get('orders/reminders', [OrderController::class, 'needsReminder'])->name('orders.reminders');
    Route::get('orders/charge-preview', [OrderController::class, 'chargePreview'])->name('orders.charge-preview');
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Marketplace CSV import (declared before the orders resource so it wins)
    Route::get('orders/import', [OrderImportController::class, 'create'])->name('orders.import.create');
    Route::post('orders/import', [OrderImportController::class, 'store'])->name('orders.import.store');
    Route::get('orders/import/template', [OrderImportController::class, 'template'])->name('orders.import.template');
    Route::patch('orders/{order}/shipment-status', [OrderController::class, 'updateShipmentStatus'])->name('orders.update-shipment-status');
    // Scoped binding ensures {item} actually belongs to {order}.
    Route::patch('orders/{order}/items/{item}/status', [OrderController::class, 'updateItemStatus'])
        ->scopeBindings()
        ->name('orders.update-item-status');
    Route::resource('orders', OrderController::class)->except(['edit', 'update']);

    // Accounting
    Route::get('accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('accounting/api', [AccountingController::class, 'ledgerApi'])->name('accounting.api');

    // Platform Payments
    Route::get('accounting/payments', [AccountingController::class, 'payments'])->name('accounting.payments');
    Route::post('accounting/payments', [AccountingController::class, 'storePayment'])->name('accounting.payments.store');
    Route::patch('accounting/payments/{payment}', [AccountingController::class, 'updatePayment'])->name('accounting.payments.update');
    Route::delete('accounting/payments/{payment}', [AccountingController::class, 'destroyPayment'])->name('accounting.payments.destroy');

    // Platform Settlements (gross / fees / net payouts, with aging)
    Route::get('accounting/settlements', [AccountingController::class, 'settlements'])->name('accounting.settlements');
    Route::get('accounting/settlements/suggest', [AccountingController::class, 'suggestSettlement'])->name('accounting.settlements.suggest');
    Route::post('accounting/settlements', [AccountingController::class, 'storeSettlement'])->name('accounting.settlements.store');
    Route::patch('accounting/settlements/{settlement}', [AccountingController::class, 'updateSettlement'])->name('accounting.settlements.update');
    Route::patch('accounting/settlements/{settlement}/received', [AccountingController::class, 'receiveSettlement'])->name('accounting.settlements.received');
    Route::delete('accounting/settlements/{settlement}', [AccountingController::class, 'destroySettlement'])->name('accounting.settlements.destroy');

    // General Expenses
    Route::get('accounting/expenses', [AccountingController::class, 'expenses'])->name('accounting.expenses');
    Route::post('accounting/expenses', [AccountingController::class, 'storeExpense'])->name('accounting.expenses.store');
    Route::patch('accounting/expenses/{expense}', [AccountingController::class, 'updateExpense'])->name('accounting.expenses.update');
    Route::delete('accounting/expenses/{expense}', [AccountingController::class, 'destroyExpense'])->name('accounting.expenses.destroy');

    // Stock source preview (for order form)
    Route::get('api/products/{product}/stock-batches', function (Product $product) {
        $batches = StockBatch::where('product_id', $product->id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->with('supplier')
            ->get();

        return response()->json($batches->map(fn ($b) => [
            'id' => $b->id,
            'supplier' => $b->supplier->name ?? 'Unknown',
            'remaining' => $b->remaining_quantity,
            'unit_cost' => $b->unit_cost,
            'status' => $b->status,
        ]));
    })->name('api.stock-batches');

    // Insights
    Route::get('insights', [InsightsController::class, 'index'])->name('insights.index');
    Route::get('insights/supplier-comparison', [InsightsController::class, 'supplierComparison'])->name('insights.supplier-comparison');
    Route::get('insights/rankings', [InsightsController::class, 'rankings'])->name('insights.rankings');
    Route::get('insights/platform-comparison', [InsightsController::class, 'platformComparison'])->name('insights.platform-comparison');
    Route::get('insights/payment-modes', [InsightsController::class, 'paymentModes'])->name('insights.payment-modes');
    Route::get('insights/health-scores', [InsightsController::class, 'healthScores'])->name('insights.health-scores');
    Route::get('insights/time-trend', [InsightsController::class, 'timeTrend'])->name('insights.time-trend');

    // Alerts
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('alerts/{alert}/read', [AlertController::class, 'markAsRead'])->name('alerts.read');
    Route::patch('alerts/read-all', [AlertController::class, 'markAllAsRead'])->name('alerts.read-all');
    Route::get('alerts/unread-count', [AlertController::class, 'unreadCount'])->name('alerts.unread-count');
});
