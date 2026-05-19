<x-app-layout>
    <x-slot name="title">Students</x-slot>

    <div class="p-lg space-y-lg" x-data="studentsPage()">

        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg">
            <div class="space-y-xs">
                <h1 class="text-headline-lg font-bold text-on-surface">Student Directory</h1>
                <p class="text-body-md text-on-surface-variant">Manage and monitor student progress across all academic programs.</p>
            </div>
            <div class="flex items-center gap-sm flex-wrap">
                <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-xs flex gap-xs">
                    <template x-for="tab in tabs" :key="tab.value">
                        <button
                            @click="setTab(tab.value)"
                            :class="activeTab === tab.value
                                ? 'bg-primary-container text-on-primary'
                                : 'text-on-surface-variant hover:bg-surface-container-low'"
                            class="px-md py-xs rounded-md text-body-md font-semibold transition-all"
                            x-text="tab.label">
                        </button>
                    </template>
                </div>
                <a href="{{ route('admin.enrollments.create') }}"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Tambah Student
                </a>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Students</span>
                    <span class="material-symbols-outlined text-secondary">group</span>
                </div>
                <div class="text-headline-lg font-bold text-on-surface" x-text="loading ? '...' : summary.total"></div>
            </div>
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Active Enrollment</span>
                    <span class="material-symbols-outlined text-secondary">check_circle</span>
                </div>
                <div class="text-headline-lg font-bold text-on-surface" x-text="loading ? '...' : summary.active"></div>
            </div>
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Tagihan Jatuh Tempo</span>
                    <span class="material-symbols-outlined text-error">event_busy</span>
                </div>
                <div class="text-headline-lg font-bold text-error" x-text="loading ? '...' : summary.overdue"></div>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between bg-surface-container-low">
                <h2 class="text-headline-md font-semibold text-on-surface">Master Directory</h2>
            </div>
            <div x-show="loading" class="flex items-center justify-center py-xl gap-sm text-on-surface-variant">
                <span class="loading loading-spinner loading-sm"></span>
                <span class="text-body-sm">Memuat data...</span>
            </div>
            <div x-show="!loading" id="students-table"></div>
        </div>

    {{-- Modal Edit Student --}}
    <dialog id="modal-edit-student" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Edit Student</h3>
            <form id="form-edit-student" method="POST" action="" class="space-y-md">
                @csrf
                @method('PUT')
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Lengkap</label>
                    <input type="text" name="name" id="edit-name" class="input w-full" placeholder="John Doe" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" id="edit-email" class="input w-full" placeholder="john@example.com" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Notes <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                    <textarea name="notes" id="edit-notes" rows="2" class="textarea w-full" placeholder="Catatan tambahan..."></textarea>
                </div>
                <div class="modal-action mt-lg">
                    <button type="button" onclick="document.getElementById('modal-edit-student').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">Simpan</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal Reset Password --}}
    <dialog id="modal-reset-password" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Reset Password</h3>
            <p class="text-body-md text-on-surface-variant mb-md">
                Reset password untuk <strong id="reset-student-name"></strong>
            </p>
            <form id="form-reset-password" method="POST" action="" class="space-y-md">
                @csrf
                @method('PUT')
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Password Baru</label>
                    <input type="password" name="password" class="input w-full" placeholder="Min. 8 karakter" required />
                </div>
                <div class="modal-action mt-lg">
                    <button type="button" onclick="document.getElementById('modal-reset-password').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">Reset Password</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
    function studentsPage() {
        return {
            activeTab: 'all',
            loading: true,
            summary: { total: 0, active: 0, overdue: 0 },
            table: null,

            tabs: [
                { value: 'all',      label: 'All Students' },
                { value: 'active',   label: 'Active Only' },
                { value: 'inactive', label: 'Inactive' },
            ],

            setTab(type) {
                this.activeTab = type;
                this.fetchData();
            },

            async fetchData() {
                this.loading = true;
                const params = new URLSearchParams({ tab: this.activeTab });
                const res  = await fetch(`{{ route('admin.students.data') }}?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                this.summary = json.summary;
                this.buildTable(json.rows);
                this.loading = false;
            },

            buildTable(data) {
                if (this.table) { this.table.destroy(); this.table = null; }

                this.table = new Tabulator('#students-table', {
                    data,
                    layout: 'fitColumns',
                    pagination: true,
                    paginationSize: 20,
                    paginationSizeSelector: [10, 20, 50, 100],
                    columns: [
                        {
                            title: 'Student', field: 'name', minWidth: 200,
                            formatter(cell) {
                                const d = cell.getData();
                                return `<div class="flex items-center gap-md">
                                    <div class="w-10 h-10 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-secondary text-xs shrink-0">
                                        ${d.initials}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-body-md font-semibold text-on-surface">${d.name}</span>
                                        <span class="text-label-lg text-on-surface-variant">${d.email}</span>
                                    </div>
                                </div>`;
                            },
                        },
                        {
                            title: 'Program', field: 'program', width: 160,
                            formatter(cell) {
                                return cell.getValue()
                                    ? `<span class="text-body-md text-on-surface">${cell.getValue()}</span>`
                                    : `<span class="text-on-surface-variant">—</span>`;
                            },
                        },
                        {
                            title: 'Tutor', field: 'tutors', minWidth: 140,
                            formatter(cell) {
                                const tutors = cell.getValue();
                                if (!tutors?.length) return `<span class="text-body-md text-on-surface-variant italic">Belum ada</span>`;
                                return tutors.map(name => `
                                    <div class="flex items-center gap-xs">
                                        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">account_circle</span>
                                        <span class="text-body-md text-on-surface">${name}</span>
                                    </div>`).join('');
                            },
                        },
                        {
                            title: 'Progress', field: 'percent', width: 160, sorter: 'number',
                            formatter(cell) {
                                const d = cell.getData();
                                if (d.total_meet > 0) {
                                    return `<div class="space-y-xs">
                                        <span class="text-body-md font-semibold text-secondary">Pertemuan ke-${d.done}</span>
                                        <div class="w-32 h-1.5 bg-surface-container-highest rounded-full overflow-hidden">
                                            <div class="bg-secondary h-full rounded-full" style="width:${d.percent}%"></div>
                                        </div>
                                    </div>`;
                                } else if (d.enroll_status !== 'none') {
                                    return `<span class="text-body-md text-on-surface-variant">${d.remaining} sesi tersisa</span>`;
                                }
                                return `<span class="text-on-surface-variant">—</span>`;
                            },
                        },
                        {
                            title: 'Status', field: 'enroll_status', width: 130,
                            formatter(cell) {
                                const s = cell.getValue();
                                if (s === 'active') return `<span class="inline-flex items-center gap-xs px-md py-xs bg-success/10 text-success rounded-full text-[12px] font-bold"><span class="w-2 h-2 rounded-full bg-success"></span>AKTIF</span>`;
                                if (s === 'none')   return `<span class="text-body-md text-on-surface-variant italic">Belum enroll</span>`;
                                return `<span class="inline-flex items-center gap-xs px-md py-xs bg-error-container text-on-error-container rounded-full text-[12px] font-bold"><span class="w-2 h-2 rounded-full bg-error"></span>${s.toUpperCase()}</span>`;
                            },
                        },
                        {
                            title: 'Pembayaran', field: 'payment_status', width: 140,
                            formatter(cell) {
                                const s = cell.getValue();
                                if (!s) return `<span class="text-on-surface-variant">—</span>`;
                                if (s === 'overdue') return `<span class="inline-flex items-center gap-xs px-md py-xs bg-error-container text-on-error-container rounded-full text-[12px] font-bold"><span class="material-symbols-outlined text-[14px]">credit_card_off</span>OVERDUE</span>`;
                                if (s === 'cicilan') return `<span class="inline-flex items-center gap-xs px-md py-xs bg-tertiary-fixed text-on-tertiary-fixed-variant rounded-full text-[12px] font-bold"><span class="material-symbols-outlined text-[14px]">payments</span>CICILAN</span>`;
                                return `<span class="inline-flex items-center gap-xs px-md py-xs bg-success/10 text-success rounded-full text-[12px] font-bold"><span class="material-symbols-outlined text-[14px]">check_circle</span>LUNAS</span>`;
                            },
                        },
                        {
                            title: '', field: 'id', width: 110, headerSort: false,
                            formatter(cell) {
                                const d = cell.getData();
                                const name  = d.name.replace(/'/g, "\\'");
                                const email = d.email.replace(/'/g, "\\'");
                                const notes = d.notes.replace(/'/g, "\\'");
                                return `<div class="flex items-center justify-end gap-xs">
                                    <button onclick="openEditModal(${d.id},'${name}','${email}','${notes}')"
                                        class="btn btn-xs btn-ghost" title="Edit">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                    </button>
                                    <button onclick="openResetModal(${d.id},'${name}')"
                                        class="btn btn-xs btn-ghost text-on-surface-variant" title="Reset Password">
                                        <span class="material-symbols-outlined text-[15px]">lock_reset</span>
                                    </button>
                                    <button onclick="confirmDelete(${d.id},'${name}')"
                                        class="btn btn-xs btn-ghost text-error" title="Hapus">
                                        <span class="material-symbols-outlined text-[15px]">delete</span>
                                    </button>
                                </div>`;
                            },
                        },
                    ],
                });
            },

            init() {
                this.fetchData();
            },
        };
    }

    function openEditModal(id, name, email, notes) {
        document.getElementById('edit-name').value  = name;
        document.getElementById('edit-email').value = email;
        document.getElementById('edit-notes').value = notes;
        document.getElementById('form-edit-student').action = `/admin/students/${id}`;
        document.getElementById('modal-edit-student').showModal();
    }

    function openResetModal(id, name) {
        document.getElementById('reset-student-name').textContent = name;
        document.getElementById('form-reset-password').action = `/admin/students/${id}`;
        document.getElementById('modal-reset-password').showModal();
    }

    function confirmDelete(id, name) {
        if (confirm(`Hapus student ${name}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/admin/students/${id}`;
            form.innerHTML = `@csrf @method('DELETE')`;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>

</x-app-layout>
