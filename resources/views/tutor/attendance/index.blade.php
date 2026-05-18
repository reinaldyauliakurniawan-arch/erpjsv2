<x-app-layout>
<x-slot name="title">Absensi</x-slot>

<div class="p-lg space-y-md" x-data="attendancePage()" x-init="init()">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Riwayat Absensi</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Semua sesi yang sudah diinput</p>
        </div>
        <a href="{{ route('tutor.attendance.create') }}"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
            <span class="material-symbols-outlined text-base">add</span>
            Input Absensi
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="alert alert-success alert-soft">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Summary Cards (reactive) --}}
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Belum Dibayar</p>
            <p class="text-xl font-bold text-error mt-xs">
                Rp <span x-text="formatRp(summary.unpaid)">{{ number_format($unpaidTotal,0,',','.') }}</span>
            </p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Dibayar Bulan Ini</p>
            <p class="text-xl font-bold text-success mt-xs">
                Rp <span x-text="formatRp(summary.paidMonth)">{{ number_format($paidThisMonth,0,',','.') }}</span>
            </p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Rate Pending</p>
            <p class="text-xl font-bold mt-xs" :class="summary.pendingRate > 0 ? 'text-warning' : 'text-on-surface'">
                <span x-text="summary.pendingRate">{{ $pendingRateCount }}</span> sesi
            </p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-md flex flex-wrap gap-sm items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="text-xs text-on-surface-variant mb-xs block">Cari Program / Sesi</label>
            <input type="text" x-model="filters.search" @input.debounce.400ms="fetchData()"
                placeholder="Ketik untuk mencari..."
                class="input input-sm w-full">
        </div>
        <div>
            <label class="text-xs text-on-surface-variant mb-xs block">Dari</label>
            <input type="date" x-model="filters.date_from" @change="fetchData()"
                class="input input-sm">
        </div>
        <div>
            <label class="text-xs text-on-surface-variant mb-xs block">Sampai</label>
            <input type="date" x-model="filters.date_to" @change="fetchData()"
                class="input input-sm">
        </div>
        <button @click="clearFilters()" class="btn btn-ghost btn-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-base">filter_alt_off</span>
            Reset
        </button>
    </div>

    {{-- Tabulator --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <div id="attendance-table"></div>
    </div>

</div>

{{-- Confirm Delete Form (dipanggil dari Tabulator formatter) --}}
<form id="delete-form" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<script>
function attendancePage() {
    return {
        table: null,
        filters: { search: '', date_from: '', date_to: '' },
        summary: {
            unpaid:      {{ $unpaidTotal }},
            paidMonth:   {{ $paidThisMonth }},
            pendingRate: {{ $pendingRateCount }},
        },

        init() {
            this.$nextTick(() => this.buildTable());
        },

        buildTable() {
            this.table = new Tabulator('#attendance-table', {
                layout:           'fitColumns',
                responsiveLayout: 'collapse',
                pagination:       true,
                paginationSize:   20,
                placeholder:      'Belum ada data absensi.',
                data:             [],
                columns: [
                    {
                        title: 'Tanggal',
                        field: 'date',
                        sorter: 'date',
                        sorterParams: { format: 'YYYY-MM-DD' },
                        formatter: (cell) => cell.getRow().getData().date_fmt,
                        width: 130,
                    },
                    { title: 'Program',   field: 'program',    sorter: 'string' },
                    { title: 'Sesi',      field: 'time_block', sorter: 'string', width: 140 },
                    { title: 'Hadir',     field: 'hadir',      sorter: 'string', width: 80, hozAlign: 'center' },
                    {
                        title: 'Status',
                        field: 'pending_rate',
                        width: 120,
                        hozAlign: 'center',
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            if (d.pending_rate)
                                return '<span class="badge badge-soft badge-warning text-xs">Rate Pending</span>';
                            if (d.paid_at)
                                return '<span class="badge badge-soft badge-success text-xs">Dibayar</span>';
                            return '<span class="badge badge-soft badge-error text-xs">Belum Bayar</span>';
                        },
                    },
                    {
                        title: 'Aksi',
                        field: 'id',
                        width: 80,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: (cell) => {
                            const id = cell.getValue();
                            return `<button onclick="confirmDelete(${id})"
                                class="btn btn-ghost btn-xs text-error"
                                title="Reverse absensi">
                                <span class="material-symbols-outlined text-base">undo</span>
                            </button>`;
                        },
                    },
                ],
            });
            this.fetchData();
        },

        fetchData() {
            const params = new URLSearchParams();
            if (this.filters.search)    params.set('search',    this.filters.search);
            if (this.filters.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters.date_to)   params.set('date_to',   this.filters.date_to);

            fetch(`{{ route('tutor.attendance.data') }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.table.setData(data);
                this.updateSummary(data);
            });
        },

        updateSummary(data) {
            const now     = new Date();
            const month   = now.getMonth();
            const year    = now.getFullYear();
            let unpaid    = 0, paidMonth = 0, pendingRate = 0;

            data.forEach(row => {
                if (row.pending_rate) {
                    pendingRate++;
                } else if (!row.paid_at) {
                    unpaid += Number(row.payable);
                } else {
                    const d = new Date(row.paid_at);
                    if (d.getMonth() === month && d.getFullYear() === year) {
                        paidMonth += Number(row.payable);
                    }
                }
            });

            this.summary = { unpaid, paidMonth, pendingRate };
        },

        clearFilters() {
            this.filters = { search: '', date_from: '', date_to: '' };
            this.fetchData();
        },

        formatRp(val) {
            return Number(val).toLocaleString('id-ID');
        },
    };
}

function confirmDelete(id) {
    if (!confirm('Reverse absensi ini?')) return;
    const form   = document.getElementById('delete-form');
    form.action  = `/tutor/attendance/${id}`;
    form.submit();
}
</script>
</x-app-layout>



