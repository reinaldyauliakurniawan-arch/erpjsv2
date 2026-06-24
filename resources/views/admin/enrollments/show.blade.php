<x-app-layout>
    <x-slot name="title">Detail Enrollment</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 56rem">

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

        {{-- Back --}}
        <a href="{{ route('admin.enrollments.index') }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Enrollments
        </a>

        {{-- Header --}}
        <div class="flex items-start justify-between gap-md">
            <div>
                <h3 class="text-headline-lg font-semibold text-on-surface">{{ $enrollment->student->user->name }}</h3>
                <p class="text-body-md text-on-surface-variant">{{ $enrollment->program->name }}</p>
            </div>
            <div class="flex items-center gap-sm">
                @if($enrollment->status === 'active')
                    <span class="badge badge-soft badge-success">Active</span>
                @elseif($enrollment->status === 'waitlist')
                    <span class="badge badge-soft badge-warning">Waitlist</span>
                @elseif($enrollment->status === 'graduate')
                    <span class="badge badge-soft badge-neutral">Graduate</span>
                @elseif($enrollment->status === 'expired')
                    <span class="badge badge-soft badge-error">Expired</span>
                @endif
            </div>
        </div>

        {{-- Info Student --}}
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Data Student</h4>
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                <div>
                    <p class="text-body-sm text-on-surface-variant">Nama</p>
                    <p class="text-body-md text-on-surface">{{ $enrollment->student->user->name }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Email</p>
                    <p class="text-body-md text-on-surface">{{ $enrollment->student->user->email }}</p>
                </div>
                @if($enrollment->student->user->phone)
                <div>
                    <p class="text-body-sm text-on-surface-variant">No. HP</p>
                    <p class="text-body-md text-on-surface">{{ $enrollment->student->user->phone }}</p>
                </div>
                @endif
                @if($enrollment->classSession)
                <div>
                    <p class="text-body-sm text-on-surface-variant">Kelas</p>
                    <p class="text-body-md text-on-surface">{{ $enrollment->classSession->name }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Info Enrollment --}}
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Detail Enrollment</h4>
            <div class="grid grid-cols-1 gap-md sm:grid-cols-2">
                <div>
                    <p class="text-body-sm text-on-surface-variant">Tanggal Transaksi</p>
                    <p class="text-body-md text-on-surface">{{ \Carbon\Carbon::parse($enrollment->enrollment_date)->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Tanggal Kadaluarsa</p>
                    <p class="text-body-md text-on-surface">{{ \Carbon\Carbon::parse($enrollment->expiry_date)->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Sisa Meeting</p>
                    <p class="text-body-md text-on-surface">{{ $enrollment->remaining_meetings }} / {{ $enrollment->program->total_meetings }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Tutor</p>
                    @if($enrollment->tutors->isEmpty())
                        <p class="text-body-md text-on-surface-variant">—</p>
                    @else
                        <p class="text-body-md text-on-surface">{{ $enrollment->tutors->map(fn($t) => $t->user->name)->join(', ') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tutor (Private) --}}
<div class="app-card space-y-md">
    <div class="flex items-center justify-between">
        <h4 class="text-headline-md font-semibold text-on-surface">Tutor</h4>
        <button type="button" onclick="document.getElementById('modal-assign-tutor').showModal()"
            class="btn btn-ghost btn-sm gap-xs">
            <span class="material-symbols-outlined text-[16px]">person_add</span>
            Assign Tutor
        </button>
    </div>

    @if($enrollment->tutors->isEmpty())
        <p class="text-body-md text-on-surface-variant">Belum ada tutor di-assign.</p>
    @else
        <div class="app-table-wrapper">
<table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant">
                    <th class="text-left font-medium">Nama</th>
                    <th class="text-left font-medium">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($enrollment->tutors as $tutor)
                <tr class="border-b border-surface-border last:border-0">
                    <td class="font-semibold text-on-surface">{{ $tutor->user->name }}</td>
                    <td>
                        <form method="POST"
                            action="{{ route('admin.enrollments.tutor-status', [$enrollment->id, $tutor->id]) }}">
                            @csrf
                            @method('PATCH')
                            <select name="status" onchange="this.form.submit()"
                                class="select select-sm w-32">
                                <option value="pending" {{ $tutor->pivot->status === 'pending' ? 'selected' : '' }}>
                                    Pending
                                </option>
                                <option value="confirmed" {{ $tutor->pivot->status === 'confirmed' ? 'selected' : '' }}>
                                    Confirmed
                                </option>
                            </select>
                        </form>
                    </td>
                    <td class="text-right">
                        <form method="POST"
                            action="{{ route('admin.enrollments.remove-tutor', $enrollment->id) }}">
                            @csrf
                            <input type="hidden" name="tutor_id" value="{{ $tutor->id }}">
                            <button type="submit"
                                class="btn btn-ghost btn-sm gap-xs text-error"
                                onclick="return confirm('Hapus tutor dari enrollment ini?')">
                                <span class="material-symbols-outlined text-[16px]">person_remove</span>
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
</div>
    @endif
</div>

        {{-- Jadwal --}}
        @if($enrollment->schedules->isNotEmpty())
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Jadwal</h4>
            <div class="app-table-wrapper">
<table class="table table-sm w-full">
                <thead>
                    <tr class="border-b border-surface-border text-on-surface-variant">
                        <th class="text-left font-medium">Hari</th>
                        <th class="text-left font-medium">Time Block</th>
                        <th class="text-left font-medium">Ruangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($enrollment->schedules as $schedule)
                    <tr class="border-b border-surface-border">
                        <td class="text-on-surface">{{ $schedule->day }}</td>
                        <td class="text-on-surface">{{ $schedule->time_block }}</td>
                        <td class="text-on-surface">{{ $schedule->classroom->name }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
</div>
        </div>
        @endif

        {{-- Pembayaran --}}
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Pembayaran</h4>
            <div class="grid grid-cols-1 gap-md sm:grid-cols-3">
                <div>
                    <p class="text-body-sm text-on-surface-variant">Metode</p>
                    <p class="text-body-md text-on-surface capitalize">{{ $enrollment->payment_method }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Total</p>
                    <p class="text-body-md text-on-surface">Rp {{ number_format($enrollment->total_amount, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Status Bayar</p>
                    @if($enrollment->payment_status === 'full')
                        <span class="badge badge-soft badge-success">Lunas</span>
                    @elseif($enrollment->payment_status === 'partial')
                        <span class="badge badge-soft badge-warning">Partial</span>
                    @else
                        <span class="badge badge-soft badge-error">Pending</span>
                    @endif
                </div>
            </div>

            {{-- Installments --}}
            @if($enrollment->installments->isNotEmpty())
            <div class="space-y-sm pt-sm">
                <p class="text-body-md font-medium text-on-surface">Cicilan</p>
                <div class="app-table-wrapper">
<table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant">
                            <th class="text-left font-medium">#</th>
                            <th class="text-left font-medium">Jumlah</th>
                            <th class="text-left font-medium">Jatuh Tempo</th>
                            <th class="text-left font-medium">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollment->installments as $i => $inst)
                        <tr class="border-b border-surface-border">
                            <td class="text-on-surface-variant">{{ $i + 1 }}</td>
                            <td class="text-on-surface">Rp {{ number_format($inst->amount, 0, ',', '.') }}</td>
                            <td class="text-on-surface">{{ \Carbon\Carbon::parse($inst->due_date)->format('d M Y') }}</td>
                            <td>
                                @if($inst->paid_at)
                                    <span class="badge badge-soft badge-success">Lunas</span>
                                @else
                                    <span class="badge badge-soft badge-warning">Pending</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if(!$inst->paid_at)
                                <form method="POST"
                                    action="{{ route('admin.enrollments.installments.paid', [$enrollment->id, $inst->id]) }}">
                                    @csrf
                                    <button type="submit"
                                        class="btn btn-ghost btn-sm gap-xs text-on-surface-variant hover:text-primary-container">
                                        <span class="material-symbols-outlined text-[16px]">payments</span>
                                        Tandai Lunas
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
</div>
            </div>
            @endif
        </div>

        {{-- Aksi --}}
        @if($enrollment->status === 'active')
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Aksi</h4>
            <p class="text-body-sm text-on-surface-variant">Perubahan status enrollment bersifat permanen dan akan mempengaruhi pencatatan akuntansi.</p>
            <div class="flex gap-sm">
                <form method="POST" action="{{ route('admin.enrollments.graduate', $enrollment->id) }}">
                    @csrf
                    <button type="submit"
                        class="btn btn-ghost gap-sm"
                        @if($enrollment->remaining_meetings > 0) disabled title="Masih ada {{ $enrollment->remaining_meetings }} meeting tersisa" @endif>
                        <span class="material-symbols-outlined text-[18px]">school</span>
                        Graduate
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.enrollments.expire', $enrollment->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost gap-sm text-error">
                        <span class="material-symbols-outlined text-[18px]">block</span>
                        Expire
                    </button>
                </form>
            </div>
        </div>
        @endif

    </div>

    {{-- Modal Assign Tutor (Private) --}}
<dialog id="modal-assign-tutor" class="modal">
    <div class="modal-box">
        <h3 class="text-headline-md font-semibold text-on-surface mb-md">Assign Tutor</h3>
        <form method="POST" action="{{ route('admin.enrollments.assign-tutor', $enrollment->id) }}"
            class="space-y-md">
            @csrf
            <div class="fieldset">
                <label class="fieldset-legend text-on-surface">Pilih Tutor</label>
                <select name="tutor_id" class="select w-full" required>
                    <option value="" disabled selected>Pilih tutor...</option>
                    @foreach($availableTutors as $tutor)
                        <option value="{{ $tutor->id }}">{{ $tutor->user->name }} — {{ $tutor->persona }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-action mt-lg">
                <button type="button"
                    onclick="document.getElementById('modal-assign-tutor').close()"
                    class="btn btn-ghost">Batal</button>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                    Assign
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
</x-app-layout>
