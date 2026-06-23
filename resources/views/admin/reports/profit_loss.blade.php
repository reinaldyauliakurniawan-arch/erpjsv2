<x-app-layout>
<x-slot name="title">Profit & Loss</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Profit & Loss</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan dan beban dalam periode tertentu</p>
        </div>
        <button onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">
            <span class="material-symbols-outlined text-base">download</span>
            Export CSV
        </button>
    </div>

    {{-- Filter --}}
    <div class="app-card">
        <form method="GET" action="{{ route('finance.reports.profit-loss') }}" class="flex items-end gap-md flex-wrap">
            <div class="fieldset">
                <label class="fieldset-legend">Periode</label>
                <select name="period" id="period-select" class="select select-sm" onchange="toggleCustom(this)">
                    <option value="month" {{ ($period??'month')=='month'?'selected':'' }}>Bulan Ini</option>
                    <option value="quarter" {{ ($period??'')=='quarter'?'selected':'' }}>Kuartal Ini</option>
                    <option value="year" {{ ($period??'')=='year'?'selected':'' }}>Tahun Ini</option>
                    <option value="custom" {{ ($period??'')=='custom'?'selected':'' }}>Custom</option>
                </select>
            </div>
            <div id="custom-range" class="flex gap-sm items-end {{ ($period??'')=='custom' ? '' : 'hidden' }}">
                <div class="fieldset">
                    <label class="fieldset-legend">Dari</label>
                    <input type="date" name="from" class="input input-sm" value="{{ $from }}">
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend">Sampai</label>
                    <input type="date" name="to" class="input input-sm" value="{{ $to }}">
                </div>
            </div>
            <button type="submit" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Pendapatan</p>
            <p class="text-xl font-bold text-success mt-xs">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Beban</p>
            <p class="text-xl font-bold text-error mt-xs">Rp {{ number_format($totalExpense, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Net Profit</p>
            <p class="text-xl font-bold mt-xs {{ $netProfit >= 0 ?'text-success' : 'text-error' }}">
                Rp {{ number_format($netProfit, 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Tabulator --}}
    <div class="app-card">
        <div id="pl-table"></div>
    </div>

</div>

<script>
const plData = @json($rows->values());

function fmt(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
}

document.addEventListener('DOMContentLoaded', function() {
    new Tabulator("#pl-table", {
        data: plData,
        layout: "fitColumns",
        pagination: "local",
        paginationSize: 30,
        initialSort: [{ column: "type", dir: "asc" }],
        columns: [
            { title: "Tipe", field: "type", width: 110,
              formatter: cell => `<span class="badge badge-soft text-xs">${cell.getValue()}</span>` },
            { title: "Kode", field: "code", width: 100, headerFilter: "input" },
            { title: "Nama Akun", field: "name", minWidth: 200, headerFilter: "input" },
            { title: "Jumlah", field: "amount", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
        ],
    });
});

function toggleCustom(sel) {
    document.getElementById('custom-range').classList.toggle('hidden', sel.value !== 'custom');
}

function exportCSV() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = `{{ route("finance.exports.profit-loss") }}?${params.toString()}`;
}
</script>
</x-app-layout>
