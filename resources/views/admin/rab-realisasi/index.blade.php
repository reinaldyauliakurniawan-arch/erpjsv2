<x-app-layout>
<x-slot name="title">Realisasi RAB</x-slot>

<div class="p-lg space-y-md">

    {{-- Flash --}}
    @if(session('success'))
        <div role="alert" class="alert alert-success alert-soft">
            <span class="material-symbols-outlined">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between gap-md flex-wrap">
        <div>
            <h3 class="text-headline-lg font-semibold text-on-surface">Realisasi RAB</h3>
            <p class="text-sm text-on-surface-variant mt-xs">Monitoring realisasi anggaran otomatis dari jurnal keuangan</p>
        </div>
        <div class="flex gap-sm items-center flex-wrap">
            <form method="GET" action="{{ route('finance.rab-realisasi.index') }}" class="flex gap-sm items-center">
                <select name="year" class="select select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('finance.rab.index') }}" class="btn btn-ghost btn-sm gap-xs">
                <span class="material-symbols-outlined text-[16px]">edit</span>
                Edit RAB
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(4, 1fr)">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Anggaran {{ $year }}</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalBudget, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Realisasi Saat Ini</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalReal, 0, ',', '.') }}</p>
            <div class="mt-sm space-y-xs">
                <div class="flex justify-between text-xs text-on-surface-variant">
                    <span>Progress</span>
                    <span>{{ $pctOverall }}%</span>
                </div>
                <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden">
                    <div class="h-full rounded-full {{ $pctOverall >= 95 ?'bg-error' : ($pctOverall >= 80 ? 'bg-warning' : 'bg-success') }}"
                         style="width: {{ min($pctOverall, 100) }}%"></div>
                </div>
            </div>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Realisasi Q{{ $currentQuarter }} (Berjalan)</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($realQCurrent, 0, ',', '.') }}</p>
            <div class="mt-sm space-y-xs">
                <div class="flex justify-between text-xs text-on-surface-variant">
                    <span>dari Rp {{ number_format($budgetQCurrent, 0, ',', '.') }}</span>
                    <span>{{ $pctQCurrent }}%</span>
                </div>
                <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden">
                    <div class="h-full rounded-full {{ $pctQCurrent >= 95 ?'bg-error' : ($pctQCurrent >= 80 ? 'bg-warning' : 'bg-success') }}"
                         style="width: {{ min($pctQCurrent, 100) }}%"></div>
                </div>
            </div>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Status Akun <span id="filter-reset" class="text-xs font-normal normal-case hidden cursor-pointer text-primary underline ml-1" onclick="resetStatusFilter()">(reset filter)</span><span id="filter-hint" class="text-xs font-normal normal-case">(klik untuk filter)</span></p>
            <div class="flex gap-md mt-xs">
                <div onclick="filterByStatus('Aman')" class="cursor-pointer hover:opacity-70 transition-opacity">
                    <p class="text-headline-md font-bold text-success">{{ $totalAman }}</p>
                    <p class="text-xs text-on-surface-variant">Aman</p>
                </div>
                <div onclick="filterByStatus('Waspada')" class="cursor-pointer hover:opacity-70 transition-opacity">
                    <p class="text-headline-md font-bold text-warning">{{ $rows->where('status', 'Waspada')->count() }}</p>
                    <p class="text-xs text-on-surface-variant">Waspada</p>
                </div>
                <div onclick="filterByStatus('Kritis')" class="cursor-pointer hover:opacity-70 transition-opacity">
                    <p class="text-headline-md font-bold text-error">{{ $totalKritis }}</p>
                    <p class="text-xs text-on-surface-variant">Kritis</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="app-card space-y-md">
        @if($rows->isEmpty())
            <div class="py-12 flex flex-col items-center justify-center text-on-surface-variant">
                <span class="material-symbols-outlined text-5xl opacity-20 mb-sm">monitoring</span>
                <p class="text-sm">Belum ada data RAB untuk tahun {{ $year }}. <a href="{{ route('finance.rab.index') }}" class="text-primary underline">Input RAB terlebih dahulu.</a></p>
            </div>
        @else
            <div id="realisasi-table"></div>
        @endif
    </div>

</div>

<script>
const realisasiData = @json($rows->values());

function fmt(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
}

function statusBadge(status) {
    const map = {
        'Aman':    'badge badge-soft badge-success text-xs',
        'Waspada': 'badge badge-soft badge-warning text-xs',
        'Kritis':  'badge badge-soft badge-error text-xs',
    };
    return `<span class="${map[status] ||'badge badge-soft text-xs'}">${status}</span>`;
}

function progressBar(pct) {
    const color = pct >= 95 ? '#ef4444' : pct >= 80 ? '#f59e0b' : '#22c55e';
    const width = Math.min(pct, 100);
    return `<div class="flex items-center gap-sm">
        <div class="flex-1 h-1.5 bg-surface-container rounded-full overflow-hidden">
            <div style="width:${width}%;background:${color}" class="h-full rounded-full"></div>
        </div>
        <span class="text-xs text-on-surface-variant w-10 text-right">${pct}%</span>
    </div>`;
}

let realisasiTable = null;
let activeStatusFilter = null;

function filterByStatus(status) {
    if (!realisasiTable) return;
    if (activeStatusFilter === status) {
        resetStatusFilter();
    } else {
        realisasiTable.setFilter('status', '=', status);
        activeStatusFilter = status;
        document.getElementById('filter-reset').classList.remove('hidden');
        document.getElementById('filter-hint').classList.add('hidden');
    }
}

function resetStatusFilter() {
    if (!realisasiTable) return;
    realisasiTable.clearFilter();
    activeStatusFilter = null;
    document.getElementById('filter-reset').classList.add('hidden');
    document.getElementById('filter-hint').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function () {
    if (!realisasiData.length) return;

    realisasiTable = new Tabulator('#realisasi-table', {
        data: realisasiData,
        layout: 'fitColumns',
        pagination: 'local',
        paginationSize: 30,
        groupBy: 'division',
        placeholder: 'Tidak ada data.',
        initialSort: [{ column: 'division', dir: 'asc' }],
        columns: [
            { title: 'Divisi',    field: 'division',     width: 160, headerFilter: 'input' },
            { title: 'Akun',      field: 'account_name', minWidth: 180, headerFilter: 'input' },
            {
                title: 'Anggaran (Total)',
                field: 'budget_total',
                width: 160,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Anggaran Q1', field: 'budget_q1', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Realisasi Q1', field: 'real_q1', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Status Q1', field: 'status_q1', width: 100, hozAlign: 'center',
                formatter: cell => statusBadge(cell.getValue()),
            },
            {
                title: 'Anggaran Q2', field: 'budget_q2', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Realisasi Q2', field: 'real_q2', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Status Q2', field: 'status_q2', width: 100, hozAlign: 'center',
                formatter: cell => statusBadge(cell.getValue()),
            },
            {
                title: 'Anggaran Q3', field: 'budget_q3', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Realisasi Q3', field: 'real_q3', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Status Q3', field: 'status_q3', width: 100, hozAlign: 'center',
                formatter: cell => statusBadge(cell.getValue()),
            },
            {
                title: 'Anggaran Q4', field: 'budget_q4', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Realisasi Q4', field: 'real_q4', width: 120,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Status Q4', field: 'status_q4', width: 100, hozAlign: 'center',
                formatter: cell => statusBadge(cell.getValue()),
            },
            {
                title: 'Realisasi Total',
                field: 'real_total',
                width: 150,
                hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => `<strong>${fmt(cell.getValue())}</strong>`,
            },
            {
                title: 'Progress',
                field: 'pct',
                width: 160,
                formatter: cell => progressBar(cell.getValue()),
            },
            {
                title: 'Status',
                field: 'status',
                width: 100,
                hozAlign: 'center',
                formatter: cell => statusBadge(cell.getValue()),
                headerFilter: 'list',
                headerFilterParams: { values: { '': 'Semua', 'Aman': 'Aman', 'Waspada': 'Waspada', 'Kritis': 'Kritis' } },
            },
        ],
    });
});
</script>
</x-app-layout>
