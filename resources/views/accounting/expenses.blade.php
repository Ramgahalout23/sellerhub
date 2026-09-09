@extends('layouts.app')
@section('title', 'General Expenses')

@section('actions')
<button onclick="openExpenseModal()" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Record Expense</span><span class="sm:hidden">Add</span>
</button>
@endsection

@section('content')
<x-card padding="false">
    <x-slot:action>
        <form class="flex gap-2" method="GET">
            <select name="category" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none max-w-[45vw]">
                <option value="">All Categories</option>
                @foreach(['rent','utilities','packaging','subscription','misc'] as $cat)<option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>@endforeach
            </select>
            <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><i class="bi bi-funnel"></i></button>
        </form>
    </x-slot:action>

    {{-- Mobile: card list --}}
    <div class="md:hidden divide-y divide-gray-50">
        @forelse($expenses as $expense)
        <div class="flex items-center gap-3 p-4">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 break-words">{{ $expense->description }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $expense->expense_date->format('d M Y') }}</p>
                <div class="mt-1.5 flex items-center gap-2">
                    <x-badge variant="outline" class="capitalize">{{ $expense->category ?? '—' }}</x-badge>
                </div>
            </div>
            <div class="text-right shrink-0">
                <p class="text-sm font-bold text-rose-600">₹{{ number_format($expense->amount) }}</p>
                <form action="{{ route('accounting.expenses.destroy', $expense) }}" method="POST" class="inline mt-1" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button aria-label="Delete expense" class="p-2 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all"><i class="bi bi-trash text-sm"></i></button>
                </form>
            </div>
        </div>
        @empty
        <x-empty-state icon="receipt" title="No expenses" description="Record your first business expense." />
        @endforelse
    </div>

    {{-- Desktop: table --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-gray-100">
                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</th>
                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Description</th>
                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Category</th>
                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Amount</th>
                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($expenses as $expense)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3 text-gray-600">{{ $expense->expense_date->format('d M Y') }}</td>
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $expense->description }}</td>
                    <td class="px-5 py-3"><x-badge variant="outline" class="capitalize">{{ $expense->category ?? '—' }}</x-badge></td>
                    <td class="px-5 py-3 text-right font-bold text-rose-600">₹{{ number_format($expense->amount) }}</td>
                    <td class="px-5 py-3 text-right">
                        <form action="{{ route('accounting.expenses.destroy', $expense) }}" method="POST" class="inline" onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button aria-label="Delete expense" class="touch-target flex items-center justify-center p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all"><i class="bi bi-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5"><x-empty-state icon="receipt" title="No expenses" description="Record your first business expense." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($expenses->hasPages())<div class="border-t border-gray-100 px-5 py-3">{{ $expenses->withQueryString()->links() }}</div>@endif
</x-card>

{{-- Modal (custom, no Bootstrap dependency) --}}
<div id="expenseModalBackdrop" class="fixed inset-0 z-[120] hidden items-end sm:items-center justify-center bg-gray-900/50 backdrop-blur-sm p-0 sm:p-4" onclick="if(event.target===this)closeExpenseModal()">
    <div id="expenseModal" class="w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] flex flex-col" role="dialog" aria-modal="true" aria-label="Record Expense">
        <form action="{{ route('accounting.expenses.store') }}" method="POST" class="flex flex-col min-h-0">
            @csrf
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 shrink-0">
                <h6 class="text-sm font-bold text-gray-900">Record Expense</h6>
                <button type="button" onclick="closeExpenseModal()" aria-label="Close" class="p-2 -mr-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="px-5 py-5 space-y-4 overflow-y-auto">
                <div><label class="block text-sm font-semibold text-gray-700 mb-1.5">Description *</label><input type="text" name="description" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required placeholder="e.g. Packaging material"></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-1.5">Amount (₹) *</label><input type="number" name="amount" inputmode="decimal" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" step="0.01" required></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-1.5">Category</label><select name="category" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none"><option value="">Select...</option>@foreach(['rent','utilities','packaging','subscription','misc'] as $cat)<option value="{{ $cat }}">{{ ucfirst($cat) }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-1.5">Date *</label><input type="date" name="expense_date" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ date('Y-m-d') }}" required></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label><input type="text" name="notes" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none"></div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4 shrink-0">
                <button type="button" onclick="closeExpenseModal()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-brand-600 text-sm font-semibold text-white hover:bg-brand-700 transition-all">Save Expense</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openExpenseModal() {
        var b = document.getElementById('expenseModalBackdrop');
        b.classList.remove('hidden'); b.classList.add('flex');
        document.body.style.overflow = 'hidden';
        var first = b.querySelector('input,select'); if (first) first.focus();
    }
    function closeExpenseModal() {
        var b = document.getElementById('expenseModalBackdrop');
        b.classList.add('hidden'); b.classList.remove('flex');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeExpenseModal();
    });
</script>
@endpush
