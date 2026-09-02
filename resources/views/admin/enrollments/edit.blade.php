<x-app-layout>
    <x-slot name="title">Edit Enrollment</x-slot>

    @php
        $sessionsByProgram = $classSessions->groupBy('program_id')
            ->map(fn ($g) => $g->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
        // Revenue sudah diakui (ada attendance)? Kalau ya, ganti SISWA dikunci
        // (atribusi tak bisa dibereskan jurnal). Ubah program / total biaya tetap
        // boleh — sistem otomatis posting jurнal penyesuaian selisih saat simpan.
        $revenueRecognized = $recognizedMeetings > 0;
    @endphp

    <div class="p-lg space-y-lg" style="max-width: 60rem"
        x-data="{
            paymentMethod: @js($enrollment->payment_method),
            programId: @js((string) $enrollment->program_id),
            currentProgramId: @js((string) $enrollment->program_id),
            classSessionId: @js((string) ($enrollment->class_session_id ?? '')),
            sessions: @js($sessionsByProgram),
            installments: @js(
                $enrollment->installments->map(fn ($i) => [
                    'id'              => $i->id,
                    'amount'          => (string) (int) $i->amount,
                    'due_date'        => optional($i->due_date)->format('Y-m-d'),
                    'payment_channel' => $i->payment_channel ?? $enrollment->payment_channel,
                    'paid'            => (bool) $i->paid_at,
                    'locked'          => $lockedInstallmentIds->contains($i->id),
                ])->values()
            ),
            get availableSessions() {
                return this.programId === this.currentProgramId ? (this.sessions[this.programId] ?? []) : [];
            },
            get installmentSum() {
                return this.installments.reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
            },
            addInstallment() {
                this.installments.push({ id: null, amount: '', due_date: '', payment_channel: @js($enrollment->payment_channel), paid: false, locked: false });
            },
            removeInstallment(i) {
                if (this.installments[i].locked) { alert('Cicilan ini punya jurnal pembayaran resmi dan tidak bisa dihapus di sini.'); return; }
                this.installments.splice(i, 1);
            },
        }">

        {{-- Back --}}
        <a href="{{ route('admin.enrollments.show', $enrollment->id) }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Detail
        </a>

        <div>
            <h1 class="text-headline-lg font-bold text-on-surface">Edit Enrollment</h1>
            <p class="text-body-md text-on-surface-variant">
                {{ $enrollment->student->user->name }} — {{ $enrollment->program->name }}
            </p>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <div>
                    <p class="font-semibold">Gagal menyimpan. Periksa kembali:</p>
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Dependency notice --}}
        <div role="alert" class="alert alert-info alert-soft">
            <span class="material-symbols-outlined">sync_alt</span>
            <span>
                Perubahan nilai keuangan (total biaya, metode, cicilan, program) otomatis
                menyinkronkan buku besar — sistem memposting <strong>jurnal penyesuaian selisih</strong>
                saat disimpan. <code>payment_status</code> juga dihitung ulang dari kas yang benar-benar masuk.
                @if($revenueRecognized)
                    <br>Enrollment ini sudah punya <strong>{{ $recognizedMeetings }}</strong> pertemuan
                    yang revenue-nya diakui, jadi <strong>ganti siswa dikunci</strong> (atribusi tak bisa dijurnal-penyesuaian).
                @endif
            </span>
        </div>

        <form method="POST" action="{{ route('admin.enrollments.update', $enrollment->id) }}" class="space-y-lg">
            @csrf
            @method('PUT')

            {{-- Siswa & Program --}}
            <section class="app-card space-y-md">
                <h4 class="text-headline-md font-semibold text-on-surface">Siswa & Program</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Siswa</label>
                        <select name="student_id" class="select w-full" @disabled($revenueRecognized)>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}" @selected(old('student_id', $enrollment->student_id) == $s->id)>
                                    {{ $s->user->name }} ({{ $s->user->email }})
                                </option>
                            @endforeach
                        </select>
                        @if($revenueRecognized)<input type="hidden" name="student_id" value="{{ $enrollment->student_id }}">@endif
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Program</label>
                        <select name="program_id" class="select w-full" x-model="programId">
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}" @selected(old('program_id', $enrollment->program_id) == $p->id)>
                                    {{ $p->name }} — Rp {{ number_format($p->price, 0, ',', '.') }} ({{ $p->total_meetings }}x)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Class Session</label>
                        <select name="class_session_id" class="select w-full" x-model="classSessionId"
                            x-effect="if (programId !== currentProgramId) classSessionId = ''">
                            <option value="">— Tidak ada / buat nanti —</option>
                            <template x-for="sess in availableSessions" :key="sess.id">
                                <option :value="String(sess.id)" x-text="sess.name"></option>
                            </template>
                        </select>
                        <p class="label text-on-surface-variant" x-show="programId !== currentProgramId" x-cloak>
                            Program diganti — sesi lama dilepas. Assign sesi baru dari halaman detail setelah simpan.
                        </p>
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Status Enrollment</label>
                        <select name="status" class="select w-full">
                            @foreach(['active','waitlist','graduate','expired','cancelled','refunded'] as $st)
                                <option value="{{ $st }}" @selected(old('status', $enrollment->status) === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <p class="label text-on-surface-variant">Koreksi data — tidak memicu jurnal. Untuk expire/graduate ber-akuntansi, pakai tombol di halaman detail.</p>
                    </div>
                </div>
            </section>

            {{-- Jadwal kontrak --}}
            <section class="app-card space-y-md">
                <h4 class="text-headline-md font-semibold text-on-surface">Tanggal & Sesi</h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-md">
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Tanggal Transaksi</label>
                        <input type="date" name="enrollment_date" class="input w-full"
                            value="{{ old('enrollment_date', optional($enrollment->enrollment_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Tanggal Kadaluarsa</label>
                        <input type="date" name="expiry_date" class="input w-full"
                            value="{{ old('expiry_date', optional($enrollment->expiry_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Sisa Meeting</label>
                        <input type="number" name="remaining_meetings" min="0" class="input w-full"
                            value="{{ old('remaining_meetings', $enrollment->remaining_meetings) }}">
                        <p class="label text-on-surface-variant">Total meeting program: {{ $enrollment->program->total_meetings }}</p>
                    </div>
                </div>
            </section>

            {{-- Pembayaran --}}
            <section class="app-card space-y-md">
                <div class="flex items-center justify-between">
                    <h4 class="text-headline-md font-semibold text-on-surface">Pembayaran</h4>
                    <button type="button" x-show="paymentMethod === 'installment'" @click="addInstallment()"
                        class="btn btn-ghost btn-sm gap-xs">
                        <span class="material-symbols-outlined text-[16px]">add</span> Tambah Cicilan
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Metode</label>
                        <select name="payment_method" class="select w-full" x-model="paymentMethod">
                            <option value="full upfront">Full Upfront</option>
                            <option value="installment">Installment</option>
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Metode Penerimaan</label>
                        <select name="payment_channel" class="select w-full">
                            <option value="cash" @selected(old('payment_channel', $enrollment->payment_channel) === 'cash')>Kas</option>
                            <option value="bank" @selected(old('payment_channel', $enrollment->payment_channel) === 'bank')>Bank</option>
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Total Biaya (IDR)</label>
                        <input type="number" name="total_amount" min="0" step="1" class="input w-full"
                            value="{{ old('total_amount', (int) $enrollment->total_amount) }}">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Status Bayar</label>
                        <input type="text" class="input w-full" value="{{ ucfirst($enrollment->payment_status) }}" disabled>
                        <p class="label text-on-surface-variant">Otomatis dari kas yang masuk saat simpan.</p>
                    </div>
                </div>

                {{-- Cicilan --}}
                <div x-show="paymentMethod === 'installment'" x-cloak class="space-y-sm pt-sm border-t border-surface-border">
                    <div class="flex items-center justify-between">
                        <p class="text-body-md font-medium text-on-surface">Cicilan</p>
                        <p class="text-body-sm" :class="Math.abs(installmentSum - {{ (int) $enrollment->total_amount }}) > 1 && paymentMethod === 'installment' ? 'text-error' : 'text-on-surface-variant'">
                            Jumlah baris: Rp <span x-text="new Intl.NumberFormat('id-ID').format(installmentSum)"></span>
                        </p>
                    </div>
                    <template x-for="(inst, i) in installments" :key="i">
                        <div class="grid gap-sm items-end" style="grid-template-columns: 1fr 1fr 0.8fr auto auto">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface-variant" x-show="i === 0">Jumlah</label>
                                <input type="number" min="0" step="1" class="input input-sm w-full"
                                    :name="`installments[${i}][amount]`" x-model="inst.amount" :disabled="inst.locked">
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface-variant" x-show="i === 0">Jatuh Tempo</label>
                                <input type="date" class="input input-sm w-full"
                                    :name="`installments[${i}][due_date]`" x-model="inst.due_date" :disabled="inst.locked">
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface-variant" x-show="i === 0">Terima</label>
                                <select class="select select-sm w-full" :name="`installments[${i}][payment_channel]`" x-model="inst.payment_channel" :disabled="inst.locked">
                                    <option value="cash">Kas</option>
                                    <option value="bank">Bank</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-xs text-body-sm mb-xs cursor-pointer">
                                <input type="checkbox" class="checkbox checkbox-sm" :name="`installments[${i}][paid]`" value="1"
                                    x-model="inst.paid" :disabled="inst.locked">
                                Lunas
                            </label>
                            <template x-if="inst.id">
                                <input type="hidden" :name="`installments[${i}][id]`" :value="inst.id">
                            </template>
                            <button type="button" @click="removeInstallment(i)" class="btn btn-ghost btn-sm text-error mb-xs" :disabled="inst.locked">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </template>
                    <p class="text-body-sm text-on-surface-variant">Centang "Lunas" mencatat cicilan dibayar (tanggal = jatuh tempo). Tidak membuat jurnal kas otomatis.</p>
                </div>
            </section>

            <div class="flex gap-sm">
                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                    <span class="material-symbols-outlined text-[18px]">save</span> Simpan Perubahan
                </button>
                <a href="{{ route('admin.enrollments.show', $enrollment->id) }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
