<x-app-layout>
<x-slot name="title">Deferred Revenue</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Deferred Revenue</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan yang belum diakui dari enrollment aktif</p>
        </div>
        <button onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">
            <span class="material-symbols-outlined text-base">download</span>
            Export CSV
        </button>
    </div>

    {{-- Filter --}}
    <div class="app-card">
        <form method="GET" action="{{ route('finance.reports.deferred-revenue') }}" class="flex items-end gap-md flex-wrap">
            <div class="fieldset">
                <label class="fieldset-legend">Dari</label>
                <input type="date" name="from" class="input input-sm" value="{{ $filterFrom ?? '' }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-legend">Sampai</label>
                <input type="date" name="to" class="input input-sm" value="{{ $filterTo ?? '' }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-legend">Program</label>
                <select name="program" class="select select-sm">
                    <option value="">Semua Program</option>
                    @foreach($programs as $p)
                        <option value="{{ $p->id }}" {{ $filterProgram == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
            <a href="{{ route('finance.reports.deferred-revenue') }}" class="btn btn-sm btn-ghost border border-surface-border">Reset</a>
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Deferred Revenue</p>
            <p class="text-2xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalDeferred, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Jumlah Enrollment</p>
            <p class="text-2xl font-bold text-on-surface mt-xs">{{ $enrollments->count() }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Sudah Diakui</p>
            <p class="text-2xl font-bold text-success mt-xs">Rp {{ number_format($enrollments->sum('recognized_amount'), 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="app-card">
        <div id="dr-table"></div>
    </div>

</div>

<script>
const drData = @json($enrollments->values());

function fmt(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));
}

document.addEventListener('DOMContentLoaded', function() {
    new Tabulator("#dr-table", {
        data: drData,
        layout: "fitColumns",
        pagination: "local",
        paginationSize: 25,
        columns: [
            { title: "Siswa", field: "student_name", minWidth: 150, headerFilter: "input" },
            { title: "Program", field: "program_name", minWidth: 150, headerFilter: "input" },
            { title: "Bulan", field: "enrolled_month", width: 100 },
            { title: "Total Sesi", field: "total_meetings", width: 90, hozAlign: "right" },
            { title: "Terpakai", field: "meetings_used", width: 90, hozAlign: "right" },
            { title: "Sisa", field: "remaining", width: 80, hozAlign: "right" },
            { title: "Harga Dibayar", field: "paid_amount", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
            { title: "Sudah Diakui", field: "recognized_amount", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
            { title: "Sisa Deferred", field: "deferred_amount", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
        ],
    });
});

function exportCSV() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = `{{ route("finance.exports.deferred-revenue") }}?${params.toString()}`;
}
</script>
</x-app-layout>
