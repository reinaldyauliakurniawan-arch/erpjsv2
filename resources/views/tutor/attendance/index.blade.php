<x-app-layout>
<x-slot name="title">Absensi</x-slot>

<div class="p-lg space-y-md" x-data="attendancePage()" x-init="init()">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-headline-lg font-semibold text-on-surface">Riwayat Absensi</h1>
            <p class="text-body-sm text-on-surface-variant mt-xs">Semua sesi yang sudah diinput</p>
        </div>
        <button @click="openModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
            <span class="material-symbols-outlined text-base">add</span>
            Input Absensi
        </button>
    </div>

    {{-- Flash --}}
    <div x-show="flash.show" x-transition
        :class="flash.type === 'success' ? 'alert alert-success alert-soft' : 'alert alert-error alert-soft'"
        style="display:none;">
        <span class="material-symbols-outlined" x-text="flash.type === 'success' ? 'check_circle' : 'error'"></span>
        <span x-text="flash.message"></span>
    </div>

    {{-- Summary Cards --}}
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
                placeholder="Ketik untuk mencari..." class="input input-sm w-full">
        </div>
        <div>
            <label class="text-xs text-on-surface-variant mb-xs block">Dari</label>
            <input type="date" x-model="filters.date_from" @change="fetchData()" class="input input-sm">
        </div>
        <div>
            <label class="text-xs text-on-surface-variant mb-xs block">Sampai</label>
            <input type="date" x-model="filters.date_to" @change="fetchData()" class="input input-sm">
        </div>
        <button @click="clearFilters()" class="btn btn-ghost btn-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-base">filter_alt_off</span>
            Reset
        </button>
    </div>

    {{-- Card List --}}
    <div class="space-y-md">
        <template x-for="cls in groupedClasses" :key="cls.class_session_id">
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                {{-- Card Header --}}
                <div class="p-lg flex items-center justify-between cursor-pointer hover:bg-surface-container transition-colors"
                     @click="toggleClassExpand(cls.class_session_id)">
                    <div>
                        <h3 class="text-headline-md font-semibold text-on-surface" x-text="cls.program"></h3>
                        <p class="text-body-sm text-on-surface-variant mt-xs">
                            <span x-text="cls.totalSessions"></span> sesi ·
                            <span x-text="cls.hadirSum"></span>/<span x-text="cls.totalStudents"></span> kehadiran total
                        </p>
                    </div>
                    <div class="flex items-center gap-sm">
                        <span class="badge badge-soft text-body-sm"
                              :class="cls.avgAttendance >= 80 ? 'badge-success' : cls.avgAttendance >= 60 ? 'badge-warning' : 'badge-error'"
                              x-text="cls.avgAttendance + '% hadir'"></span>
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform"
                              :class="expandedClasses.includes(cls.class_session_id) ? 'rotate-180' : ''">expand_more</span>
                    </div>
                </div>

                {{-- Expanded Sessions --}}
                <div x-show="expandedClasses.includes(cls.class_session_id)"
                     x-transition style="display:none;"
                     class="border-t border-surface-border p-lg space-y-md bg-surface-container-low">

                    <template x-for="(session, idx) in cls.sessions.slice(0, cls.visibleCount)" :key="session.id">
                        <div class="bg-surface-container-lowest border border-surface-border rounded-lg p-md">
                            <div class="flex items-center justify-between mb-sm">
                                <div class="flex items-center gap-sm">
                                    <span class="text-body-md font-semibold text-on-surface"
                                          x-text="'Sesi ' + (cls.sessions.length - idx)"></span>
                                    <span class="text-body-sm text-on-surface-variant" x-text="session.date_fmt"></span>
                                    <span class="text-body-sm text-secondary" x-text="session.time_block"></span>
                                </div>
                                <span class="badge badge-soft text-body-sm"
                                      :class="session.mode === 'replacement' ? 'badge-warning' : session.mode === 'team_teaching' ? 'badge-info' : ''"
                                      x-text="session.mode === 'replacement' ? 'Replacement' : session.mode === 'team_teaching' ? 'Team' : 'Own'"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="text-body-sm text-on-surface-variant">
                                    Hadir: <span class="font-semibold text-on-surface" x-text="session.hadir"></span> siswa
                                    <span x-show="session.notes" class="text-xs block mt-xs" x-text="'Materi: ' + session.notes"></span>
                                </div>
                                <button @click="openSessionModal(session)"
                                        class="btn btn-ghost btn-xs text-secondary">
                                    Lihat detail →
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="cls.sessions.length > cls.visibleCount" class="text-center">
                        <button @click="loadMoreSessions(cls.class_session_id)"
                                class="btn btn-ghost btn-sm text-on-surface-variant">
                            <span x-show="!cls.loadingMore">Tampilkan <span x-text="Math.min(5, cls.sessions.length - cls.visibleCount)"></span> sesi lagi</span>
                            <span x-show="cls.loadingMore" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="groupedClasses.length === 0" class="text-center py-lg text-body-sm text-on-surface-variant">
            Belum ada data absensi.
        </div>
    </div>

{{-- MODAL DETAIL SESI --}}
<div x-show="sessionModal.open" x-transition style="display:none;"
    class="fixed inset-0 z-50 flex items-center justify-center p-md">
    <div class="absolute inset-0 bg-black/40" @click="closeSessionModal()"></div>
    <div class="relative bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col z-10">

        <div class="flex items-center justify-between p-lg border-b border-surface-border flex-shrink-0">
            <div>
                <h2 class="text-headline-md font-semibold text-on-surface" x-text="sessionModal.program"></h2>
                <p class="text-body-sm text-on-surface-variant">
                    Sesi <span x-text="sessionModal.sessionNumber"></span> ·
                    <span x-text="sessionModal.date"></span>
                </p>
            </div>
            <button @click="closeSessionModal()" class="btn btn-ghost btn-xs">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <div class="overflow-y-auto flex-1 p-lg space-y-md">
            <div class="flex items-center gap-sm">
                <span class="badge badge-soft text-body-sm"
                      :class="sessionModal.mode === 'replacement' ? 'badge-warning' : sessionModal.mode === 'team_teaching' ? 'badge-info' : ''"
                      x-text="sessionModal.modeLabel"></span>
                <span class="text-body-sm text-on-surface-variant" x-text="sessionModal.modeLabel"></span>
            </div>

            <div class="fieldset">
                <label class="fieldset-legend">Materi</label>
                <p class="text-body-sm text-on-surface" x-text="sessionModal.notes || 'Tidak ada catatan'"></p>
            </div>

            <div class="flex items-center gap-sm p-sm bg-surface-container-low border border-surface-border rounded-lg">
                <span class="material-symbols-outlined text-secondary text-[20px]">group</span>
                <p class="text-body-md text-on-surface">
                    <span class="font-bold" x-text="sessionModal.presentCount"></span>
                    <span class="text-on-surface-variant"> / </span>
                    <span class="font-bold" x-text="sessionModal.totalStudents"></span>
                    <span class="text-on-surface-variant"> siswa hadir</span>
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-sm p-lg border-t border-surface-border flex-shrink-0">
            <button type="button" @click="closeSessionModal()" class="btn btn-ghost">Tutup</button>
        </div>
    </div>
</div>
{{-- MODAL INPUT ABSENSI --}}
<div x-show="modal.open" x-transition style="display:none;"
    class="fixed inset-0 z-50 flex items-center justify-center p-md">
    <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>
    <div class="relative bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10">

        {{-- Modal Header --}}
        <div class="flex items-center justify-between p-lg border-b border-surface-border flex-shrink-0">
            <h2 class="text-base font-semibold text-on-surface">Input Absensi</h2>
            <button @click="closeModal()" class="btn btn-ghost btn-xs">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        {{-- Modal Body (scrollable) --}}
        <div class="overflow-y-auto flex-1 p-lg space-y-lg">

            {{-- Toggle Mode --}}
            <div>
                <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-sm">Mode Mengajar</p>
                <div class="inline-flex bg-surface-container rounded-xl p-xs gap-xs">
                    <template x-for="m in modes" :key="m.value">
                        <button type="button"
                            @click="setMode(m.value)"
                            :class="modal.mode === m.value
                                ? 'btn btn-sm bg-secondary text-on-secondary border-none rounded-lg'
                                : 'btn btn-sm btn-ghost text-on-surface-variant border-none rounded-lg'"
                            x-text="m.label">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Pilih Kelas --}}
            <div class="relative">
                <div class="fieldset">
                    <label class="fieldset-legend">Cari Kelas / Program</label>
                    <input type="text" class="input w-full"
                        placeholder="Ketik nama kelas atau program..."
                        x-model="modal.query"
                        @input.debounce.300ms="searchSessions()"
                        autocomplete="off">
                </div>
                <div x-show="modal.results.length > 0"
                    class="absolute z-20 bg-surface-container border border-surface-border rounded-lg shadow-md w-full mt-xs">
                    <template x-for="item in modal.results" :key="item.id">
                        <div class="px-md py-sm text-sm hover:bg-surface-container-high cursor-pointer"
                            x-text="item.name"
                            @click="selectSession(item)">
                        </div>
                    </template>
                </div>
                <p x-show="modal.searching" class="text-xs text-on-surface-variant mt-xs">Mencari...</p>
            </div>

            {{-- Form Detail (muncul setelah kelas dipilih) --}}
            <div x-show="modal.selectedId" x-transition style="display:none;" class="space-y-md">

                {{-- Detail Sesi --}}
                <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="fieldset">
                        <label class="fieldset-legend">Tanggal <span class="text-error">*</span></label>
                        <input type="date" x-model="modal.date" class="input w-full" required>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend">Sesi <span class="text-error">*</span></label>
                        <select x-model="modal.time_block" class="select w-full" required>
                            <option value="">— Pilih —</option>
                            @foreach(['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'] as $block)
                            <option value="{{ $block }}">{{ $block }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend">Ruangan <span class="text-error">*</span></label>
                        <select x-model="modal.classroom_id" class="select w-full" required>
                            <option value="">— Pilih —</option>
                            @foreach($classrooms as $room)
                            <option value="{{ $room->id }}">{{ $room->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Replacement: pilih tutor yang digantikan --}}
                <div x-show="modal.mode === 'replacement'" style="display:none;">
                    <div class="fieldset">
                        <label class="fieldset-legend">Tutor yang Di-replace <span class="text-error">*</span></label>
                        <select x-model="modal.replaced_tutor_id" class="select w-full">
                            <option value="">— Pilih —</option>
                            <template x-for="t in modal.assignedTutors" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </select>
                        <p x-show="modal.assignedTutors.length === 0" class="text-xs text-on-surface-variant mt-xs">
                            Tidak ada tutor lain yang terdaftar di kelas ini.
                        </p>
                    </div>
                </div>

                {{-- Team Teaching: pilih co-tutor --}}
                <div x-show="modal.mode === 'team_teaching'" style="display:none;">
                    <div class="fieldset">
                        <label class="fieldset-legend">Co-Tutor</label>
                        <div class="space-y-xs mt-xs">
                            <template x-for="t in modal.coTutorCandidates" :key="t.id">
                                <label class="flex items-center gap-sm cursor-pointer">
                                    <input type="checkbox" class="checkbox"
                                        :value="t.id"
                                        @change="toggleCoTutor(t.id)">
                                    <span class="text-sm" x-text="t.name"></span>
                                </label>
                            </template>
                            <p x-show="modal.coTutorCandidates.length === 0" class="text-xs text-on-surface-variant">
                                Tidak ada tutor lain yang terdaftar di kelas ini.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Materi & Catatan Sesi --}}
                <div class="fieldset">
                    <label class="fieldset-legend">Materi & Catatan Sesi</label>
                    <textarea x-model="modal.notes" class="textarea w-full" rows="2"
                        placeholder="Contoh: Grammar Past Tense, latihan listening unit 3, kendala siswa, dll."></textarea>
                </div>

                {{-- Daftar Siswa --}}
                <div>
                    <span class="badge badge-soft badge-success text-body-sm"
                        Kehadiran Siswa (<span x-text="modal.students.length"></span> siswa)
                    </p>
                    <table class="table table-sm w-full">
                        <thead>
                            <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                                <th class="text-left font-semibold py-sm">Nama</th>
                                <th class="text-center font-semibold py-sm w-16">Hadir</th>
                                <th class="text-left font-semibold py-sm">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(student, i) in modal.students" :key="student.enrollment_id">
                                <tr class="border-b border-surface-border transition-colors"
                                    :class="student.is_present ? '' : 'bg-error/5'">
                                    <td class="py-sm text-sm text-on-surface" x-text="student.name"></td>
                                    <td class="py-sm text-center">
                                        <input type="checkbox" class="checkbox"
                                            x-model="student.is_present">
                                    </td>
                                    <td class="py-sm">
                                        <input type="text" class="input input-sm w-full"
                                            x-model="student.notes"
                                            placeholder="Opsional">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        {{-- Modal Footer --}}
        <div class="flex justify-end gap-sm p-lg border-t border-surface-border flex-shrink-0">
            <button type="button" @click="closeModal()" class="btn btn-ghost">Batal</button>
            <button type="button" @click="submitAttendance()"
                :disabled="modal.submitting || !modal.selectedId"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 disabled:opacity-50">
                <span x-show="modal.submitting" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                <span x-show="!modal.submitting" class="material-symbols-outlined text-base">save</span>
                <span x-text="modal.submitting ? 'Menyimpan...' : 'Simpan Absensi'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function attendancePage() {
    return {

        filters: { search: '', date_from: '', date_to: '' },
        summary: {
            unpaid:      {{ $unpaidTotal }},
            paidMonth:   {{ $paidThisMonth }},
            pendingRate: {{ $pendingRateCount }},
        },
        flash: { show: false, type: 'success', message: '' },
        modes: [
            { value: 'own',           label: 'Kelas Saya' },
            { value: 'replacement',   label: 'Replacement' },
            { value: 'team_teaching', label: 'Team Teaching' },
        ],
        expandedClasses: [],
        groupedClasses: [],
        sessionModal: {
            open: false,
            program: '',
            sessionNumber: '',
            date: '',
            mode: '',
            modeLabel: '',
            tutor: '',
            notes: '',
            totalStudents: 0,
            presentStudents: [],
            absentStudents: [],
        },
        modal: {
            open:             false,
            mode:             'own',
            query:            '',
            results:          [],
            searching:        false,
            selectedId:       null,
            selectedName:     '',
            date:             new Date().toISOString().split('T')[0],
            time_block:       '',
            classroom_id:     '',
            notes:            '',
            students:         [],
            replaced_tutor_id: '',
            assignedTutors:   [],
            coTutorCandidates: [],
            selectedCoTutors: [],
            submitting:       false,
        },

        init() {
            this.fetchData();
        },

        openModal() {
            this.modal.open = true;
            this.modal.mode = 'own';
            this.resetModalSession();
            this.searchSessions();
        },

        closeModal() {
            this.modal.open = false;
        },

        resetModalSession() {
            this.modal.query            = '';
            this.modal.results          = [];
            this.modal.selectedId       = null;
            this.modal.selectedName     = '';
            this.modal.date             = new Date().toISOString().split('T')[0];
            this.modal.time_block       = '';
            this.modal.classroom_id     = '';
            this.modal.notes            = '';
            this.modal.students         = [];
            this.modal.replaced_tutor_id = '';
            this.modal.assignedTutors   = [];
            this.modal.coTutorCandidates = [];
            this.modal.selectedCoTutors = [];
        },

        setMode(mode) {
            this.modal.mode = mode;
            this.resetModalSession();
        },

        searchSessions() {
            if (this.modal.query.length === 0 && this.modal.mode !== 'own') { this.modal.results = []; return; }
            this.modal.searching = true;
            fetch(`{{ route('tutor.attendance.search-sessions') }}?q=${encodeURIComponent(this.modal.query)}&mode=${this.modal.mode}`)
                .then(r => r.json())
                .then(data => {
                    this.modal.results   = data;
                    this.modal.searching = false;
                });
        },

        selectSession(item) {
            this.modal.selectedId   = item.id;
            this.modal.selectedName = item.name;
            this.modal.query        = item.name;
            this.modal.results      = [];
            if (item.last_classroom_id) {
                this.modal.classroom_id = String(item.last_classroom_id);
            }
            this.loadHistory(item.id);
        },

        loadHistory(classSessionId) {
            fetch(`{{ route('tutor.attendance.history') }}?class_session_id=${classSessionId}`)
                .then(r => r.json())
                .then(data => {
                    this.modal.assignedTutors    = data.assigned_tutors;
                    this.modal.coTutorCandidates = data.co_tutor_candidates;
                    this.modal.students          = data.enrollments.map(e => ({
                        enrollment_id: e.enrollment_id,
                        name:          e.name,
                        is_present:    true,
                        notes:         '',
                    }));
                });
        },

        toggleCoTutor(id) {
            const idx = this.modal.selectedCoTutors.indexOf(id);
            if (idx === -1) this.modal.selectedCoTutors.push(id);
            else this.modal.selectedCoTutors.splice(idx, 1);
        },

        submitAttendance() {
            if (!this.modal.selectedId || !this.modal.date || !this.modal.time_block || !this.modal.classroom_id) {
                this.showFlash('error', 'Lengkapi semua field yang wajib diisi.');
                return;
            }
            if (this.modal.mode === 'replacement' && !this.modal.replaced_tutor_id) {
                this.showFlash('error', 'Pilih tutor yang di-replace.');
                return;
            }

            this.modal.submitting = true;

            const payload = {
                class_session_id:  this.modal.selectedId,
                date:              this.modal.date,
                time_block:        this.modal.time_block,
                classroom_id:      this.modal.classroom_id,
                notes:             this.modal.notes,
                mode:              this.modal.mode,
                replaced_tutor_id: this.modal.replaced_tutor_id || null,
                co_tutor_ids:      this.modal.selectedCoTutors,
                students:          this.modal.students.map(s => ({
                    enrollment_id: s.enrollment_id,
                    is_present:    s.is_present ? 1 : 0,
                    notes:         s.notes || null,
                })),
            };

            fetch('{{ route('tutor.attendance.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
            .then(r => r.json())
            .then(data => {
                this.modal.submitting = false;
                if (data.success) {
                    this.closeModal();
                    this.fetchData();
                    this.showFlash('success', data.message);
                } else {
                    this.showFlash('error', data.message);
                }
            })
            .catch(() => {
                this.modal.submitting = false;
                this.showFlash('error', 'Terjadi kesalahan koneksi.');
            });
        },

        showFlash(type, message) {
            this.flash = { show: true, type, message };
            setTimeout(() => this.flash.show = false, 4000);
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
                this.groupedClasses = this.groupByClass(data);
                this.updateSummary(data);
            });
        },
        groupByClass(sessions) {
            const map = {};
            sessions.forEach(s => {
                if (!map[s.class_session_id]) {
                    map[s.class_session_id] = {
                        class_session_id: s.class_session_id,
                        program: s.program,
                        totalSessions: 0,
                        hadirSum: 0,
                        totalStudents: 0,
                        sessions: [],
                    };
                }
                map[s.class_session_id].totalSessions++;
                const hadirParts = String(s.hadir || '0/0').split('/');
                const present = parseInt(hadirParts[0]) || 0;
                const total = parseInt(hadirParts[1]) || 0;
                map[s.class_session_id].hadirSum += present;
                map[s.class_session_id].totalStudents += total;
                map[s.class_session_id].sessions.push(s);
            });
            return Object.values(map).map(c => ({
                ...c,
                avgAttendance: c.totalStudents > 0 ? Math.round((c.hadirSum / c.totalStudents) * 100) : 0,
                loadingMore: false,
                visibleCount: 5,
            }));
        },

        toggleClassExpand(id) {
            const idx = this.expandedClasses.indexOf(id);
            if (idx === -1) this.expandedClasses.push(id);
            else this.expandedClasses.splice(idx, 1);
        },

        openSessionModal(session) {
            const hadirParts = String(session.hadir || '0/0').split('/');
            const presentCount = parseInt(hadirParts[0]) || 0;
            const totalCount   = parseInt(hadirParts[1]) || 0;
            this.sessionModal = {
                open: true,
                program: session.program,
                sessionNumber: '',
                date: session.date_fmt,
                mode: session.mode,
                modeLabel: session.mode === 'replacement' ? 'Replacement' : session.mode === 'team_teaching' ? 'Team Teaching' : 'Kelas Sendiri',
                tutor: session.tutor || 'Anda',
                notes: session.notes || '',
                presentCount: presentCount,
                totalStudents: totalCount,
            };
            const cls = this.groupedClasses.find(c => c.class_session_id === session.class_session_id);
            if (cls) {
                const idx = cls.sessions.findIndex(s => s.id === session.id);
                this.sessionModal.sessionNumber = cls.sessions.length - idx;
            }
        },

        closeSessionModal() {
            this.sessionModal.open = false;
        },

        loadMoreSessions(classSessionId) {
            const cls = this.groupedClasses.find(c => c.class_session_id === classSessionId);
            if (!cls) return;
            cls.loadingMore = true;
            setTimeout(() => {
                cls.visibleCount += 5;
                cls.loadingMore = false;
            }, 150);
        },



        updateSummary(data) {
            const now = new Date();
            let unpaid = 0, paidMonth = 0, pendingRate = 0;
            data.forEach(row => {
                if (row.pending_rate) {
                    pendingRate++;
                } else if (!row.paid_at) {
                    unpaid += Number(row.payable);
                } else {
                    const d = new Date(row.paid_at);
                    if (d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()) {
                        paidMonth += Number(row.payable);
                    }
                }
            });
            this.summary = { unpaid, paidMonth, pendingRate };
        },

        clearFilters() {
            this.filters = { search: '', date_from: '', date_to: '' };
            this.expandedClasses = [];
            this.fetchData();
        },

        formatRp(val) {
            return Number(val).toLocaleString('id-ID');
        },
    };
}

function reverseAttendance(id) {
    if (!confirm('Reverse absensi ini? Semua tutor yang terlibat akan ikut di-reverse.')) return;
    fetch(`/tutor/attendance/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-HTTP-Method-Override': 'DELETE',
        },
    })
    .then(r => r.json())
    .then(data => {
        const alpineEl = document.querySelector('[x-data="attendancePage()"]');
        if (alpineEl && alpineEl._x_dataStack) {
            const component = alpineEl._x_dataStack[0];
            component.fetchData();
            component.showFlash(data.success ? 'success' : 'error', data.message);
        }
    });
}
</script>

</x-app-layout>
