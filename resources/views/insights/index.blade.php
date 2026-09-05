@extends('layouts.app')
@section('title', 'Insights')
@section('subtitle', 'Compare, rank & analyze your products and suppliers')

@section('styles')
<style>
    .insights-tab { display: none; }
    .insights-tab.active { display: block; }
    .tab-btn { border-bottom: 2px solid transparent; color: #6b7280; }
    .tab-btn.active { border-bottom-color: #059669; color: #047857; background: #ecfdf550; }
    .trend-chart-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .trend-chart-inner { min-width: 280px; }
</style>
@endsection

@section('content')
<div class="max-w-6xl mx-auto space-y-4">

    {{-- ═══════ TAB NAVIGATION ═══════ --}}
    <div class="flex overflow-x-auto gap-1 border-b border-gray-200 -mx-4 px-4 lg:mx-0 lg:px-0 pb-0" id="tabBar">
        <button data-tab="supplier" class="tab-btn active px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap touch-target flex items-center gap-1.5" onclick="switchTab('supplier')">
            <i class="bi bi-arrow-left-right"></i> Suppliers
        </button>
        <button data-tab="profit" class="tab-btn px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap touch-target flex items-center gap-1.5" onclick="switchTab('profit')">
            <i class="bi bi-graph-up-arrow"></i> Top Profit
        </button>
        <button data-tab="loss" class="tab-btn px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap touch-target flex items-center gap-1.5" onclick="switchTab('loss')">
            <i class="bi bi-graph-down-arrow"></i> Top Loss
        </button>
        <button data-tab="returns" class="tab-btn px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap touch-target flex items-center gap-1.5" onclick="switchTab('returns')">
            <i class="bi bi-arrow-return-left"></i> Returns
        </button>
        <button data-tab="health" class="tab-btn px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap touch-target flex items-center gap-1.5" onclick="switchTab('health')">
            <i class="bi bi-heart-pulse"></i> Health
        </button>
        <button data-tab="trend" class="tab-btn px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap touch-target flex items-center gap-1.5" onclick="switchTab('trend')">
            <i class="bi bi-calendar-range"></i> Trend
        </button>
    </div>

    {{-- ═══════ TAB 1: Supplier Comparison ═══════ --}}
    <div class="insights-tab active" id="tab-supplier">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 lg:p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-gray-900">Supplier Comparison</h2>
                <p class="text-xs text-gray-500 mt-0.5">Pick a product — see all suppliers side by side</p>
            </div>
            <div class="mb-4">
                <label class="text-xs font-medium text-gray-600 mb-1.5 block">Select Product</label>
                <select id="scProductSelect"
                        class="w-full lg:w-72 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 touch-target"
                        onchange="loadSupplierComparison(this.value)">
                    <option value="">Choose a product…</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                    @endforeach
                </select>
            </div>
            <div id="scResults">
                <div class="text-center py-12 text-gray-400 text-sm">
                    <i class="bi bi-arrow-left-right text-3xl mb-2 block opacity-40"></i>
                    Select a product to compare suppliers
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════ TAB 2: Top Profit ═══════ --}}
    <div class="insights-tab" id="tab-profit">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 lg:p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-gray-900">Top Profitable Products</h2>
                <p class="text-xs text-gray-500 mt-0.5">Ranked by net profit — highest earners first</p>
            </div>
            <div id="profitResults"></div>
        </div>
    </div>

    {{-- ═══════ TAB 3: Top Loss ═══════ --}}
    <div class="insights-tab" id="tab-loss">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 lg:p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-gray-900">Top Loss-Making Products</h2>
                <p class="text-xs text-gray-500 mt-0.5">Products causing the most loss — investigate these first</p>
            </div>
            <div id="lossResults"></div>
        </div>
    </div>

    {{-- ═══════ TAB 4: Returns ═══════ --}}
    <div class="insights-tab" id="tab-returns">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 lg:p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-gray-900">Return Rate Ranking</h2>
                <p class="text-xs text-gray-500 mt-0.5">Least returned first — your most reliable products</p>
            </div>
            <div id="returnResults"></div>
        </div>
    </div>

    {{-- ═══════ TAB 5: Health Score ═══════ --}}
    <div class="insights-tab" id="tab-health">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 lg:p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-gray-900">Combined Health Score</h2>
                <p class="text-xs text-gray-500 mt-0.5">Balances profit (60%) + low return rate (40%) — score 0 to 100</p>
            </div>
            <div id="healthResults"></div>
        </div>
    </div>

    {{-- ═══════ TAB 6: Time Trend ═══════ --}}
    <div class="insights-tab" id="tab-trend">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 lg:p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-gray-900">Time Trend</h2>
                <p class="text-xs text-gray-500 mt-0.5">How profit and return rate changed month by month</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-600 mb-1.5 block">Track</label>
                    <select id="trendType" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 touch-target"
                            onchange="updateTrendEntityOptions(this.value)">
                        <option value="">Choose type…</option>
                        <option value="product">Product</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </div>
                <div class="flex-1" id="trendEntityWrap" style="display:none">
                    <label class="text-xs font-medium text-gray-600 mb-1.5 block">Select</label>
                    <select id="trendEntitySelect" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 touch-target">
                        <option value="">Choose…</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" data-type="product">{{ $p->name }}</option>
                        @endforeach
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" data-type="supplier">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-24">
                    <label class="text-xs font-medium text-gray-600 mb-1.5 block">Months</label>
                    <select id="trendMonths" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 touch-target">
                        <option value="3">3</option>
                        <option value="6" selected>6</option>
                        <option value="12">12</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="loadTimeTrend()" class="px-4 py-2.5 bg-brand-600 text-white text-sm font-semibold rounded-xl hover:bg-brand-700 transition touch-target">
                        Show
                    </button>
                </div>
            </div>
            <div id="trendResults">
                <div class="text-center py-12 text-gray-400 text-sm">
                    <i class="bi bi-calendar-range text-3xl mb-2 block opacity-40"></i>
                    Select a product or supplier and click Show
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
var loadedTabs = {};

function switchTab(tab) {
    // Update buttons
    var btns = document.querySelectorAll('#tabBar .tab-btn');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.toggle('active', btns[i].getAttribute('data-tab') === tab);
    }
    // Show/hide panels
    var panels = document.querySelectorAll('.insights-tab');
    for (var j = 0; j < panels.length; j++) {
        panels[j].classList.toggle('active', panels[j].id === 'tab-' + tab);
    }
    // Lazy load data
    if (!loadedTabs[tab]) {
        loadedTabs[tab] = true;
        if (tab === 'profit') loadRankings('profit', 'profitResults');
        if (tab === 'loss') loadRankings('loss', 'lossResults');
        if (tab === 'returns') loadRankings('return_ratio', 'returnResults');
        if (tab === 'health') loadHealthScores();
    }
}

// ═══════════════════════════════════════════
// SUPPLIER COMPARISON
// ═══════════════════════════════════════════
function loadSupplierComparison(productId) {
    var box = document.getElementById('scResults');
    if (!productId) {
        box.innerHTML = '<div class="text-center py-12 text-gray-400 text-sm"><i class="bi bi-arrow-left-right text-3xl mb-2 block opacity-40"></i>Select a product to compare suppliers</div>';
        return;
    }
    box.innerHTML = '<div class="text-center py-8"><div class="inline-block w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div></div>';

    fetch('/insights/supplier-comparison?product_id=' + productId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.suppliers || data.suppliers.length === 0) {
                box.innerHTML = '<div class="text-center py-8 text-gray-400 text-sm">No supplier data for this product yet</div>';
                return;
            }
            var html = '<div class="grid gap-3">';
            data.suppliers.forEach(function(s, i) {
                var pc = s.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600';
                var pi = s.net_profit >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
                var rc = s.return_rate > 20 ? 'text-rose-600 bg-rose-50' : s.return_rate > 0 ? 'text-amber-600 bg-amber-50' : 'text-emerald-600 bg-emerald-50';
                var medal = i === 0 ? '\uD83E\uDD47' : i === 1 ? '\uD83E\uDD48' : i === 2 ? '\uD83E\uDD49' : '';

                html += '<div class="bg-gray-50 rounded-xl p-4 border border-gray-100">';
                html += '<div class="flex items-center justify-between gap-3 mb-3">';
                html += '<div class="flex items-center gap-2 min-w-0">';
                html += '<span class="text-lg shrink-0">' + medal + '</span>';
                html += '<h3 class="text-sm font-bold text-gray-900 truncate">' + s.supplier.name + '</h3>';
                html += '<span class="text-[10px] text-gray-400 font-medium shrink-0">' + s.batch_count + ' batch' + (s.batch_count > 1 ? 'es' : '') + '</span>';
                html += '</div>';
                html += '<div class="text-right shrink-0">';
                html += '<span class="text-base font-bold ' + pc + '"><i class="bi ' + pi + '"></i> \u20B9' + Math.abs(s.net_profit).toLocaleString('en-IN') + '</span>';
                html += '<span class="text-[10px] text-gray-400 block">' + (s.net_profit >= 0 ? 'profit' : 'loss') + '</span>';
                html += '</div></div>';

                html += '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">';
                html += '<div><p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wide">Supplied</p>';
                html += '<p class="text-sm font-bold text-gray-900">' + s.original_qty + ' units</p>';
                html += '<p class="text-[10px] text-gray-500">Avg \u20B9' + s.avg_cost + '/unit</p></div>';
                html += '<div><p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wide">Sold</p>';
                html += '<p class="text-sm font-bold text-emerald-600">' + s.sold_qty + ' units</p>';
                html += '<p class="text-[10px] text-gray-500">\u20B9' + s.revenue.toLocaleString('en-IN') + '</p></div>';
                html += '<div><p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wide">Returns+RTO</p>';
                html += '<p class="text-sm font-bold text-rose-600">' + s.returned_qty + ' units</p>';
                html += '<p class="text-[10px] text-gray-500">RTO: ' + s.rto_qty + '</p></div>';
                html += '<div><p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wide">Return Rate</p>';
                html += '<p class="text-sm font-bold ' + rc + ' inline-block px-2 py-0.5 rounded-lg">' + s.return_rate + '%</p>';
                if (s.missing_qty > 0) html += '<p class="text-[10px] text-rose-500 mt-0.5">Missing: ' + s.missing_qty + '</p>';
                html += '</div></div>';

                html += '<div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">';
                html += '<span>COGS: \u20B9' + s.cogs.toLocaleString('en-IN') + ' | Charges: \u20B9' + s.charges.toLocaleString('en-IN') + '</span>';
                html += '<a href="/products/' + data.product.id + '" class="text-brand-600 font-medium shrink-0">View \u2192</a></div>';
                html += '</div>';
            });
            html += '</div>';
            box.innerHTML = html;
        });
}

// ═══════════════════════════════════════════
// RANKING TABLES
// ═══════════════════════════════════════════
function loadRankings(type, containerId) {
    var box = document.getElementById(containerId);
    box.innerHTML = '<div class="text-center py-8"><div class="inline-block w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div></div>';

    fetch('/insights/rankings?type=' + type)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || data.length === 0) {
                box.innerHTML = '<div class="text-center py-8 text-gray-400 text-sm">No products yet</div>';
                return;
            }
            var html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b border-gray-100">';
            html += '<th class="text-left py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-10">#</th>';
            html += '<th class="text-left py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Product</th>';

            if (type === 'return_ratio') {
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Dispatched</th>';
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Returned</th>';
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Return %</th>';
            } else {
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Sold</th>';
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Revenue</th>';
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">COGS</th>';
                html += '<th class="text-right py-2.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Net Profit</th>';
            }
            html += '</tr></thead><tbody>';

            data.forEach(function(r) {
                var pc = r.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600';
                var medal = r.rank === 1 ? '\uD83E\uDD47' : r.rank === 2 ? '\uD83E\uDD48' : r.rank === 3 ? '\uD83E\uDD49' : '';

                html += '<tr class="border-b border-gray-50 hover:bg-gray-50 transition cursor-pointer" onclick="window.location=\'/products/' + r.product.id + '\'">';
                html += '<td class="py-3 px-2 font-bold text-gray-400"><span class="text-sm">' + (medal || r.rank) + '</span></td>';
                html += '<td class="py-3 px-2"><div class="font-semibold text-gray-900 text-sm">' + r.product.name + '</div><div class="text-[10px] text-gray-400">' + (r.product.sku || '') + '</div></td>';

                if (type === 'return_ratio') {
                    var rc = r.return_rate > 20 ? 'text-rose-600 bg-rose-50' : r.return_rate > 0 ? 'text-amber-600 bg-amber-50' : 'text-emerald-600 bg-emerald-50';
                    html += '<td class="py-3 px-2 text-right font-medium text-gray-700">' + r.dispatched + '</td>';
                    html += '<td class="py-3 px-2 text-right font-medium text-rose-600">' + r.returned_qty + '</td>';
                    html += '<td class="py-3 px-2 text-right"><span class="text-xs font-bold ' + rc + ' px-2 py-0.5 rounded-lg">' + r.return_rate + '%</span></td>';
                } else {
                    html += '<td class="py-3 px-2 text-right font-medium text-gray-700">' + r.sold_qty + '</td>';
                    html += '<td class="py-3 px-2 text-right text-gray-700">\u20B9' + r.revenue.toLocaleString('en-IN') + '</td>';
                    html += '<td class="py-3 px-2 text-right text-gray-500">\u20B9' + r.cogs.toLocaleString('en-IN') + '</td>';
                    html += '<td class="py-3 px-2 text-right"><span class="font-bold text-sm ' + pc + '">\u20B9' + Math.abs(r.net_profit).toLocaleString('en-IN') + '</span>' + (r.net_profit < 0 ? ' \u2193' : '') + '</td>';
                }
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            box.innerHTML = html;
        });
}

// ═══════════════════════════════════════════
// HEALTH SCORES
// ═══════════════════════════════════════════
function loadHealthScores() {
    var box = document.getElementById('healthResults');
    box.innerHTML = '<div class="text-center py-8"><div class="inline-block w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div></div>';

    fetch('/insights/health-scores')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || data.length === 0) {
                box.innerHTML = '<div class="text-center py-8 text-gray-400 text-sm">No products yet</div>';
                return;
            }
            var html = '<div class="space-y-2">';
            data.forEach(function(r) {
                var pc = r.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600';
                var sc = r.health_score >= 70 ? 'text-emerald-600 bg-emerald-50' : r.health_score >= 40 ? 'text-amber-600 bg-amber-50' : 'text-rose-600 bg-rose-50';
                var bc = r.health_score >= 70 ? 'bg-emerald-500' : r.health_score >= 40 ? 'bg-amber-500' : 'bg-rose-500';
                var medal = r.rank === 1 ? '\uD83E\uDD47' : r.rank === 2 ? '\uD83E\uDD48' : r.rank === 3 ? '\uD83E\uDD49' : '';

                html += '<a href="/products/' + r.product.id + '" class="block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-brand-200 hover:bg-brand-50/30 transition">';
                html += '<div class="flex items-center gap-3 mb-2">';
                html += '<span class="text-lg w-7">' + (medal || '<span class="text-xs font-bold text-gray-400">' + r.rank + '</span>') + '</span>';
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="font-semibold text-gray-900 text-sm truncate">' + r.product.name + '</div>';
                html += '<div class="text-[10px] text-gray-400">' + (r.product.sku || '') + '</div>';
                html += '</div>';
                html += '<span class="text-sm font-bold ' + sc + ' px-2.5 py-1 rounded-lg">' + r.health_score + '</span>';
                html += '</div>';
                html += '<div class="w-full bg-gray-200 rounded-full h-2 mb-2.5">';
                html += '<div class="' + bc + ' h-2 rounded-full transition-all" style="width:' + Math.max(r.health_score, 2) + '%"></div>';
                html += '</div>';
                html += '<div class="grid grid-cols-3 gap-2 text-xs">';
                html += '<div><span class="text-gray-400">Profit</span> <span class="font-bold ' + pc + '">\u20B9' + Math.abs(r.net_profit).toLocaleString('en-IN') + '</span></div>';
                html += '<div><span class="text-gray-400">Return</span> <span class="font-bold text-gray-700">' + r.return_rate + '%</span></div>';
                html += '<div><span class="text-gray-400">Sold</span> <span class="font-bold text-gray-700">' + r.sold_qty + '</span></div>';
                html += '</div></a>';
            });
            html += '</div>';
            box.innerHTML = html;
        });
}

// ═══════════════════════════════════════════
// TIME TREND
// ═══════════════════════════════════════════
function updateTrendEntityOptions(type) {
    var wrap = document.getElementById('trendEntityWrap');
    var sel = document.getElementById('trendEntitySelect');
    if (!type) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    for (var i = 0; i < sel.options.length; i++) {
        var opt = sel.options[i];
        if (opt.value && opt.dataset.type !== type) {
            opt.style.display = 'none';
        } else {
            opt.style.display = '';
        }
    }
    sel.value = '';
}

function loadTimeTrend() {
    var type = document.getElementById('trendType').value;
    var entityId = document.getElementById('trendEntitySelect').value;
    var months = document.getElementById('trendMonths').value;
    var box = document.getElementById('trendResults');

    if (!type || !entityId) {
        box.innerHTML = '<div class="text-center py-8 text-gray-400 text-sm">Please select both type and entity</div>';
        return;
    }

    box.innerHTML = '<div class="text-center py-8"><div class="inline-block w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div></div>';

    fetch('/insights/time-trend?type=' + type + '&entity_id=' + entityId + '&months=' + months)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || data.length === 0) {
                box.innerHTML = '<div class="text-center py-8 text-gray-400 text-sm">No data for this period</div>';
                return;
            }
            var maxP = 1, maxR = 1;
            data.forEach(function(d) {
                if (Math.abs(d.net_profit) > maxP) maxP = Math.abs(d.net_profit);
                if (d.return_rate > maxR) maxR = d.return_rate;
            });

            var html = '<div class="space-y-4">';

            // Profit chart
            html += '<div><h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Monthly Profit / Loss</h3>';
            html += '<div class="trend-chart-wrap"><div class="trend-chart-inner flex items-end gap-3 h-40">';
            data.forEach(function(d) {
                var h = maxP > 0 ? Math.abs(d.net_profit) / maxP * 100 : 0;
                var c = d.net_profit >= 0 ? 'bg-emerald-500' : 'bg-rose-400';
                var tc = d.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600';
                html += '<div class="flex-1 flex flex-col items-center justify-end h-full">';
                html += '<span class="text-[10px] font-bold ' + tc + ' mb-1">\u20B9' + Math.abs(d.net_profit).toLocaleString('en-IN') + '</span>';
                html += '<div class="w-full ' + c + ' rounded-t-lg" style="height:' + Math.max(h, 3) + '%"></div>';
                html += '<span class="text-[10px] text-gray-400 mt-1 font-medium">' + d.month.split(' ')[0] + '</span>';
                html += '</div>';
            });
            html += '</div></div></div>';

            // Return rate chart
            html += '<div class="pt-4 border-t border-gray-100"><h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Monthly Return Rate</h3>';
            html += '<div class="trend-chart-wrap"><div class="trend-chart-inner flex items-end gap-3 h-28">';
            data.forEach(function(d) {
                var h = maxR > 0 ? d.return_rate / maxR * 100 : 0;
                var c = d.return_rate > 20 ? 'bg-rose-400' : d.return_rate > 0 ? 'bg-amber-400' : 'bg-emerald-400';
                html += '<div class="flex-1 flex flex-col items-center justify-end h-full">';
                html += '<span class="text-[10px] font-bold text-gray-600 mb-1">' + d.return_rate + '%</span>';
                html += '<div class="w-full ' + c + ' rounded-t-lg" style="height:' + Math.max(h, d.return_rate > 0 ? 8 : 2) + '%"></div>';
                html += '<span class="text-[10px] text-gray-400 mt-1 font-medium">' + d.month.split(' ')[0] + '</span>';
                html += '</div>';
            });
            html += '</div></div></div>';

            // Detail table
            html += '<div class="pt-4 border-t border-gray-100"><h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Details</h3>';
            html += '<div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr class="border-b border-gray-100">';
            html += '<th class="text-left py-2 px-2 text-gray-400 font-bold">Month</th>';
            html += '<th class="text-right py-2 px-2 text-gray-400 font-bold">Sold</th>';
            html += '<th class="text-right py-2 px-2 text-gray-400 font-bold">Revenue</th>';
            html += '<th class="text-right py-2 px-2 text-gray-400 font-bold">COGS</th>';
            html += '<th class="text-right py-2 px-2 text-gray-400 font-bold">Net</th>';
            html += '<th class="text-right py-2 px-2 text-gray-400 font-bold">Return %</th>';
            html += '</tr></thead><tbody>';
            data.forEach(function(d) {
                var pc = d.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600';
                html += '<tr class="border-b border-gray-50">';
                html += '<td class="py-2 px-2 font-medium text-gray-700">' + d.month + '</td>';
                html += '<td class="py-2 px-2 text-right text-gray-700">' + d.sold_qty + '</td>';
                html += '<td class="py-2 px-2 text-right text-gray-700">\u20B9' + d.revenue.toLocaleString('en-IN') + '</td>';
                html += '<td class="py-2 px-2 text-right text-gray-500">\u20B9' + d.cogs.toLocaleString('en-IN') + '</td>';
                html += '<td class="py-2 px-2 text-right font-bold ' + pc + '">\u20B9' + d.net_profit.toLocaleString('en-IN') + '</td>';
                html += '<td class="py-2 px-2 text-right font-medium text-gray-700">' + d.return_rate + '%</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div></div>';
            html += '</div>';
            box.innerHTML = html;
        });
}
</script>
@endsection
