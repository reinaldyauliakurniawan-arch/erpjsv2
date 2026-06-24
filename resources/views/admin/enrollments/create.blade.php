<x-app-layout>
    <x-slot name="title">Tambah Enrollment</x-slot>

    <div class="p-lg space-y-lg" x-data="{
        // Submit state — prevents double-submit while server processes enrollment
        submitting: false,
        // Student
        mode: 'new',
        studentQuery: '',
        studentResults: [],
        studentLoading: false,
        showDropdown: false,
        selectedStudent: null,

        // Program
        selectedType: '',
        selectedProgramId: '{{ old('program_id') }}',
        programs: {{ $programs->map(fn($p) => ['id' => $p->id, 'price' => $p->price, 'type' => $p->type])->toJson() }},

        // Session
        selectedDay: '',
        selectedTimeBlock: '',
        eligibleSessions: [],
        sessionLoading: false,
        selectedSessionId: '',
        selectedSession: null,
        privateClassroomId: '',

        // Tutor
        availableTutors: [],
        tutorLoading: false,
        showAllTutors: false,
        selectedTutorIds: [],

        // Payment
        paymentMethod: 'full upfront',
        installments: [{ amount: '', due_date: '', payment_channel: 'cash' }],

        // Override
        remainingOverride: '{{ old('remaining_meetings', '') }}',
        totalAmountOverride: '{{ old('total_amount', '') }}',

        // Computed
        get filteredPrograms() {
            return this.programs.filter(p => p.type === this.selectedType);
        },
        get selectedProgram() {
            return this.programs.find(p => p.id == this.selectedProgramId);
        },
        get selectedPrice() {
            return this.selectedProgram ? new Intl.NumberFormat('id-ID').format(this.selectedProgram.price) : null;
        },
        get isWaitlist() {
            return !this.selectedDay || !this.selectedTimeBlock;
        },
        get summaryTutors() {
            return this.availableTutors.filter(t => this.selectedTutorIds.includes(t.id)).map(t => t.name).join(', ') || '—';
        },

        // Methods
        async searchStudents() {
            if (this.studentQuery.length < 2) { this.studentResults = []; return; }
            this.studentLoading = true;
            this.showDropdown = true;
            try {
                const res = await fetch(`{{ route('admin.enrollments.students.search') }}?q=${encodeURIComponent(this.studentQuery)}`, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                this.studentResults = await res.json();
            } finally { this.studentLoading = false; }
        },
        selectStudent(s) {
            this.selectedStudent = s;
            this.studentQuery = s.name;
            this.showDropdown = false;
        },
        async fetchSessions() {
            if (this.selectedDay && this.selectedTimeBlock) this.fetchTutors();
            if (!this.selectedProgramId || !this.selectedDay || !this.selectedTimeBlock || this.selectedType === 'private') {
                this.eligibleSessions = [];
                return;
            }
            this.sessionLoading = true;
            this.selectedSessionId = '';
            this.selectedSession = null;
            try {
                const p = new URLSearchParams({ program_id: this.selectedProgramId, day: this.selectedDay, time_block: this.selectedTimeBlock });
                const res = await fetch(`{{ route('admin.enrollments.sessions.eligible') }}?${p}`, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                this.eligibleSessions = await res.json();
            } finally { this.sessionLoading = false; }
        },
        async fetchTutors() {
            this.tutorLoading = true;
            this.selectedTutorIds = [];
            try {
                const p = new URLSearchParams({ day: this.selectedDay, time_block: this.selectedTimeBlock });
                const res = await fetch(`{{ route('admin.enrollments.tutors.available') }}?${p}`, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                this.availableTutors = await res.json();
            } finally { this.tutorLoading = false; }
        },
        addInstallment() { this.installments.push({ amount: '', due_date: '', payment_channel: 'cash' }) },
        removeInstallment(i) { if (this.installments.length > 1) this.installments.splice(i, 1) },
        init() {
            this.$watch('selectedProgramId', (val) => {
                const program = this.programs.find(p => p.id == val);
                if (program) {
                    this.totalAmountOverride = program.price;
                }
            });
        },
    }">

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

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg">
            <div class="space-y-xs">
                <a href="{{ route('admin.enrollments.index') }}"
                    class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-on-surface transition-colors mb-xs">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Kembali
                </a>
                <h1 class="text-headline-lg font-bold text-on-surface">Tambah Enrollment</h1>
                <p class="text-body-md text-on-surface-variant">Lengkapi formulir berikut untuk mendaftarkan siswa ke program akademik.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.enrollments.store') }}"
            @submit="submitting = true">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

                {{-- LEFT COLUMN --}}
                <div class="lg:col-span-8 space-y-lg">

                    {{-- 1. INFORMASI SISWA --}}
                    <section class="app-card">
                        <div class="flex items-center justify-between mb-lg pb-md border-b border-surface-border">
                            <div class="flex items-center gap-sm">
                                <span class="material-symbols-outlined text-secondary">person</span>
                                <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Informasi Siswa</h4>
                            </div>
                            <div class="inline-flex rounded-lg overflow-hidden border border-primary-container">
                                <button type="button"
                                    @click="mode = 'new'; selectedStudent = null"
                                    :class="mode ==='new' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
                                    class="px-md py-sm text-body-md font-semibold transition-all">
                                    Murid Baru
                                </button>
                                <button type="button"
                                    @click="mode = 'existing'"
                                    :class="mode ==='existing' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
                                    class="px-md py-sm text-body-md font-semibold border-l border-primary-container transition-all">
                                    Murid Lama
                                </button>
                            </div>
                        </div>

                        {{-- Murid Baru --}}
                        <template x-if="mode === 'new'">
                            <div>
                                <input type="hidden" name="existing_student_id" value="">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                                    <div class="fieldset">
                                        <label class="fieldset-legend text-on-surface">Nama Lengkap</label>
                                        <input type="text" name="new_student[name]" value="{{ old('new_student.name') }}"
                                            class="input w-full @error('new_student.name') input-error @enderror"
                                            placeholder="Budi Santoso" />
                                        @error('new_student.name')<p class="label text-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="fieldset">
                                        <label class="fieldset-legend text-on-surface">Email Aktif</label>
                                        <input type="email" name="new_student[email]" value="{{ old('new_student.email') }}"
                                            class="input w-full @error('new_student.email') input-error @enderror"
                                            placeholder="budi@email.com" />
                                        @error('new_student.email')<p class="label text-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="fieldset">
                                        <label class="fieldset-legend text-on-surface">No. HP / WhatsApp <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                                        <input type="tel" name="new_student[phone]" value="{{ old('new_student.phone') }}"
                                            class="input w-full" placeholder="+62 812 XXXX XXXX" />
                                    </div>
                                    <div class="fieldset">
                                        <label class="fieldset-legend text-on-surface">Jenjang Pendidikan <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                                        <select name="new_student[education_level]" class="select w-full">
                                            <option value="">— Pilih —</option>
                                            <option value="SD">SD</option>
                                            <option value="SMP">SMP</option>
                                            <option value="SMA">SMA</option>
                                            <option value="Kuliah">Kuliah</option>
                                            <option value="Umum">Umum</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Murid Lama --}}
                        <template x-if="mode === 'existing'">
                            <div>
                                <input type="hidden" name="existing_student_id" :value="selectedStudent?.id">
                                <div class="fieldset">
                                    <label class="fieldset-legend text-on-surface">Cari Murid</label>
                                    <div class="relative">
                                        <input type="text" class="input w-full" placeholder="Ketik nama atau email..."
                                            x-model="studentQuery"
                                            @input.debounce.300ms="searchStudents()"
                                            @focus="showDropdown = true"
                                            @click.away="showDropdown = false" />
                                        <div x-show="showDropdown && (studentLoading || studentResults.length > 0 || studentQuery.length > 1)"
                                            class="absolute z-50 w-full mt-xs bg-surface-container-lowest border border-surface-border rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                            <div x-show="studentLoading" class="px-md py-sm text-body-sm text-on-surface-variant">Mencari...</div>
                                            <template x-for="s in studentResults" :key="s.id">
                                                <button type="button" @click="selectStudent(s)"
                                                    class="w-full text-left px-md py-sm hover:bg-surface-container border-b border-surface-border last:border-0">
                                                    <p class="text-body-md font-semibold text-on-surface" x-text="s.name"></p>
                                                    <p class="text-body-sm text-on-surface-variant" x-text="s.email"></p>
                                                    <div class="flex gap-xs flex-wrap mt-xs">
                                                        <template x-for="e in s.enrollments" :key="e.program">
                                                            <span class="text-label-lg px-xs py-0.5 rounded bg-surface-container text-on-surface-variant"
                                                                x-text="e.program + ' · ' + e.status"></span>
                                                        </template>
                                                    </div>
                                                </button>
                                            </template>
                                            <div x-show="!studentLoading && studentResults.length === 0 && studentQuery.length > 1"
                                                class="px-md py-sm text-body-sm text-on-surface-variant">Tidak ditemukan.</div>
                                        </div>
                                    </div>
                                </div>
                                <div x-show="selectedStudent" class="mt-sm p-sm bg-surface-container-low border border-surface-border rounded-lg flex items-center justify-between">
                                    <div>
                                        <p class="text-body-md font-semibold text-on-surface" x-text="selectedStudent?.name"></p>
                                        <p class="text-body-sm text-on-surface-variant" x-text="selectedStudent?.email"></p>
                                    </div>
                                    <button aria-label="Tutup" type="button" @click="selectedStudent = null; studentQuery = ''"
                                        class="text-on-surface-variant hover:text-error transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">close</span>
                                    </button>
                                </div>
                                <p class="text-body-sm text-on-surface-variant mt-sm">Data pribadi murid tidak akan diubah. Hanya enrollment baru yang dibuat.</p>
                            </div>
                        </template>
                    </section>

                    {{-- 2. PROGRAM --}}
                    <section class="app-card">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">auto_awesome</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Pilihan Program</h4>
                        </div>

                        {{-- Tipe --}}
                        <div class="fieldset mb-md">
                            <label class="fieldset-legend text-on-surface">Tipe Program</label>
                            <div class="flex gap-sm flex-wrap">
                                @foreach(['private','semi-private','group'] as $t)
                                <button type="button"
                                    @click="selectedType = '{{ $t }}'; selectedProgramId = ''; selectedSessionId = ''; selectedSession = null"
                                    :class="selectedType ==='{{ $t }}' ? 'bg-primary-container text-on-primary border-primary-container' : 'bg-surface-container-lowest text-on-surface border-surface-border hover:border-primary'"
                                    class="px-md py-sm rounded-lg border text-body-md font-semibold capitalize transition-all">
                                    {{ $t }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        <div x-show="selectedType" class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Program</label>
                                <select name="program_id"
                                    class="select w-full @error('program_id') select-error @enderror"
                                    x-model="selectedProgramId"
                                    @change="fetchSessions()"
                                    required>
                                    <option value="">Pilih program...</option>
                                    @foreach($programs as $program)
                                        <option value="{{ $program->id }}"
                                            data-type="{{ $program->type }}"
                                            x-bind:hidden="selectedType !== '{{ $program->type }}'"
                                            {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                            {{ $program->name }} — Rp {{ number_format($program->price, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('program_id')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Tanggal Transaksi</label>
                                <input type="date" name="enrollment_date" value="{{ old('enrollment_date') }}"
                                    class="input w-full @error('enrollment_date') input-error @enderror" required />
                                @error('enrollment_date')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Tanggal Kadaluarsa</label>
                                <input type="date" name="expiry_date" value="{{ old('expiry_date') }}"
                                    class="input w-full @error('expiry_date') input-error @enderror" required />
                                @error('expiry_date')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Sisa Pertemuan <span class="text-on-surface-variant font-normal">(override opsional)</span></label>
                                <input type="number" name="remaining_meetings" min="0"
                                    class="input w-full" x-model="remainingOverride"
                                    placeholder="Default: total meetings program" />
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Biaya Aktual (IDR) <span class="text-on-surface-variant font-normal">(override opsional)</span></label>
                                <input type="number" name="total_amount" min="0"
                                    class="input w-full" x-model="totalAmountOverride"
                                    placeholder="Default: harga program" />
                            </div>
                        </div>
                    </section>

                    {{-- 3. SESI & JADWAL --}}
                    <section class="app-card"
                        x-show="selectedProgramId" x-transition>
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">calendar_month</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Sesi & Jadwal</h4>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md mb-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Hari <span class="text-on-surface-variant font-normal">(kosong = waitlist)</span></label>
                                <select class="select w-full" x-model="selectedDay" @change="fetchSessions()">
                                    <option value="">— Pilih hari —</option>
                                    @foreach(['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $day)
                                        <option value="{{ $day }}">{{ $day }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Time Block <span class="text-on-surface-variant font-normal">(kosong = waitlist)</span></label>
                                <select class="select w-full" x-model="selectedTimeBlock" @change="fetchSessions()">
                                    <option value="">— Pilih time block —</option>
                                    @foreach(['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'] as $block)
                                        <option value="{{ $block }}">{{ $block }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Waitlist notice --}}
                        <div x-show="!selectedDay || !selectedTimeBlock"
                            class="flex items-center gap-sm p-sm bg-warning/10 border border-warning rounded-lg mb-md">
                            <span class="material-symbols-outlined text-warning text-[20px]">schedule</span>
                            <p class="text-body-sm text-warning">Tanpa hari & time block, student otomatis masuk <strong>waitlist</strong>.</p>
                        </div>

                        <div x-show="sessionLoading" class="text-body-sm text-on-surface-variant py-sm">Memuat sesi...</div>

                        {{-- Private --}}
                        <div x-show="selectedType === 'private' && selectedDay && selectedTimeBlock && !sessionLoading" x-cloak>
                            <div class="flex items-center gap-sm p-sm bg-surface-container-low border border-surface-border rounded-lg mb-md">
                                <span class="material-symbols-outlined text-secondary text-[18px]">info</span>
                                <p class="text-body-sm text-on-surface-variant">Sesi private baru dibuat otomatis atas nama siswa.</p>
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Ruangan</label>
                                <select class="select w-full" x-model="privateClassroomId">
                                    <option value="">Pilih ruangan...</option>
                                    @foreach($classrooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->capacity }} pax)</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Group / Semi-private --}}
                        <div x-show="(selectedType === 'group' || selectedType === 'semi-private') && selectedDay && selectedTimeBlock && !sessionLoading" x-cloak>
                            <input type="hidden" name="class_session_id" :value="selectedSessionId">

                            <div x-show="eligibleSessions.length === 0 && !sessionLoading"
                                class="p-sm bg-surface-container-low border border-surface-border rounded-lg text-body-sm text-on-surface-variant mb-md">
                                Tidak ada sesi eligible untuk slot ini.
                            </div>

                            <div class="space-y-sm">
                                <template x-for="sess in eligibleSessions" :key="sess.id">
                                    <button type="button"
                                        @click="selectedSessionId = sess.id; selectedSession = sess; selectedTutorIds = sess.tutors.map(t => t.id)"
                                        :class="selectedSessionId == sess.id ?'border-primary-container ring-2 ring-primary-container bg-surface-container-low'
                                            : 'border-surface-border hover:border-primary'"
                                        class="w-full text-left p-md rounded-lg border transition-all">
                                        <div class="flex items-start justify-between gap-sm">
                                            <div>
                                                <p class="text-body-md font-semibold text-on-surface" x-text="sess.name"></p>
                                                <p class="text-body-sm text-on-surface-variant" x-text="sess.classroom + ' · ' + sess.day + ' ' + sess.time_block"></p>
                                                <p class="text-body-sm text-on-surface-variant">Pertemuan berjalan: <span class="font-semibold" x-text="sess.finished_meetings"></span></p>
                                                <div class="flex gap-xs flex-wrap mt-xs">
                                                    <template x-for="t in sess.tutors" :key="t.id">
                                                        <span class="text-label-lg px-xs py-0.5 rounded bg-surface-container text-on-surface-variant" x-text="t.name"></span>
                                                    </template>
                                                </div>
                                            </div>
                                            <span class="text-body-sm font-semibold shrink-0"
                                                :class="sess.enrolled_count >= sess.capacity ?'text-error' : 'text-secondary'"
                                                x-text="sess.enrolled_count + '/' + sess.capacity + ' siswa'"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Tutor --}}
                        <div x-show="selectedDay && selectedTimeBlock" x-cloak class="mt-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Tutor</label>
                                <div x-show="tutorLoading" class="text-body-sm text-on-surface-variant">Memuat tutor...</div>
                                <div class="flex gap-sm flex-wrap" x-show="!tutorLoading">
                                    <template x-for="tutor in (showAllTutors ? availableTutors : availableTutors.slice(0, 5))" :key="tutor.id">
                                        <button type="button"
                                            @click="selectedTutorIds.includes(tutor.id) ? selectedTutorIds = selectedTutorIds.filter(id => id !== tutor.id) : selectedTutorIds.push(tutor.id)"
                                            :class="selectedTutorIds.includes(tutor.id) ?'bg-primary-container text-on-primary border-primary-container'
                                                : 'bg-surface-container-lowest text-on-surface border-surface-border hover:border-primary'"
                                            class="px-md py-sm rounded-lg border text-body-md font-semibold transition-all"
                                            x-text="tutor.name"></button>
                                    </template>
                                    <p x-show="availableTutors.length === 0" class="text-body-sm text-on-surface-variant">Tidak ada tutor tersedia.</p>
                                </div>
                                <button type="button" x-show="availableTutors.length > 5" @click="showAllTutors = !showAllTutors"
    class="text-body-sm text-primary underline mt-xs"
    x-text="showAllTutors ? 'Sembunyikan' : 'Lihat semua (' + availableTutors.length + ')'"></button>
                                <template x-for="(tid, idx) in selectedTutorIds" :key="tid">
                                    <input type="hidden" :name="`tutor_ids[${idx}]`" :value="tid">
                                </template>
                            </div>
                        </div>
                    </section>
                    <input type="hidden" name="schedules[0][day]" :value="selectedDay">
                    <input type="hidden" name="schedules[0][time_block]" :value="selectedTimeBlock">
                    <input type="hidden" name="schedules[0][classroom_id]" :value="selectedType === 'private' ? privateClassroomId : (selectedSession?.classroom_id ?? '')">
                    {{-- 4. PEMBAYARAN --}}
                    <section class="app-card">
                        <div class="flex items-center justify-between mb-lg pb-md border-b border-surface-border">
                            <div class="flex items-center gap-sm">
                                <span class="material-symbols-outlined text-secondary">payments</span>
                                <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Pembayaran</h4>
                            </div>
                            <button type="button" x-show="paymentMethod === 'installment'" x-cloak
                                @click="addInstallment()" class="btn btn-ghost btn-sm gap-xs">
                                <span class="material-symbols-outlined text-[16px]">add</span>
                                Tambah Cicilan
                            </button>
                        </div>

                        <div class="space-y-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Metode Pembayaran</label>
                                <select name="payment_method" class="select w-full" x-model="paymentMethod" required>
                                    <option value="full upfront">Full Upfront</option>
                                    <option value="installment">Installment</option>
                                </select>
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Metode Penerimaan</label>
                                <select name="payment_channel" class="select w-full" required>
                                    <option value="cash">Kas</option>
                                    <option value="bank">Bank</option>
                                </select>
                            </div>

                            <div x-show="paymentMethod === 'installment'" x-cloak class="space-y-sm">
                                <template x-for="(inst, i) in installments" :key="i">
                                    <div class="grid gap-md items-end" style="grid-template-columns: 1fr 1fr 1fr auto">
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Jumlah (IDR)</label>
                                            <input type="number" :name="`installments[${i}][amount]`"
                                                x-model="inst.amount" min="0" class="input w-full" />
                                        </div>
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Jatuh Tempo</label>
                                            <input type="date" :name="`installments[${i}][due_date]`"
                                                x-model="inst.due_date" class="input w-full" />
                                        </div>
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Penerimaan</label>
                                            <select :name="`installments[${i}][payment_channel]`" x-model="inst.payment_channel" class="select w-full">
                                                <option value="cash">Kas</option>
                                                <option value="bank">Bank</option>
                                            </select>
                                        </div>
                                        <button aria-label="Hapus" type="button" @click="removeInstallment(i)"
                                            class="btn btn-ghost btn-sm text-error mb-xs"
                                            :disabled="installments.length === 1">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </section>

                </div>

                {{-- RIGHT COLUMN --}}
                <div class="lg:col-span-4 space-y-lg">

                    {{-- Waitlist badge --}}
                    <div x-show="isWaitlist && selectedProgramId" x-cloak
                        class="flex items-center gap-sm p-sm bg-warning text-white rounded-lg font-semibold text-body-md">
                        <span class="material-symbols-outlined text-[18px]">schedule</span>
                        Student akan masuk WAITLIST
                    </div>

                    {{-- Ringkasan --}}
                    <div class="bg-primary-container rounded-lg p-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <h5 class="text-headline-md font-semibold text-on-primary-container mb-lg">Ringkasan Pendaftaran</h5>
                            <div class="space-y-md border-b border-on-primary/20 pb-md mb-md">
                                <div class="flex justify-between items-center">
                                    <span class="text-body-md text-on-primary-container">Tipe</span>
                                    <span class="text-body-md font-semibold text-on-primary capitalize" x-text="selectedType || '—'"></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-body-md text-on-primary-container">Biaya Program</span>
                                    <span class="text-body-md font-semibold text-on-primary">
                                        <span x-show="selectedPrice" x-text="'Rp ' + selectedPrice"></span>
                                        <span x-show="!selectedPrice" class="text-on-primary/60">Pilih program</span>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-body-md text-on-primary-container">Metode</span>
                                    <span class="text-body-md font-semibold text-on-primary capitalize" x-text="paymentMethod"></span>
                                </div>
                                <div class="flex justify-between items-center" x-show="selectedSessionId">
                                    <span class="text-body-md text-on-primary-container">Sesi</span>
                                    <span class="text-body-md font-semibold text-on-primary" x-text="selectedSession?.name ?? '—'"></span>
                                </div>
                                <div class="flex justify-between items-center" x-show="selectedTutorIds.length > 0">
                                    <span class="text-body-md text-on-primary-container">Tutor</span>
                                    <span class="text-body-md font-semibold text-on-primary" x-text="summaryTutors"></span>
                                </div>
                            </div>
                            <button type="submit"
                                :disabled="submitting"
                                :class="{ 'loading': submitting }"
                                class="w-full py-md bg-secondary-container text-on-secondary-container rounded-lg font-bold hover:opacity-90 transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed">
                                <span x-show="!submitting">Simpan Enrollment</span>
                                <span x-show="submitting" x-cloak>Menyimpan…</span>
                            </button>
                        </div>
                        <div class="absolute -right-6 -bottom-6 opacity-10">
                            <span class="material-symbols-outlined text-[100px] text-on-primary">receipt_long</span>
                        </div>
                    </div>

                    {{-- Catatan Admin --}}
                    <div class="bg-surface-container-low border border-surface-border rounded-lg p-lg">
                        <h5 class="text-label-lg text-on-surface-variant uppercase tracking-widest mb-md">Catatan Administrasi</h5>
                        <div class="space-y-md">
                            <div class="flex gap-md">
                                <div class="w-2 h-2 rounded-full bg-secondary mt-sm shrink-0"></div>
                                <p class="text-body-md text-on-surface-variant italic">Password default student: <strong>password123</strong>. Minta student untuk reset setelah login.</p>
                            </div>
                            <div class="flex gap-md">
                                <div class="w-2 h-2 rounded-full bg-secondary mt-sm shrink-0"></div>
                                <p class="text-body-md text-on-surface-variant italic">Program private akan otomatis membuat class session baru.</p>
                            </div>
                            <div class="flex gap-md">
                                <div class="w-2 h-2 rounded-full bg-secondary mt-sm shrink-0"></div>
                                <p class="text-body-md text-on-surface-variant italic">Tanpa hari & time block, student masuk waitlist.</p>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('admin.enrollments.index') }}" class="btn btn-ghost w-full">Batal</a>

                </div>

            </div>
        </form>
    </div>
</x-app-layout>
