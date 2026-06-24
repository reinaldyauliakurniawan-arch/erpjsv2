<x-app-layout>
    <x-slot name="title">Absensi</x-slot>

    <div class="p-lg space-y-lg" x-data="attendancePage()">

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if($errors->has('error'))
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <span>{{ $errors->first('error') }}</span>
            </div>
        @endif

        {{-- Header + Tab Toggle --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg">
            <div class="space-y-xs">
                <h3 class="text-headline-lg font-semibold text-on-surface">Absensi</h3>
                <p class="text-body-md text-on-surface-variant">Monitoring kehadiran berdasarkan kategori kelas.</p>
            </div>
            <div class="flex items-center bg-surface-container-low p-1 rounded-lg border border-surface-border">
                <template x-for="tab in tabs" :key="tab.value">
                    <button
                        @click="setTab(tab.value)"
                        :class="activeTab === tab.value ?'bg-secondary text-on-secondary shadow-sm'
                            : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-lg py-2 rounded-lg text-sm font-bold transition-all"
                        x-text="tab.label">
                    </button>
                </template>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-md">
            <div class="app-card flex flex-col gap-md">
                <div class="app-icon-badge">
                    <span class="material-symbols-outlined text-secondary">assignment_turned_in</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Sesi</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs" x-text="loading ? '...' : summary.total_sessions"></p>
                </div>
            </div>
            <div class="app-card flex flex-col gap-md">
                <div class="app-icon-badge">
                    <span class="material-symbols-outlined text-secondary">today</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Aktif Hari Ini</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs" x-text="loading ? '...' : summary.active_today"></p>
                </div>
            </div>
            <div class="app-card flex flex-col gap-md">
                <div class="app-icon-badge">
                    <span class="material-symbols-outlined text-secondary">group</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Kehadiran Rata-rata</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs" x-text="loading ? '...' : summary.avg_attendance + '%'"></p>
                </div>
            </div>
            <div class="app-card flex flex-col gap-md">
                <div class="app-icon-badge">
                    <span class="material-symbols-outlined text-secondary">calendar_month</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Periode</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs" x-text="loading ? '...' : periodLabel"></p>
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="app-card p-md flex flex-wrap gap-sm items-end">

            {{-- Periode preset --}}
            <div class="flex flex-col gap-xs">
                <label class="text-label-caps text-on-surface-variant text-[11px] tracking-widest uppercase">Periode</label>
                <select x-model="period" @change="onPeriodChange()" class="select select-sm select-bordered bg-surface text-on-surface">
                    <option value="all">Semua</option>
                    <option value="this_month">Bulan Ini</option>
                    <option value="last_month">Bulan Lalu</option>
                    <option value="3_months">3 Bulan Terakhir</option>
                    <option value="this_year">Tahun Ini</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>

            {{-- Custom date range — muncul hanya kalau pilih Custom --}}
            <template x-if="period === 'custom'">
                <div class="flex gap-sm items-end">
                    <div class="flex flex-col gap-xs">
                        <label class="text-label-caps text-on-surface-variant text-[11px] tracking-widest uppercase">Dari</label>
                        <input type="date" x-model="dateFrom" class="input input-sm bg-surface text-on-surface" />
                    </div>
                    <div class="flex flex-col gap-xs">
                        <label class="text-label-caps text-on-surface-variant text-[11px] tracking-widest uppercase">Sampai</label>
                        <input type="date" x-model="dateTo" class="input input-sm bg-surface text-on-surface" />
                    </div>
                </div>
            </template>

            {{-- Tutor --}}
            <div class="flex flex-col gap-xs flex-1 min-w-[160px]">
                <label class="text-label-caps text-on-surface-variant text-[11px] tracking-widest uppercase">Tutor</label>
                <input type="text" x-model="tutor" placeholder="Nama tutor..." class="input input-sm bg-surface text-on-surface" />
            </div>

            {{-- Status --}}
            <div class="flex flex-col gap-xs flex-1 min-w-[140px]">
                <label class="text-label-caps text-on-surface-variant text-[11px] tracking-widest uppercase">Status</label>
                <select x-model="status" class="select select-sm select-bordered bg-surface text-on-surface">
                    <option value="">Semua</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="finished">Finished</option>
                    <option value="skipped">Skipped</option>
                    <option value="postponed">Postponed</option>
                </select>
            </div>

            <button @click="fetchData()" class="btn btn-sm bg-secondary text-on-secondary border-none hover:opacity-90" :disabled="loading">
                <span class="material-symbols-outlined text-[18px]">search</span>
                Cari
            </button>
            <button @click="resetFilters()" class="btn btn-sm btn-ghost text-on-surface-variant">
                <span class="material-symbols-outlined text-[18px]">filter_alt_off</span>
                Reset
            </button>
        </div>

        {{-- Table --}}
        <div class="app-card app-card--flush">
            <div class="app-card__header">
                <h4 class="text-title-sm font-semibold text-primary" x-text="tableTitle"></h4>
                <template x-if="summary">
                    <span x-show="summary.duplicate_count > 0" class="badge badge-soft badge-error gap-xs">
                        <span class="material-symbols-outlined text-[14px]">warning</span>
                        <span x-text="summary.duplicate_count"></span> duplikat
                    </span>
                </template>
            </div>
            <div x-show="loading" class="flex items-center justify-center py-xl gap-sm text-on-surface-variant">
                <span class="loading loading-spinner loading-sm"></span>
                <span class="text-body-sm">Memuat data...</span>
            </div>
            <div x-show="!loading" id="attendance-table"></div>
        </div>

    </div>

    {{-- Modal Edit --}}
    <dialog id="modal-edit" class="modal">
        <div class="modal-box bg-surface-container-lowest border border-surface-border">
            <h3 class="text-headline-md font-semibold text-on-surface mb-xs">Edit Status Attendance</h3>
            <p class="text-body-sm text-on-surface-variant mb-lg" id="edit-subtitle">—</p>
            <form id="form-edit" method="POST">
                @csrf
                @method('PATCH')
                <div class="flex flex-col gap-sm mb-lg">
                    <label class="text-label-caps text-on-surface-variant text-[11px] tracking-widest uppercase">Status</label>
                    <select name="status" id="edit-status" class="select select-bordered bg-surface text-on-surface w-full">
                       <option value="scheduled">Scheduled</option>
                       <option value="ongoing">Ongoing</option>
                       <option value="finished">Finished</option>
                       <option value="skipped">Skipped</option>
                       <option value="postponed">Postponed</option>
                    </select>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-edit').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-secondary text-on-secondary border-none hover:opacity-90">Simpan</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal Delete --}}
    <dialog id="modal-delete" class="modal">
        <div class="modal-box bg-surface-container-lowest border border-surface-border">
            <h3 class="text-headline-md font-semibold text-on-surface mb-xs">Hapus Attendance</h3>
            <p class="text-body-sm text-on-surface-variant mb-md" id="delete-subtitle">—</p>
            <p class="text-body-sm text-error">Jurnal terkait akan di-reverse. Tindakan ini tidak bisa dibatalkan.</p>
            <form id="form-delete" method="POST" class="mt-lg">
                @csrf
                @method('DELETE')
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-delete').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn btn-error">Hapus</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
    function attendancePage() {
        return {
            // State
            activeTab: 'all',
            period:    'this_month',
            dateFrom:  '',
            dateTo:    '',
            tutor:     '',
            status:    '',
            loading:   true,
            summary:   { total_sessions: 0, active_today: 0, avg_attendance: 0, duplicate_count: 0 },
            table:     null,

            tabs: [
                { value: 'all',          label: 'Semua' },
                { value: 'group',        label: 'Group Class' },
                { value: 'semi-private', label: 'Semi Private' },
                { value: 'private',      label: 'Private' },
            ],

            // Computed-style getters
            get tableTitle() {
                const map = {
                    'all':          'Rekap Semua Sesi',
                    'group':        'Rekap Absensi — Group Class',
                    'semi-private': 'Rekap Absensi — Semi Private',
                    'private':      'Rekap Absensi — Private',
                };
                return map[this.activeTab] ?? 'Rekap Absensi';
            },

            get periodLabel() {
                const now   = new Date();
                const month = now.toLocaleString('id-ID', { month: 'long' });
                const year  = now.getFullYear();
                const map = {
                    'all':        'Semua Data',
                    'this_month': `${month} ${year}`,
                    'last_month': (() => {
                        const d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        return d.toLocaleString('id-ID', { month: 'long', year: 'numeric' });
                    })(),
                    '3_months':   '3 Bulan Terakhir',
                    'this_year':  `Tahun ${year}`,
                    'custom':     this.dateFrom && this.dateTo
                        ? `${this.dateFrom} – ${this.dateTo}`
                        : 'Custom',
                };
                return map[this.period] ?? '—';
            },

            // Period preset → dateFrom & dateTo
            resolveDates() {
                const now   = new Date();
                const pad   = n => String(n).padStart(2, '0');
                const fmt   = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

                const firstOfMonth = (y, m) => new Date(y, m, 1);
                const lastOfMonth  = (y, m) => new Date(y, m + 1, 0);

                const y = now.getFullYear(), m = now.getMonth();

                const map = {
                    'all':        { from: '', to: '' },
                    'this_month': { from: fmt(firstOfMonth(y, m)),     to: fmt(lastOfMonth(y, m)) },
                    'last_month': { from: fmt(firstOfMonth(y, m - 1)), to: fmt(lastOfMonth(y, m - 1)) },
                    '3_months':   { from: fmt(new Date(y, m - 2, 1)),  to: fmt(now) },
                    'this_year':  { from: `${y}-01-01`,                to: `${y}-12-31` },
                    'custom':     { from: this.dateFrom,               to: this.dateTo },
                };
                return map[this.period] ?? { from: '', to: '' };
            },

            onPeriodChange() {
                if (this.period !== 'custom') this.fetchData();
            },

            // Fetch
            async fetchData() {
                if (this.period === 'custom' && (!this.dateFrom || !this.dateTo)) {
                    alert('Silakan isi tanggal mulai dan tanggal akhir terlebih dahulu.');
                    return;
                }
                this.loading = true;
                try {
                    const { from, to } = this.resolveDates();
                    const params = new URLSearchParams();
                    if (from)           params.set('date_from',    from);
                    if (to)             params.set('date_to',      to);
                    if (this.tutor)     params.set('tutor',        this.tutor);
                    if (this.status)    params.set('status',       this.status);
                    if (this.activeTab !== 'all') params.set('program_type', this.activeTab);

                    const res  = await fetch(`{{ route('admin.attendance.data') }}?${params}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const json = await res.json();
                    this.summary = json.summary ?? { total_sessions: 0, active_today: 0, avg_attendance: 0, duplicate_count: 0 };
                    this.buildTable(json.rows ?? []);
                } catch (e) {
                    console.error('Fetch error:', e);
                    this.summary = { total_sessions: 0, active_today: 0, avg_attendance: 0, duplicate_count: 0 };
                } finally {
                    this.loading = false;
                }
            },

            // Tabulator
            buildTable(data) {
                if (this.table) { this.table.destroy(); this.table = null; }

                this.table = new Tabulator('#attendance-table', {
                    data,
                    layout: 'fitColumns',
                    pagination: true,
                    paginationSize: 20,
                    paginationSizeSelector: [10, 20, 50, 100],
                    rowFormatter(row) {
                        if (row.getData().is_duplicate) {
                            row.getElement().style.backgroundColor = 'oklch(var(--er)/0.05)';
                        }
                    },
                    columns: [
                        {
                            title: 'Tanggal', field: 'date', width: 160, sorter: 'date',
                            formatter(cell) {
                                const d = cell.getData();
                                const warn = d.is_duplicate
                                    ? `<span class="material-symbols-outlined text-error text-[16px] align-middle ml-1" title="Duplikat">warning</span>`
                                    : '';
                                return `<div>
                                    <p class="text-body-sm font-semibold text-on-surface">${d.date_label}${warn}</p>
                                    <p class="text-[12px] text-on-surface-variant">${d.day_label}</p>
                                </div>`;
                            },
                        },
                        {
                            title: 'Time Block', field: 'time_block', width: 120,
                            formatter(cell) {
                                return `<span class="px-sm py-xs bg-surface-container border border-surface-border rounded text-[12px] font-medium text-on-surface">${cell.getValue() ?? '—'}</span>`;
                            },
                        },
                        {
                            title: 'Kelas', field: 'class_name',
                            formatter(cell) {
                                return `<div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded-lg bg-primary/5 border border-primary/10 flex items-center justify-center text-primary shrink-0">
                                        <span class="material-symbols-outlined text-[16px]">groups</span>
                                    </div>
                                    <p class="text-body-sm font-semibold text-on-surface">${cell.getValue() ?? '—'}</p>
                                </div>`;
                            },
                        },
                        {
                            title: 'Program', field: 'program_name',
                            formatter(cell) {
                                const d = cell.getData();
                                const type = d.program_type ? `<p class="text-[11px] text-on-surface-variant">${d.program_type}</p>` : '';
                                return `<div><p class="text-body-sm text-on-surface">${cell.getValue() ?? '—'}</p>${type}</div>`;
                            },
                        },
                        {
                            title: 'Tutor', field: 'tutors',
                            formatter(cell) {
                                const tutors = cell.getValue();
                                if (!tutors?.length) return `<span class="text-on-surface-variant">—</span>`;
                                return tutors.map(name => `
                                    <div class="flex items-center gap-xs">
                                        <div class="w-5 h-5 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center text-[10px] font-bold shrink-0">
                                            ${name.charAt(0).toUpperCase()}
                                        </div>
                                        <span class="text-body-sm text-on-surface">${name}</span>
                                    </div>`).join('');
                            },
                        },
                        {
                            title: 'Replacement', field: 'replacements', headerSort: false,
                            formatter(cell) {
                                const replacements = cell.getValue();
                                if (!replacements?.length) return `<span class="text-on-surface-variant text-[12px]">—</span>`;
                                return replacements.map(r => `
                                    <div class="text-[12px] leading-tight">
                                        <span class="font-semibold text-warning">${r.replaced_by}</span>
                                        <span class="text-on-surface-variant"> replace </span>
                                        <span class="font-semibold text-on-surface">${r.replaced_tutor ?? '?'}</span>
                                    </div>`).join('');
                            },
                        },
                        {
                            title: 'Kehadiran', field: 'pct', width: 160, sorter: 'number',
                            formatter(cell) {
                                const d = cell.getData();
                                if (d.total === 0 || d.pct === null) return `<span class="text-on-surface-variant text-[12px]">—</span>`;
                                const pct   = d.pct;
                                const color = pct >= 80 ? 'bg-secondary' : pct >= 50 ? 'bg-warning' : 'bg-error';
                                return `<div class="flex flex-col gap-xs">
                                    <div class="flex justify-between items-center text-[11px] font-bold text-on-surface-variant">
                                        <span>${d.present} / ${d.total} Hadir</span>
                                        <span>${pct}%</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-surface-border rounded-full overflow-hidden">
                                        <div class="h-full rounded-full ${color}" style="width:${pct}%"></div>
                                    </div>
                                </div>`;
                            },
                        },
                        {
                            title: 'Status', field: 'status', width: 130,
                            formatter(cell) {
                                const s = cell.getValue();
                                if (s === 'finished')  return `<span class="badge badge-soft badge-success">Finished</span>`;
                                if (s === 'ongoing')   return `<span class="badge badge-soft badge-info">Ongoing</span>`;
                                if (s === 'scheduled') return `<span class="badge badge-soft badge-warning">Scheduled</span>`;
                                if (s === 'skipped')   return `<span class="badge badge-soft badge-error">Skipped</span>`;
                                if (s === 'postponed') return `<span class="badge badge-soft badge-neutral">Postponed</span>`;
                                return `<span class="badge badge-soft badge-ghost">${s ?? '—'}</span>`;
                            },
                        },
                        {
                            title: '', field: 'id', width: 100, headerSort: false,
                            formatter(cell) {
                                const d = cell.getData();
                                const cn = d.class_name.replace(/'/g, "\\'");
                                return `<div class="flex items-center justify-end gap-xs">
                                    <button type="button" onclick="openEditModal(${d.id},'${d.status}','${cn}','${d.date_label}')"
                                        class="btn btn-ghost btn-sm text-on-surface-variant hover:text-secondary" title="Edit">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <button type="button" onclick="openDeleteModal(${d.id},'${cn}','${d.date_label}')"
                                        class="btn btn-ghost btn-sm text-error" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>`;
                            },
                        },
                    ],
                });
            },

            setTab(type) {
                this.activeTab = type;
                this.fetchData();
            },

            resetFilters() {
                this.period   = 'this_month';
                this.dateFrom = '';
                this.dateTo   = '';
                this.tutor    = '';
                this.status   = '';
                this.fetchData();
            },

            init() {
                this.fetchData();
            },
        };
    }

    // Modal helpers — di luar Alpine karena dipanggil dari Tabulator formatter
    function openEditModal(id, currentStatus, className, date) {
        document.getElementById('edit-subtitle').textContent = `${className} — ${date}`;
        document.getElementById('edit-status').value = currentStatus;
        document.getElementById('form-edit').action = `/admin/attendance/${id}`;
        document.getElementById('modal-edit').showModal();
    }

    function openDeleteModal(id, className, date) {
        document.getElementById('delete-subtitle').textContent = `${className} — ${date}`;
        document.getElementById('form-delete').action = `/admin/attendance/${id}`;
        document.getElementById('modal-delete').showModal();
    }
    </script>

</x-app-layout>
