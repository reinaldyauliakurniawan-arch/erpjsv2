<x-app-layout>
<x-slot name="title">Trial Balance</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Trial Balance</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Saldo semua akun dari seluruh jurnal</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">
            <span class="material-symbols-outlined text-base">download</span>
            Export CSV
        </button>
    </div>

    {{-- Filter --}}
    <div class="app-card">
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
            <div id="custom-range" class="flex gap-sm items-end {{ $from ?'' : 'hidden' }}">
                <div class="fieldset">
                    <label class="fieldset-legend">Dari</label>
                    <input type="date" id="filter-from" class="input input-sm" value="{{ $from }}">
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend">Sampai</label>
                    <input type="date" id="filter-to" class="input input-sm" value="{{ $to }}">
                </div>
            </div>
            <button type="button" onclick="applyFilter()" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid gap-md" style="grid-template-columns:1fr 1fr 1fr;">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Debit</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalDebit,0,',','.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Kredit</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalCredit,0,',','.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Status</p>
            <p class="text-headline-md font-bold mt-xs {{ abs($totalDebit-$totalCredit)<0.001 ?'text-success' : 'text-error' }}">
                {{ abs($totalDebit-$totalCredit)<0.001 ? 'Balance ✓' : 'Tidak Balance ✗' }}
            </p>
        </div>
    </div>

    <div class="app-card">
        <div id="trial-balance-table"></div>
    </div>

</div>

<script>
const rawData = @json($rows->values());

function fmt(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
}

const tableData = rawData.map(r => {
    const debit  = parseFloat(r.debit)  || 0;
    const credit = parseFloat(r.credit) || 0;
    const normalDebet = ['Asset', 'Expense'];
    let saldo_debet = 0, saldo_kredit = 0;
    if (normalDebet.includes(r.type)) {
        if (debit >= credit) saldo_debet  = debit - credit;
        else                 saldo_kredit = credit - debit;
    } else {
        if (credit >= debit) saldo_kredit = credit - debit;
        else                 saldo_debet  = debit - credit;
    }
    return { ...r, saldo_debet, saldo_kredit };
});

document.addEventListener('DOMContentLoaded', function() {
new Tabulator("#trial-balance-table", {
    data: tableData,
    layout: "fitColumns",
    pagination: "local",
    paginationSize: 25,
    columns: [
        { title: "Kode", field: "code", width: 100, headerFilter: "input" },
        { title: "Nama Akun", field: "name", minWidth: 180, headerFilter: "input" },
        { title: "Tipe", field: "type", width: 110,
          formatter: cell => `<span class="badge badge-soft text-xs">${cell.getValue()}</span>` },
        { title: "Debit", field: "debit", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
        { title: "Kredit", field: "credit", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
        { title: "Saldo Debet", field: "saldo_debet", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
        { title: "Saldo Kredit", field: "saldo_kredit", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
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
        window.location.href = '{{ route("finance.reports.trial-balance") }}';
        return;
    }
    let from, to;
    if (period === 'custom') {
        from = document.getElementById('filter-from').value;
        to   = document.getElementById('filter-to').value;
        if (!from || !to) return;
    } else {
        ({ from, to } = getPeriodRange(period));
    }
    window.location.href = `{{ route("finance.reports.trial-balance") }}?from=${from}&to=${to}`;
}

function exportCSV() {
    const from = '{{ $from }}';
    const to   = '{{ $to }}';
    let params = '';
    if (from && to) params = `?from=${from}&to=${to}`;
    window.location.href = `{{ route("finance.exports.trial-balance") }}${params}`;
}
</script>
</x-app-layout>
