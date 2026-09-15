@extends('layouts.app')
@section('title', 'Platform Settlements')
@section('subtitle', 'What each marketplace has paid, and what it still owes')

@section('actions')
<button onclick="openSettlementModal()" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">
    <i class="bi bi-plus-lg"></i> <span class="hidden sm:inline">Record Settlement</span><span class="sm:hidden">Add</span>
</button>
@endsection

@section('content')
@php $t = $overview['totals']; @endphp

{{-- ═══════ WHAT AM I STILL OWED? ═══════ --}}
<x-card title="Money owed to you" subtitle="Earned (sales minus platform fees) vs actually banked — all time">
    @if($t['expected_net'] == 0 && $t['banked'] == 0)
        <p class="text-sm text-gray-400 text-center py-3">No sales or payouts recorded yet.</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Earned (net)</p>
                <p class="mt-1 text-lg font-bold text-gray-900">₹{{ number_format($t['expected_net']) }}</p>
                <p class="text-[10px] text-gray-400">₹{{ number_format($t['gross']) }} gross − ₹{{ number_format($t['fees']) }} fees</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Banked</p>
                <p class="mt-1 text-lg font-bold text-emerald-600">₹{{ number_format($t['banked']) }}</p>
                <p class="text-[10px] text-gray-400">payouts received</p>
            </div>
            <div class="rounded-xl border-2 {{ $t['owed'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider {{ $t['owed'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Still owed</p>
                <p class="mt-1 text-lg font-bold {{ $t['owed'] > 0 ? 'text-amber-700' : 'text-emerald-600' }}">₹{{ number_format($t['owed']) }}</p>
                <p class="text-[10px] {{ $t['owed'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">across all platforms</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Pending payouts</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ $t['pending_count'] }}</p>
                <p class="text-[10px] text-gray-400">recorded, not received</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-100">
                    <th class="py-2.5 px-2 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform</th>
                    <th class="py-2.5 px-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Earned (net)</th>
                    <th class="py-2.5 px-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Banked</th>
                    <th class="py-2.5 px-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Owed</th>
                    <th class="py-2.5 px-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Oldest unpaid</th>
                </tr></thead>
                <tbody>
                    @foreach($overview['platforms'] as $row)
                        @if($row['expected_net'] != 0 || $row['banked'] != 0)
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 px-2">
                                <span class="font-semibold text-gray-900">{{ $row['platform']->name }}</span>
                                <span class="block text-[10px] text-gray-400">{{ $row['orders'] }} order(s)</span>
                            </td>
                            <td class="py-2.5 px-2 text-right text-gray-700">₹{{ number_format($row['expected_net']) }}</td>
                            <td class="py-2.5 px-2 text-right text-emerald-600">₹{{ number_format($row['banked']) }}</td>
                            <td class="py-2.5 px-2 text-right font-bold {{ $row['owed'] > 0 ? 'text-amber-700' : 'text-gray-400' }}">₹{{ number_format($row['owed']) }}</td>
                            <td class="py-2.5 px-2 text-right">
                                @if($row['pending_count'] > 0)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-lg {{ $row['oldest_age_days'] > 30 ? 'text-rose-600 bg-rose-50' : ($row['oldest_age_days'] > 15 ? 'text-amber-600 bg-amber-50' : 'text-gray-600 bg-gray-100') }}">
                                        {{ $row['oldest_age_days'] > 0 ? $row['oldest_age_days'].' days' : 'not due yet' }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-[11px] text-gray-400">Record a payout to move money from “owed” to “banked”. A settlement can also be created straight from your sales — see “Fill from sales” in the form.</p>
    @endif
</x-card>

{{-- ═══════ PAYOUT LIST ═══════ --}}
<x-card padding="false" class="mt-6">
    <x-slot:action>
        <form class="flex gap-2 flex-wrap" method="GET">
            <select name="platform_id" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                <option value="">All Platforms</option>
                @foreach($platforms as $p)<option value="{{ $p->id }}" {{ request('platform_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach
            </select>
            <select name="status" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                <option value="">All</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received</option>
            </select>
            <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><i class="bi bi-funnel"></i></button>
        </form>
    </x-slot:action>

    {{-- Mobile --}}
    <div class="md:hidden divide-y divide-gray-50">
        @forelse($settlements as $s)
        <div class="p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">₹{{ number_format($s->net_amount) }} net</p>
                    <p class="text-[11px] text-gray-400">
                        {{ $s->period_start?->format('d M') }} – {{ $s->period_end?->format('d M Y') }}
                    </p>
                </div>
                <x-badge variant="info">{{ $s->platform->name }}</x-badge>
            </div>
            <div class="mt-2 flex items-center gap-2 flex-wrap text-[11px] text-gray-500">
                <span>Gross ₹{{ number_format($s->gross_amount) }}</span>
                <span>·</span>
                <span>Fees ₹{{ number_format($s->fees_amount) }} ({{ $s->feeRate() }}%)</span>
            </div>
            <div class="mt-2 flex items-center justify-between gap-2">
                @if($s->isReceived())
                    <x-badge variant="success">Received {{ $s->received_on->format('d M Y') }}</x-badge>
                @else
                    <x-badge :variant="$s->urgency() >= 3 ? 'danger' : ($s->urgency() === 2 ? 'warning' : 'default')">
                        Pending{{ $s->ageInDays() > 0 ? ' · '.$s->ageInDays().'d overdue' : '' }}
                    </x-badge>
                @endif
                <div class="flex items-center gap-1">
                    @include('accounting._settlement-actions', ['s' => $s])
                </div>
            </div>
        </div>
        @empty
        <x-empty-state icon="cash-coin" title="No payouts recorded" description="Record a marketplace payout to reconcile what you are owed." />
        @endforelse
    </div>

    {{-- Desktop --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-gray-100">
                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Period</th>
                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Platform</th>
                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Gross</th>
                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Fees</th>
                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Net</th>
                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($settlements as $s)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3 text-gray-600 whitespace-nowrap">{{ $s->period_start?->format('d M') }} – {{ $s->period_end?->format('d M Y') }}</td>
                    <td class="px-5 py-3"><x-badge variant="info">{{ $s->platform->name }}</x-badge></td>
                    <td class="px-5 py-3 text-right text-gray-600">₹{{ number_format($s->gross_amount) }}</td>
                    <td class="px-5 py-3 text-right text-gray-500">₹{{ number_format($s->fees_amount) }} <span class="text-[10px] text-gray-400">({{ $s->feeRate() }}%)</span></td>
                    <td class="px-5 py-3 text-right font-bold text-gray-900">₹{{ number_format($s->net_amount) }}</td>
                    <td class="px-5 py-3">
                        @if($s->isReceived())
                            <x-badge variant="success">Received {{ $s->received_on->format('d M') }}</x-badge>
                        @else
                            <x-badge :variant="$s->urgency() >= 3 ? 'danger' : ($s->urgency() === 2 ? 'warning' : 'default')">
                                Pending{{ $s->ageInDays() > 0 ? ' · '.$s->ageInDays().'d' : '' }}
                            </x-badge>
                        @endif
                        @if($s->reference)<p class="text-[10px] text-gray-400 mt-1">{{ $s->reference }}</p>@endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        @include('accounting._settlement-actions', ['s' => $s])
                    </td>
                </tr>
                @empty
                <tr><td colspan="7"><x-empty-state icon="cash-coin" title="No payouts recorded" description="Record a marketplace payout to reconcile what you are owed." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($settlements->hasPages())<div class="border-t border-gray-100 px-5 py-3">{{ $settlements->withQueryString()->links() }}</div>@endif
</x-card>

{{-- ═══════ MODAL ═══════ --}}
<div id="settlementModalBackdrop" class="fixed inset-0 z-[120] hidden items-end sm:items-center justify-center bg-gray-900/50 backdrop-blur-sm p-0 sm:p-4" onclick="if(event.target===this)closeSettlementModal()">
    <div class="w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] flex flex-col" role="dialog" aria-modal="true" aria-label="Record Settlement">
        <form action="{{ route('accounting.settlements.store') }}" method="POST" id="settlementForm" class="flex flex-col min-h-0">
            @csrf
            <input type="hidden" name="_method" id="settlementMethod" value="POST">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 shrink-0">
                <h6 class="text-sm font-bold text-gray-900" id="settlementModalTitle">Record Settlement</h6>
                <button type="button" onclick="closeSettlementModal()" aria-label="Close" class="p-2 -mr-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="px-5 py-5 space-y-4 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform *</label>
                        <select name="platform_id" id="stPlatform" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required>
                            <option value="">Select...</option>
                            @foreach($platforms as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Reference</label>
                        <input type="text" name="reference" id="stReference" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="Payout / bank reference">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-sm font-semibold text-gray-700">Sales period *</label>
                        <button type="button" onclick="fillFromSales()" class="text-[11px] font-semibold text-brand-600 hover:text-brand-700">
                            <i class="bi bi-magic"></i> Fill from sales
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="date" name="period_start" id="stPeriodStart" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required>
                        <input type="date" name="period_end" id="stPeriodEnd" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400" id="stSuggestNote">“Fill from sales” reads your successful orders for that period.</p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Gross (₹) *</label>
                        <input type="number" name="gross_amount" id="stGross" step="0.01" min="0" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Fees (₹)</label>
                        <input type="number" name="fees_amount" id="stFees" step="0.01" min="0" value="0" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Net (₹)</label>
                        <input type="number" name="net_amount" id="stNet" step="0.01" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Expected on</label>
                        <input type="date" name="expected_on" id="stExpected" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Received on</label>
                        <input type="date" name="received_on" id="stReceived" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none">
                        <p class="mt-1 text-[11px] text-gray-400">Leave blank while the payout is still owed.</p>
                        @error('received_on')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                    <input type="text" name="notes" id="stNotes" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" placeholder="e.g. weekly settlement">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4 shrink-0">
                <button type="button" onclick="closeSettlementModal()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-brand-600 text-sm font-semibold text-white hover:bg-brand-700 transition-all" id="settlementSubmit">Save Settlement</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var settlementStoreUrl = "{{ route('accounting.settlements.store') }}";
    var settlementSuggestUrl = "{{ route('accounting.settlements.suggest') }}";

    function openSettlementModal(data) {
        var form = document.getElementById('settlementForm');
        var method = document.getElementById('settlementMethod');

        if (data) {
            form.action = data.action;
            method.value = 'PATCH';
            document.getElementById('settlementModalTitle').textContent = 'Edit Settlement';
            document.getElementById('settlementSubmit').textContent = 'Save Changes';
            setField('platform_id', data.platform_id);
            setField('period_start', data.period_start);
            setField('period_end', data.period_end);
            setField('gross_amount', data.gross_amount);
            setField('fees_amount', data.fees_amount);
            setField('net_amount', data.net_amount);
            setField('expected_on', data.expected_on);
            setField('received_on', data.received_on);
            setField('reference', data.reference);
            setField('notes', data.notes);
        } else {
            form.action = settlementStoreUrl;
            method.value = 'POST';
            document.getElementById('settlementModalTitle').textContent = 'Record Settlement';
            document.getElementById('settlementSubmit').textContent = 'Save Settlement';
            form.reset();
            var today = new Date();
            var weekAgo = new Date(today.getTime() - 7 * 86400000);
            var inAWeek = new Date(today.getTime() + 7 * 86400000);
            setField('period_start', iso(weekAgo));
            setField('period_end', iso(today));
            setField('expected_on', iso(inAWeek));
            setField('fees_amount', '0');
        }

        var backdrop = document.getElementById('settlementModalBackdrop');
        backdrop.classList.remove('hidden');
        backdrop.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeSettlementModal() {
        var backdrop = document.getElementById('settlementModalBackdrop');
        backdrop.classList.add('hidden');
        backdrop.classList.remove('flex');
        document.body.style.overflow = '';
    }

    function setField(name, value) {
        var el = document.getElementById('settlementForm').querySelector('[name="' + name + '"]');
        if (el) el.value = value === null || value === undefined ? '' : value;
    }

    function iso(date) {
        return date.toISOString().slice(0, 10);
    }

    // Net is gross minus fees unless the user overrides it.
    function recalcNet() {
        var gross = parseFloat(document.getElementById('stGross').value) || 0;
        var fees = parseFloat(document.getElementById('stFees').value) || 0;
        document.getElementById('stNet').value = (gross - fees).toFixed(2);
    }

    document.getElementById('stGross').addEventListener('input', recalcNet);
    document.getElementById('stFees').addEventListener('input', recalcNet);

    function fillFromSales() {
        var platformId = document.getElementById('stPlatform').value;
        var from = document.getElementById('stPeriodStart').value;
        var to = document.getElementById('stPeriodEnd').value;
        var note = document.getElementById('stSuggestNote');

        if (!platformId || !from || !to) {
            note.textContent = 'Pick a platform and a period first.';
            note.className = 'mt-1 text-[11px] text-rose-600';
            return;
        }

        note.textContent = 'Reading your sales…';
        note.className = 'mt-1 text-[11px] text-gray-400';

        fetch(settlementSuggestUrl + '?platform_id=' + encodeURIComponent(platformId) + '&from=' + from + '&to=' + to, {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.errors) {
                note.textContent = 'Could not read those sales.';
                note.className = 'mt-1 text-[11px] text-rose-600';
                return;
            }

            document.getElementById('stGross').value = data.gross_amount;
            document.getElementById('stFees').value = data.fees_amount;
            document.getElementById('stNet').value = data.net_amount;

            note.textContent = data.orders > 0
                ? data.orders + ' successful order(s) in this period: gross ₹' + data.gross_amount.toLocaleString('en-IN') + ', fees ₹' + data.fees_amount.toLocaleString('en-IN') + '.'
                : 'No successful sales in this period.';
            note.className = 'mt-1 text-[11px] ' + (data.orders > 0 ? 'text-emerald-600' : 'text-amber-600');
        }).catch(function() {
            note.textContent = 'Could not read those sales.';
            note.className = 'mt-1 text-[11px] text-rose-600';
        });
    }
</script>
@endpush
