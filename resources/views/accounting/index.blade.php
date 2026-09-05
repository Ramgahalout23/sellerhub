@extends('layouts.app')
@section('title', 'Accounting & Ledger')

@section('content')

@php
    $s = $summary; // shorthand

    // Simple story numbers
    $totalInvested = $s['total_invested'];
    $totalRevenue = $s['total_revenue'];
    $pendingRevenue = $s['pending_revenue'];
    $expectedRevenue = $s['expected_revenue'];
    $totalCogs = $s['total_cogs'];
    $totalCharges = $s['total_charges'];
    $returnCharges = $s['return_charges'];
    $totalExpenses = $s['total_expenses'];
    $missingCost = $s['missing_cost'];
    $returnsQty = $s['returns_qty'] + $s['orphan_returns'];
    $inventoryValue = $s['inventory_value'];

    // Simple profit
    $simpleProfit = $totalRevenue - $totalCogs - $totalCharges - $returnCharges - $totalExpenses - $missingCost;
    $cashIn = $s['total_payments_received'];
    $cashOut = $totalInvested + $totalExpenses;
@endphp

<!-- Date Filter -->
<x-card>
    <form class="flex gap-3 items-end flex-wrap" method="GET">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">From</label>
            <input type="date" name="from" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ request('from') }}">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">To</label>
            <input type="date" name="to" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ request('to') }}">
        </div>
        <button class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors">Filter</button>
        @if(request('from') || request('to'))
            <a href="{{ route('accounting.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>
</x-card>

{{-- ═══════════════════════════════════════════════════════════
     THE BIG PICTURE — Your Business in One View
     ═══════════════════════════════════════════════════════════ --}}
<x-card>
    <div class="text-center mb-4">
        <h2 class="text-lg font-bold text-gray-900">Your Business at a Glance</h2>
        <p class="text-sm text-gray-500 mt-1">Simple breakdown: what went out, what came in, and what's left</p>
    </div>

    {{-- Money Flow Visual --}}
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-5">
        {{-- Row 1: What you spent --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center">
                <i class="bi bi-arrow-down-circle text-rose-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Tune stock kharidne mein lagaya</p>
                <p class="text-xs text-gray-500">Total paid to suppliers for all products</p>
            </div>
            <p class="text-lg font-bold text-rose-600">₹{{ number_format($totalInvested) }}</p>
        </div>

        {{-- Row 2: Expenses --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center">
                <i class="bi bi-receipt text-orange-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Rent, packaging, bills wagera kharcha</p>
                <p class="text-xs text-gray-500">General expenses — rent, packaging, subscription, utilities</p>
            </div>
            <p class="text-lg font-bold text-orange-600">₹{{ number_format($totalExpenses) }}</p>
        </div>

        {{-- Divider --}}
        <div class="border-t border-gray-300 my-3"></div>

        {{-- Row 3: Total Out --}}
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-gray-200 flex items-center justify-center">
                <i class="bi bi-cash-stack text-gray-700 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold text-gray-900">Tumne total kitna paisa lagaya (Cash Out)</p>
                <p class="text-xs text-gray-500">= Stock purchase + General expenses</p>
            </div>
            <p class="text-xl font-bold text-gray-900">₹{{ number_format($cashOut) }}</p>
        </div>

        {{-- Arrow Down --}}
        <div class="text-center my-2">
            <div class="inline-flex items-center gap-2 text-gray-400">
                <div class="w-16 h-px bg-gray-300"></div>
                <i class="bi bi-arrow-down text-xl"></i>
                <span class="text-xs font-semibold uppercase tracking-wide">Ab dekho kitna wapas aaya</span>
                <i class="bi bi-arrow-down text-xl"></i>
                <div class="w-16 h-px bg-gray-300"></div>
            </div>
        </div>

        {{-- Row 4: Sales Revenue --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                <i class="bi bi-check-circle text-emerald-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Successful orders se mila (delivered + confirmed)</p>
                <p class="text-xs text-gray-500">Money from orders marked "Successful"</p>
            </div>
            <p class="text-lg font-bold text-emerald-600">₹{{ number_format($totalRevenue) }}</p>
        </div>

        {{-- Row 5: Pending --}}
        @if($pendingRevenue > 0)
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                <i class="bi bi-hourglass-split text-amber-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Abhi delivery ho rahi hai (In Transit)</p>
                <p class="text-xs text-gray-500">Yeh paisa abhi aayega — deliver hone ke baad count hoga</p>
            </div>
            <p class="text-lg font-bold text-amber-600">₹{{ number_format($pendingRevenue) }}</p>
        </div>
        @endif

        {{-- Row 6: Platform Charges --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center">
                <i class="bi bi-credit-card text-rose-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Amazon/Flipkart ka kat gaya (Platform charges)</p>
                <p class="text-xs text-gray-500">Marketplace fees deducted from each sale</p>
            </div>
            <p class="text-lg font-bold text-rose-600">₹{{ number_format($totalCharges) }}</p>
        </div>

        {{-- Row 7: Returns --}}
        @if($returnsQty > 0)
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center">
                <i class="bi bi-arrow-return-left text-rose-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Returns hue — customer ne wapas bheja ({{ number_format($returnsQty) }} units)</p>
                <p class="text-xs text-gray-500">Return shipping kharcha: ₹{{ number_format($returnCharges) }}</p>
            </div>
            <p class="text-lg font-bold text-rose-600">{{ number_format($returnsQty) }} items</p>
        </div>
        @endif

        {{-- Row 8: Missing --}}
        @if($missingCost > 0)
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="bi bi-exclamation-triangle text-red-600 text-lg"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Missing/lost — product gayab ho gaya (total loss)</p>
                <p class="text-xs text-gray-500">Na wapas aaya, na paisa mila — seedha nuksan</p>
            </div>
            <p class="text-lg font-bold text-red-600">-₹{{ number_format($missingCost) }}</p>
        </div>
        @endif

        {{-- Divider --}}
        <div class="border-t border-gray-300 my-3"></div>

        {{-- FINAL PROFIT / LOSS --}}
        <div class="bg-white rounded-2xl border-2 {{ $simpleProfit >= 0 ? 'border-emerald-200' : 'border-rose-200' }} p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold {{ $simpleProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $simpleProfit >= 0 ? 'Tumne kamaya (PROFIT)' : 'Tumhara nuksan (LOSS)' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        = Revenue ₹{{ number_format($totalRevenue) }} − COGS ₹{{ number_format($totalCogs) }} − Charges ₹{{ number_format($totalCharges) }} − Return ₹{{ number_format($returnCharges) }} − Expenses ₹{{ number_format($totalExpenses) }} − Missing ₹{{ number_format($missingCost) }}
                    </p>
                </div>
                <p class="text-3xl font-bold sm:shrink-0 {{ $simpleProfit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $simpleProfit >= 0 ? '+' : '' }}₹{{ number_format($simpleProfit) }}
                </p>
            </div>
        </div>

        {{-- Expected if pending succeed --}}
        @if($pendingRevenue > 0)
        <div class="mt-3 bg-amber-50 rounded-xl p-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-info-circle text-amber-600"></i>
                <p class="text-sm text-amber-800">
                    <strong>Agar sab pending orders deliver ho jayein</strong> → Expected Profit: 
                    <span class="font-bold {{ ($simpleProfit + $pendingRevenue - $s['pending_cogs'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        ₹{{ number_format($s['expected_profit']) }}
                    </span>
                </p>
            </div>
        </div>
        @endif
    </div>
</x-card>

{{-- ═══════════════════════════════════════════════════════════
     SECTION 2 — Cash Balance (Jeb mein kitna hai)
     ═══════════════════════════════════════════════════════════ --}}
<x-card class="mt-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Cash Balance — Pocket Check</h2>
    <p class="text-sm text-gray-500 mb-4">Asli paisa — kitna aaya, kitna gaya, kitna bacha</p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Cash In --}}
        <div class="bg-emerald-50 rounded-2xl p-4 border border-emerald-100">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <i class="bi bi-arrow-down-left text-emerald-600"></i>
                </div>
                <p class="text-sm font-bold text-emerald-800">Cash In (Tumhare paas aaya)</p>
            </div>
            <p class="text-2xl font-bold text-emerald-600 mb-1">₹{{ number_format($cashIn) }}</p>
            <p class="text-xs text-emerald-600">Platform se paisa mila</p>
            <div class="mt-2 space-y-1 text-xs text-emerald-700">
                <div class="flex justify-between"><span>Orders se auto mila</span><span class="font-semibold">₹{{ number_format($s['order_payments']) }}</span></div>
                <div class="flex justify-between"><span>Manual settlement</span><span class="font-semibold">₹{{ number_format($s['manual_payments']) }}</span></div>
            </div>
        </div>

        {{-- Cash Out --}}
        <div class="bg-rose-50 rounded-2xl p-4 border border-rose-100">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center">
                    <i class="bi bi-arrow-up-right text-rose-600"></i>
                </div>
                <p class="text-sm font-bold text-rose-800">Cash Out (Tumne kharch kiya)</p>
            </div>
            <p class="text-2xl font-bold text-rose-600 mb-1">₹{{ number_format($cashOut) }}</p>
            <p class="text-xs text-rose-600">Suppliers + General expenses</p>
            <div class="mt-2 space-y-1 text-xs text-rose-700">
                <div class="flex justify-between"><span>Stock kharida</span><span class="font-semibold">₹{{ number_format($totalInvested) }}</span></div>
                <div class="flex justify-between"><span>General expenses</span><span class="font-semibold">₹{{ number_format($totalExpenses) }}</span></div>
            </div>
        </div>

        {{-- Cash Position --}}
        <div class="rounded-2xl p-4 border-2 {{ ($cashIn - $cashOut) >= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200' }}">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg {{ ($cashIn - $cashOut) >= 0 ? 'bg-emerald-100' : 'bg-rose-100' }} flex items-center justify-center">
                    <i class="bi bi-wallet2 {{ ($cashIn - $cashOut) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}"></i>
                </div>
                <p class="text-sm font-bold {{ ($cashIn - $cashOut) >= 0 ? 'text-emerald-800' : 'text-rose-800' }}">
                    {{ ($cashIn - $cashOut) >= 0 ? 'Fayda hai (Bacha hua)' : 'Nuksan hai (Kam pada)' }}
                </p>
            </div>
            <p class="text-3xl font-bold {{ ($cashIn - $cashOut) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ ($cashIn - $cashOut) >= 0 ? '+' : '' }}₹{{ number_format($cashIn - $cashOut) }}
            </p>
            <p class="text-xs {{ ($cashIn - $cashOut) >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">
                = ₹{{ number_format($cashIn) }} aaya − ₹{{ number_format($cashOut) }} gaya
            </p>
            <div class="mt-2 text-xs {{ ($cashIn - $cashOut) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                @if($inventoryValue > 0)
                <p>📦 Abhi stock mein ₹{{ number_format($inventoryValue) }} ka maal pada hai</p>
                @endif
                <p class="mt-1">Yeh paisa abhi bank mein / haath mein hai</p>
            </div>
        </div>
    </div>
</x-card>

{{-- ═══════════════════════════════════════════════════════════
     SECTION 3 — Detailed Breakdown (For Accountant / CA)
     ═══════════════════════════════════════════════════════════ --}}
<x-card class="mt-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Detailed Breakdown</h2>
    <p class="text-sm text-gray-500 mb-4">Har item ka hisaab — CA ya accountant ke liye</p>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Revenue Breakdown --}}
        <div>
            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <i class="bi bi-graph-up text-emerald-600"></i> Income (Aayi hui dhanrashi)
            </h3>
            <div class="space-y-2">
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">✅ Successful orders (delivered + confirmed)</span>
                    <span class="font-bold text-emerald-600">₹{{ number_format($totalRevenue) }}</span>
                </div>
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">⏳ Pending orders (in transit — abhi nahi mila)</span>
                    <span class="font-bold text-amber-600">₹{{ number_format($pendingRevenue) }}</span>
                </div>
                <div class="flex justify-between text-sm py-2 font-bold bg-gray-50 rounded-lg px-2">
                    <span class="text-gray-900">Expected Total (agar sab deliver ho jaye)</span>
                    <span class="text-gray-900">₹{{ number_format($expectedRevenue) }}</span>
                </div>
                @if($returnsQty > 0)
                <div class="flex justify-between text-sm py-2 border-b border-gray-100 mt-2">
                    <span class="text-gray-600">↩️ Returns ({{ number_format($returnsQty) }} items wapas aaye)</span>
                    <span class="font-bold text-rose-500">₹{{ number_format($totalRevenue * ($returnsQty / max($returnsQty + $totalRevenue / 100, 1))) }} (approx)</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Cost Breakdown --}}
        <div>
            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <i class="bi bi-cash text-rose-600"></i> Expenses (Kharche)
            </h3>
            <div class="space-y-2">
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">📦 COGS — sirf bikne wale maal ki keemat</span>
                    <span class="font-bold text-rose-600">₹{{ number_format($totalCogs) }}</span>
                </div>
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">💳 Platform charges (Amazon/Flipkart fees)</span>
                    <span class="font-bold text-rose-600">₹{{ number_format($totalCharges) }}</span>
                </div>
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">🚚 Return shipping (return bhejne ka kiraya)</span>
                    <span class="font-bold text-rose-600">₹{{ number_format($returnCharges) }}</span>
                </div>
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">🏪 General expenses (rent, packaging, bills)</span>
                    <span class="font-bold text-rose-600">₹{{ number_format($totalExpenses) }}</span>
                </div>
                @if($missingCost > 0)
                <div class="flex justify-between text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">❌ Missing/lost (gayab hua — pure nuksan)</span>
                    <span class="font-bold text-red-600">₹{{ number_format($missingCost) }}</span>
                </div>
                @endif
                <div class="flex justify-between text-sm py-2 font-bold bg-gray-50 rounded-lg px-2">
                    <span class="text-gray-900">Total Kharche</span>
                    <span class="text-gray-900">₹{{ number_format($totalCogs + $totalCharges + $returnCharges + $totalExpenses + $missingCost) }}</span>
                </div>
            </div>
        </div>
    </div>
</x-card>

{{-- ═══════════════════════════════════════════════════════════
     SECTION 4 — Expense Categories
     ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mt-6">
    <x-card>
        <h3 class="text-sm font-bold text-gray-900 mb-1">Expense Categories</h3>
        <p class="text-xs text-gray-400 mb-3"><a href="{{ route('accounting.expenses') }}" class="text-brand-600 hover:underline">Manage →</a></p>
        @php $ebc = \App\Models\GeneralExpense::selectRaw('category, SUM(amount) as total')->groupBy('category')->get(); @endphp
        <div class="space-y-2">
            @forelse($ebc as $e)
            <div class="flex justify-between items-center text-sm py-2 border-b border-gray-50">
                <div class="flex items-center gap-2">
                    @php
                        $icons = ['rent' => 'building', 'packaging' => 'box', 'subscription' => 'cloud', 'utilities' => 'lightning', 'shipping' => 'truck', 'marketing' => 'megaphone'];
                        $icon = $icons[strtolower($e->category)] ?? 'cash';
                    @endphp
                    <i class="bi bi-{{ $icon }} text-gray-400"></i>
                    <span class="text-gray-700 capitalize">{{ $e->category ?? 'Uncategorized' }}</span>
                </div>
                <span class="font-bold text-rose-600">₹{{ number_format($e->total) }}</span>
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-3">No expenses recorded yet.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <h3 class="text-sm font-bold text-gray-900 mb-1">Platform Payments</h3>
        <p class="text-xs text-gray-400 mb-3"><a href="{{ route('accounting.payments') }}" class="text-brand-600 hover:underline">Manage →</a></p>
        <div class="space-y-3">
            <div class="bg-emerald-50 rounded-xl p-3">
                <p class="text-xs font-semibold text-emerald-700 mb-1">Orders se auto mila</p>
                <p class="text-lg font-bold text-emerald-600">₹{{ number_format($s['order_payments']) }}</p>
                <p class="text-xs text-emerald-600">Har successful order pe automatically add hota hai</p>
            </div>
            <div class="bg-blue-50 rounded-xl p-3">
                <p class="text-xs font-semibold text-blue-700 mb-1">Manual settlements (platform payouts)</p>
                <p class="text-lg font-bold text-blue-600">₹{{ number_format($s['manual_payments']) }}</p>
                <p class="text-xs text-blue-600">Jab platform paisa bhejta hai account mein</p>
            </div>
            <div class="border-t border-gray-100 pt-2 flex justify-between text-sm font-bold">
                <span class="text-gray-900">Total Received</span>
                <span class="text-gray-900">₹{{ number_format($cashIn) }}</span>
            </div>
        </div>
    </x-card>
</div>

@endsection
