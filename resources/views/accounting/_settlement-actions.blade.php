@php
    // Edit form data, read by openSettlementModal() on the page.
    $payload = [
        'action' => route('accounting.settlements.update', $s),
        'platform_id' => $s->platform_id,
        'period_start' => $s->period_start?->format('Y-m-d'),
        'period_end' => $s->period_end?->format('Y-m-d'),
        'gross_amount' => (float) $s->gross_amount,
        'fees_amount' => (float) $s->fees_amount,
        'net_amount' => (float) $s->net_amount,
        'expected_on' => $s->expected_on?->format('Y-m-d'),
        'received_on' => $s->received_on?->format('Y-m-d'),
        'reference' => $s->reference,
        'notes' => $s->notes,
    ];
@endphp

<form action="{{ route('accounting.settlements.received', $s) }}" method="POST" class="inline">
    @csrf @method('PATCH')
    <input type="hidden" name="received" value="{{ $s->isReceived() ? 0 : 1 }}">
    <button type="submit"
            aria-label="{{ $s->isReceived() ? 'Move back to pending' : 'Mark as received' }}"
            title="{{ $s->isReceived() ? 'Move back to pending' : 'Mark payout as received' }}"
            class="touch-target flex items-center justify-center p-1.5 rounded-lg transition-all {{ $s->isReceived() ? 'text-amber-500 hover:text-amber-700 hover:bg-amber-50' : 'text-emerald-500 hover:text-emerald-700 hover:bg-emerald-50' }}">
        <i class="bi bi-{{ $s->isReceived() ? 'arrow-counterclockwise' : 'check2-circle' }} text-sm"></i>
    </button>
</form>

<button type="button" aria-label="Edit settlement" title="Edit"
        onclick='openSettlementModal(@json($payload))'
        class="touch-target flex items-center justify-center p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-all">
    <i class="bi bi-pencil text-sm"></i>
</button>

<form action="{{ route('accounting.settlements.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('Delete this payout record?')">
    @csrf @method('DELETE')
    <button type="submit" aria-label="Delete settlement" title="Delete"
            class="touch-target flex items-center justify-center p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-all">
        <i class="bi bi-trash text-sm"></i>
    </button>
</form>
