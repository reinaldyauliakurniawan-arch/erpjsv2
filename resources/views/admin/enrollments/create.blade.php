<x-app-layout>
    <x-slot name="title">Tambah Enrollment</x-slot>

    <div class="p-lg space-y-lg" x-data="{
        paymentMethod: 'full upfront',
        installments: [{ amount: '', due_date: '' }],
        schedules: [{ classroom_id: '', day: '', time_block: '' }],
        programs: {{ $programs->map(fn($p) => ['id' => $p->id, 'price' => $p->price])->toJson() }},
        selectedProgramId: '{{ old('program_id') }}',
        get selectedPrice() {
            const p = this.programs.find(p => p.id == this.selectedProgramId);
            return p ? new Intl.NumberFormat('id-ID').format(p.price) : null;
        },
        addInstallment() { this.installments.push({ amount: '', due_date: '' }) },
        removeInstallment(i) { if (this.installments.length > 1) this.installments.splice(i, 1) },
        addSchedule() { this.schedules.push({ classroom_id: '', day: '', time_block: '' }) },
        removeSchedule(i) { if (this.schedules.length > 1) this.schedules.splice(i, 1) },
    }">

        {{-- Flash Error --}}
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
                <h1 class="text-headline-lg font-bold text-on-surface">Pendaftaran Murid Baru</h1>
                <p class="text-body-md text-on-surface-variant">Lengkapi formulir berikut untuk mendaftarkan siswa ke program akademik.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.enrollments.store') }}">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

                {{-- LEFT COLUMN --}}
                <div class="lg:col-span-8 space-y-lg">

                    {{-- Informasi Pribadi --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">person</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Informasi Pribadi</h4>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Nama Lengkap</label>
                                <input type="text" name="new_student[name]" value="{{ old('new_student.name') }}"
                                    class="input w-full @error('new_student.name') input-error @enderror"
                                    placeholder="Budi Santoso" required />
                                @error('new_student.name')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Email Aktif</label>
                                <input type="email" name="new_student[email]" value="{{ old('new_student.email') }}"
                                    class="input w-full @error('new_student.email') input-error @enderror"
                                    placeholder="budi@email.com" required />
                                @error('new_student.email')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">No. HP / WhatsApp <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                                <input type="tel" name="new_student[phone]" value="{{ old('new_student.phone') }}"
                                    class="input w-full @error('new_student.phone') input-error @enderror"
                                    placeholder="+62 812 XXXX XXXX" />
                                @error('new_student.phone')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    {{-- Program & Jadwal --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">auto_awesome</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Pilihan Program</h4>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Program</label>
                                <select name="program_id"
                                    class="select w-full @error('program_id') select-error @enderror"
                                    x-model="selectedProgramId" required>
                                    <option value="" disabled selected>Pilih program...</option>
                                    @foreach($programs as $program)
                                        <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                            {{ $program->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('program_id')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Kelas <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                                <select name="class_session_id" class="select w-full">
                                    <option value="">Tanpa kelas</option>
                                    @foreach($classSessions as $cs)
                                        <option value="{{ $cs->id }}" {{ old('class_session_id') == $cs->id ? 'selected' : '' }}>
                                            {{ $cs->name }} — {{ $cs->program->name }} ({{ $cs->filled_count }} siswa)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Tanggal Mulai</label>
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
                        </div>
                    </section>

                    {{-- Pembayaran --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
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
                                    <option value="ala carte">Ala Carte</option>
                                </select>
                            </div>

                            <div x-show="paymentMethod === 'installment'" x-cloak class="space-y-sm">
                                <template x-for="(inst, i) in installments" :key="i">
                                    <div class="grid gap-md items-end" style="grid-template-columns: 1fr 1fr auto">
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
                                        <button type="button" @click="removeInstallment(i)"
                                            class="btn btn-ghost btn-sm text-error mb-xs"
                                            :disabled="installments.length === 1">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </section>

                    {{-- Jadwal --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                        <div class="flex items-center justify-between mb-lg pb-md border-b border-surface-border">
                            <div class="flex items-center gap-sm">
                                <span class="material-symbols-outlined text-secondary">calendar_month</span>
                                <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Jadwal</h4>
                            </div>
                            <button type="button" @click="addSchedule()" class="btn btn-ghost btn-sm gap-xs">
                                <span class="material-symbols-outlined text-[16px]">add</span>
                                Tambah Jadwal
                            </button>
                        </div>

                        @error('schedules')<p class="label text-error mb-md">{{ $message }}</p>@enderror

                        <div class="space-y-sm">
                            <template x-for="(sch, i) in schedules" :key="i">
                                <div class="grid gap-md items-end" style="grid-template-columns: 1fr 1fr 1fr auto">
                                    <div class="fieldset">
                                        <label class="fieldset-legend text-on-surface">Ruangan</label>
                                        <select :name="`schedules[${i}][classroom_id]`" class="select w-full" required>
                                            <option value="">Pilih...</option>
                                            @foreach($classrooms as $room)
                                                <option value="{{ $room->id }}">{{ $room->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fieldset">
                                        <label class="fieldset-legend text-on-surface">Hari</label>
                                        <select :name="`schedules[${i}][day]`" class="select w-full" required>
                                            <option value="">Pilih...</option>
                                            @foreach(['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $day)
                                                <option value="{{ $day }}">{{ $day }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fieldset" x-data="{ custom: false }">
                                        <label class="fieldset-legend text-on-surface">Time Block</label>
                                        <div x-show="!custom">
                                            <select :name="`schedules[${i}][time_block]`" class="select w-full">
                                                <option value="">Pilih...</option>
                                                @foreach(['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'] as $block)
                                                    <option value="{{ $block }}">{{ $block }}</option>
                                                @endforeach
                                                <option value="__custom__" @click.prevent="custom = true">Custom...</option>
                                            </select>
                                        </div>
                                        <div x-show="custom" class="flex gap-xs items-center" x-cloak>
                                            <input type="text" :name="`schedules[${i}][time_block]`"
                                                placeholder="cth: 07:00-08:30" class="input w-full" />
                                            <button type="button" @click="custom = false"
                                                class="btn btn-ghost btn-sm text-on-surface-variant">
                                                <span class="material-symbols-outlined text-[18px]">undo</span>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" @click="removeSchedule(i)"
                                        class="btn btn-ghost btn-sm text-error mb-xs"
                                        :disabled="schedules.length === 1">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </section>

                </div>

                {{-- RIGHT COLUMN --}}
                <div class="lg:col-span-4 space-y-lg">

                    {{-- Ringkasan --}}
                    <div class="bg-primary-container rounded-lg p-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <h5 class="text-headline-md font-semibold text-on-primary-container mb-lg">Ringkasan Pendaftaran</h5>
                            <div class="space-y-md border-b border-on-primary/20 pb-md mb-md">
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
                            </div>
                            <button type="submit"
                                class="w-full py-md bg-secondary-container text-on-secondary-container rounded-lg font-bold hover:opacity-90 transition-all active:scale-95">
                                Simpan Enrollment
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
                                <p class="text-body-md text-on-surface-variant italic">Program reguler membutuhkan class session yang sudah ada.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Batal --}}
                    <a href="{{ route('admin.enrollments.index') }}"
                        class="btn btn-ghost w-full">Batal</a>

                </div>

            </div>
        </form>
    </div>
</x-app-layout>



