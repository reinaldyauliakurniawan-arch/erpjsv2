<x-app-layout>
<x-slot name="title">Adjusted Trial Balance</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Adjusted Trial Balance</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Trial Balance setelah Jurnal Penyesuaian (AJP)</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <div class="flex items-end gap-md flex-wrap">
            <div class="fieldset">
                <label class="fieldset-legend">Periode</label>
                <select id="filter-period" class="select select-sm" onchange="toggleCustom(this)">
                    <option value="all" {{ !$from ? 'selected' : '' }}>Semua</option>
                    <option value="month">Bulan Ini</option>
                    <option value="quarter">Kuartal Ini</option>
                    <option value="year">Tahun Ini</option>
                    <option value="custom" {{ $from ? 'selected' : '' }}>Custom</option>
                </select>
            </div>
            <div id="custom-range" class="flex gap-sm items-end {{ $from ? '' : 'hidden' }}">
                <div class="fieldset">
                    <label class="fieldset-legend">Dari</label>
                    <input type="date" id="filter-from" class="input input-sm" value="{{ $from }}">
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend">Sampai</label>
                    <input type="date" id="filter-to" class="input input-sm" value="{{ $to }}">
                </div>
            </div>
            <button onclick="applyFilter()" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Saldo Pre-Adjustment</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalPreSaldo, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Adj Debit</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalAdjDebit, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Adj Kredit</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalAdjCredit, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Saldo Adjusted</p>
            <p class="text-xl font-bold mt-xs {{ abs($totalAdjDebit - $totalAdjCredit) < 0.001 ? 'text-success' : 'text-error' }}">
                Rp {{ number_format($totalAdjustedSaldo, 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-md text-xs text-on-surface-variant">
        <span class="flex items-center gap-xs">
            <span class="inline-block w-3 h-3 rounded-sm bg-warning opacity-30"></span>
            Akun yang terkena AJP
        </span>
    </div>

    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <div id="atb-table"></div>
    </div>

</div>

<script>
const rawData = @json($rows);

function fmt(n) {
    if (n === null || n === undefined || n == 0) return '—';
    const abs = Math.abs(n);
    const str = 'Rp ' + new Intl.NumberFormat('id-ID').format(abs);
    return n < 0 ? `<span class="text-error">(${str})</span>` : str;
}

function fmtAdj(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
}

document.addEventListener('DOMContentLoaded', function () {
    new Tabulator("#atb-table", {
        data: rawData,
        layout: "fitColumns",
        pagination: "local",
        paginationSize: 30,
        rowFormatter: function(row) {
            if (row.getData().has_adj) {
                row.getElement().style.backgroundColor = 'oklch(var(--wa) / 0.08)';
            }
        },
        columns: [
            { title: "Kode", field: "code", width: 90, headerFilter: "input" },
            { title: "Nama Akun", field: "name", minWidth: 180, headerFilter: "input" },
            {
                title: "Tipe", field: "type", width: 100,
                formatter: cell => `<span class="badge badge-soft text-xs">${cell.getValue()}</span>`
            },
            {
                title: "Pre-Adjustment",
                field: "pre_saldo",
                hozAlign: "right",
                formatter: cell => fmt(cell.getValue()),
                bottomCalc: "sum",
                bottomCalcFormatter: cell => `<span class="font-semibold">${fmt(cell.getValue())}</span>`,
            },
            {
                title: "Adj Debit",
                field: "adj_debit",
                hozAlign: "right",
                formatter: cell => fmtAdj(cell.getValue()),
                bottomCalc: "sum",
                bottomCalcFormatter: cell => `<span class="font-semibold">${fmtAdj(cell.getValue())}</span>`,
            },
            {
                title: "Adj Kredit",
                field: "adj_credit",
                hozAlign: "right",
                formatter: cell => fmtAdj(cell.getValue()),
                bottomCalc: "sum",
                bottomCalcFormatter: cell => `<span class="font-semibold">${fmtAdj(cell.getValue())}</span>`,
            },
            {
                title: "Adjusted Saldo",
                field: "adjusted_saldo",
                hozAlign: "right",
                formatter: cell => fmt(cell.getValue()),
                bottomCalc: "sum",
                bottomCalcFormatter: cell => `<span class="font-semibold">${fmt(cell.getValue())}</span>`,
            },
        ],
    });
});

function toggleCustom(sel) {
    document.getElementById('custom-range').classList.toggle('hidden', sel.value !== 'custom');
}

function getPeriodRange(period) {
    const now = new Date();
    let from, to = now.toISOString().split('T')[0];
    if (period === 'month') {
        from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    } else if (period === 'quarter') {
        const q = Math.floor(now.getMonth() / 3);
        from = new Date(now.getFullYear(), q * 3, 1).toISOString().split('T')[0];
    } else if (period === 'year') {
        from = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
    }
    return { from, to };
}

function applyFilter() {
    const period = document.getElementById('filter-period').value;
    if (period === 'all') {
        window.location.href = '{{ route("finance.reports.adjusted-trial-balance") }}';
        return;
    }
    let from, to;
    if (period === 'custom') {
        from = document.getElementById('filter-from').value;
        to   = document.getElementById('filter-to').value;
    } else {
        ({ from, to } = getPeriodRange(period));
    }
    window.location.href = `{{ route("finance.reports.adjusted-trial-balance") }}?from=${from}&to=${to}`;
}
</script>
</x-app-layout>
