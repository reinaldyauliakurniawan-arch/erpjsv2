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
                <a href="{{ route('admin.enrollments.create') }}"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Enroll Siswa Baru
                </a>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-md">
            <div class="app-card space-y-md">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Students</span>
                    <span class="material-symbols-outlined text-secondary">group</span>
                </div>
                <div class="text-headline-lg font-bold text-on-surface" x-text="loading ? '...' : summary.total"></div>
            </div>
            <div class="app-card space-y-md">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Active Enrollment</span>
                    <span class="material-symbols-outlined text-secondary">check_circle</span>
                </div>
                <div class="text-headline-lg font-bold text-on-surface" x-text="loading ? '...' : summary.active"></div>
            </div>
            <button @click="setFilter('inactive')"
                :class="filter ==='inactive' ? 'ring-2 ring-warning' : ''"
                class="app-card space-y-md text-left w-full hover:bg-surface-container-low transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Inactive Students</span>
                    <span class="material-symbols-outlined text-warning">person_off</span>
                </div>
                <div class="text-headline-lg font-bold text-warning" x-text="loading ? '...' : summary.inactive"></div>
            </button>
            <button @click="setFilter('overdue')"
                :class="filter ==='overdue' ? 'ring-2 ring-error' : ''"
                class="app-card space-y-md text-left w-full hover:bg-surface-container-low transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-label-lg text-on-surface-variant uppercase tracking-widest">Tagihan Jatuh Tempo</span>
                    <span class="material-symbols-outlined text-error">event_busy</span>
                </div>
                <div class="text-headline-lg font-bold text-error" x-text="loading ? '...' : summary.overdue"></div>
            </button>
        </div>

        {{-- Table --}}
        <div class="app-card app-card--flush">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between bg-surface-container-low">
                <h2 class="text-headline-md font-semibold text-on-surface">Master Directory</h2>
            </div>
            <div x-show="loading" class="flex items-center justify-center py-xl gap-sm text-on-surface-variant">
                <span class="loading loading-spinner loading-sm"></span>
                <span class="text-body-sm">Memuat data...</span>
            </div>
            <div x-show="!loading">
                <div x-show="filter !== 'all'" class="px-lg py-sm bg-surface-container-low border-b border-surface-border flex items-center gap-sm text-body-sm text-on-surface-variant">
                    <span>Filter aktif:</span>
                    <span class="font-semibold text-on-surface" x-text="filter === 'inactive' ? 'Inactive Students' : 'Tagihan Jatuh Tempo'"></span>
                    <button @click="setFilter('all')" class="ml-auto text-error hover:underline text-body-sm">Reset filter</button>
                </div>
                <div class="app-table-wrapper">
<table class="w-full text-left">
                    <thead class="bg-surface-container-low border-b border-surface-border">
                        <tr>
                            <th class="px-lg py-md text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Student</th>
                            <th class="px-lg py-md text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Jenjang</th>
                            <th class="px-lg py-md text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Enrollments</th>
                            <th class="px-lg py-md text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold w-28"></th>
                        </tr>
                    </thead>
                    <tbody x-show="rows.length === 0">
                        <tr><td colspan="4" class="px-lg py-xl text-center text-on-surface-variant italic">Tidak ada data.</td></tr>
                    </tbody>
                    <template x-for="s in rows" :key="s.id">
                        <tbody class="border-b border-surface-border">
                            <tr class="hover:bg-surface-container-low transition-all">
                                <td class="px-lg py-md align-top">
                                    <div class="flex items-center gap-md cursor-pointer" @click="s._open = !s._open">
                                        <div class="app-avatar app-avatar--sm" x-text="s.initials"></div>
                                        <div class="flex flex-col">
                                            <span class="text-body-md font-semibold text-on-surface" x-text="s.name"></span>
                                            <span class="text-label-lg text-on-surface-variant" x-text="s.email"></span>
                                        </div>
                                        <span class="material-symbols-outlined text-on-surface-variant text-[18px] ml-sm transition-transform" :class="s._open ?'rotate-180' : ''" x-text="'expand_more'"></span>
                                    </div>
                                </td>
                                <td class="px-lg py-md align-top">
                                    <span class="badge badge-soft text-xs" x-text="s.education_level || '—'"></span>
                                </td>
                                <td class="px-lg py-md align-top">
                                    <span class="text-body-sm text-on-surface-variant" x-text="s.enrollments.filter(e => e.status === 'active').length + ' aktif / ' + s.enrollments.length + ' total'"></span>
                                </td>
                                <td class="px-lg py-md align-top">
                                    <div class="flex items-center justify-end gap-xs">
                                        <button @click.stop="openEditModal(s.id, s.name, s.email, s.notes, s.education_level)" class="btn btn-xs btn-ghost" title="Edit">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                        </button>
                                        <button @click.stop="openResetModal(s.id, s.name)" class="btn btn-xs btn-ghost text-on-surface-variant" title="Reset Password">
                                            <span class="material-symbols-outlined text-[15px]">lock_reset</span>
                                        </button>
                                        <button @click.stop="confirmDelete(s.id, s.name)" class="btn btn-xs btn-ghost text-error" title="Hapus">
                                            <span class="material-symbols-outlined text-[15px]">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="s._open" class="bg-surface-container-low/50">
                                <td colspan="3" class="px-lg pb-md pt-0">
                                    <div class="rounded-lg border border-surface-border overflow-hidden">
                                        <table class="w-full text-left">
                                            <thead class="bg-surface-container border-b border-surface-border">
                                                <tr>
                                                    <th class="px-md py-sm text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Program</th>
                                                    <th class="px-md py-sm text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Status</th>
                                                    <th class="px-md py-sm text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Tutor</th>
                                                    <th class="px-md py-sm text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Progress</th>
                                                    <th class="px-md py-sm text-label-lg text-on-surface-variant uppercase tracking-widest font-semibold">Pembayaran</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-surface-border">
                                                <template x-for="e in s.enrollments" :key="e.enrollment_id">
                                                    <tr>
                                                        <td class="px-md py-sm text-body-sm font-medium text-on-surface" x-text="e.program"></td>
                                                        <td class="px-md py-sm" x-html="statusBadge(e.status)"></td>
                                                        <td class="px-md py-sm text-body-sm text-on-surface-variant" x-text="e.tutors.length ? e.tutors.join(', ') : 'Belum ada tutor'"></td>
                                                        <td class="px-md py-sm">
                                                            <template x-if="e.total_meet > 0">
                                                                <div class="flex items-center gap-sm">
                                                                    <div class="w-16 h-1.5 bg-surface-container-highest rounded-full overflow-hidden">
                                                                        <div class="bg-secondary h-full rounded-full" :style="'width:' + e.percent + '%'"></div>
                                                                    </div>
                                                                    <span class="text-[11px] text-on-surface-variant" x-text="e.done + '/' + e.total_meet"></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="e.total_meet === 0">
                                                                <span class="text-on-surface-variant">—</span>
                                                            </template>
                                                        </td>
                                                        <td class="px-md py-sm" x-html="paymentBadge(e.payment_status)"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </table>
                <div class="flex items-center justify-between px-lg py-md border-t border-surface-border bg-surface-container-low">
                    <span class="text-body-sm text-on-surface-variant" x-text="'Halaman ' + page + ' dari ' + lastPage"></span>
                    <div class="flex gap-sm">
                        <button aria-label="Sebelumnya" @click="prevPage" :disabled="page <= 1" class="btn btn-sm btn-ghost" :class="page <= 1 ?'opacity-40' : ''">
                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        </button>
                        <button @click="nextPage" :disabled="page >= lastPage" class="btn btn-sm btn-ghost" :class="page >= lastPage ?'opacity-40' : ''">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
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
                    <label class="fieldset-legend text-on-surface">Jenjang Pendidikan</label>
                    <select name="education_level" id="edit-education-level" class="select w-full">
                        <option value="">— Pilih —</option>
                        <option value="SD">SD</option>
                        <option value="SMP">SMP</option>
                        <option value="SMA">SMA</option>
                        <option value="Kuliah">Kuliah</option>
                        <option value="Umum">Umum</option>
                    </select>
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
    function statusBadge(s) {
        const map = {
            active:    'bg-success/10 text-success',
            waitlist:  'bg-warning/10 text-warning',
            graduate:  'bg-info/10 text-info',
            expired:   'bg-error-container text-on-error-container',
            cancelled: 'bg-surface-container text-on-surface-variant',
        };
        const cls = map[s] ?? 'bg-surface-container text-on-surface-variant';
        return '<span class="inline-flex items-center px-sm py-[2px] rounded-full text-[11px] font-bold uppercase' + cls + '">' + s + '</span>';
    }

    function paymentBadge(s) {
        if (!s) return '—';
        if (s === 'overdue') return '<span class="inline-flex items-center gap-xs px-sm py-[2px] bg-error-container text-on-error-container rounded-full text-[11px] font-bold"><span class="material-symbols-outlined text-[13px]">credit_card_off</span>OVERDUE</span>';
        if (s === 'cicilan') return '<span class="inline-flex items-center gap-xs px-sm py-[2px] bg-tertiary-fixed text-on-tertiary-fixed-variant rounded-full text-[11px] font-bold"><span class="material-symbols-outlined text-[13px]">payments</span>CICILAN</span>';
        return '<span class="inline-flex items-center gap-xs px-sm py-[2px] bg-success/10 text-success rounded-full text-[11px] font-bold"><span class="material-symbols-outlined text-[13px]">check_circle</span>LUNAS</span>';
    }

    function studentsPage() {
        return {
            loading: true,
            filter: 'all',
            rows: [],
            summary: { total: 0, active: 0, inactive: 0, overdue: 0 },
            page: 1,
            lastPage: 1,

            statusBadge,
            paymentBadge,

            setFilter(f) {
                this.filter = this.filter === f ? 'all' : f;
                this.page = 1;
                this.fetchData();
            },

            async fetchData() {
                this.loading = true;
                const params = new URLSearchParams({ filter: this.filter, page: this.page });
                const res = await fetch('{{ route('admin.students.data') }}?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                this.rows = (json.data ?? []).map(s => ({ ...s, _open: false }));
                this.summary = json.summary;
                this.lastPage = json.last_page ?? 1;
                this.loading = false;
            },

            prevPage() {
                if (this.page > 1) { this.page--; this.fetchData(); }
            },

            nextPage() {
                if (this.page < this.lastPage) { this.page++; this.fetchData(); }
            },

            openEditModal(id, name, email, notes, educationLevel) {
                document.getElementById('edit-name').value             = name;
                document.getElementById('edit-email').value            = email;
                document.getElementById('edit-notes').value            = notes;
                document.getElementById('edit-education-level').value  = educationLevel || '';
                document.getElementById('form-edit-student').action    = '/admin/students/' + id;
                document.getElementById('modal-edit-student').showModal();
            },

            openResetModal(id, name) {
                document.getElementById('reset-student-name').textContent = name;
                document.getElementById('form-reset-password').action = '/admin/students/' + id;
                document.getElementById('modal-reset-password').showModal();
            },

            confirmDelete(id, name) {
                if (!confirm('Hapus student ' + name + '? Data tidak bisa dikembalikan.')) return;
                fetch('/admin/students/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    }
                }).then(r => r.json()).then(data => {
                    if (data.success) this.fetchData();
                    else alert(data.message ?? 'Gagal menghapus student.');
                }).catch(() => alert('Terjadi kesalahan. Coba lagi.'));
            },

            init() {
                this.fetchData();
            },
        };
    }
    </script>

</x-app-layout>
