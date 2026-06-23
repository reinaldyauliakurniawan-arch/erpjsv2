<x-app-layout>
    <x-slot name="title">Detail Class Session</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 56rem">

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

        <a href="{{ route('admin.class-sessions.index') }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Class Sessions
        </a>

        {{-- Header --}}
        <div class="flex items-start justify-between gap-md">
            <div>
                <h3 class="text-headline-lg font-semibold text-on-surface">{{ $classSession->name }}</h3>
                <div class="flex items-center gap-sm mt-xs">
                    <p class="text-body-md text-on-surface-variant">{{ $classSession->program->name }}</p>
                    <span class="badge badge-soft">{{ ucfirst($classSession->class_type) }}</span>
                    <span class="badge badge-soft {{ $classSession->status ==='active' ? 'badge-success' : 'badge-error' }}">
                        {{ $classSession->status }}
                    </span>
                </div>
            </div>
            <a href="{{ route('admin.class-sessions.edit', $classSession->id) }}"
                class="btn btn-ghost btn-sm gap-xs">
                <span class="material-symbols-outlined text-[16px]">edit</span>
                Edit
            </a>
        </div>

        {{-- Tutor Section --}}
        <div class="app-card space-y-md">
            <div class="flex items-center justify-between">
                <h4 class="text-headline-md font-semibold text-on-surface">Tutor</h4>
                @if($availableTutors->isNotEmpty())
                    <button type="button" onclick="document.getElementById('modal-assign-tutor').showModal()"
                        class="btn btn-ghost btn-sm gap-xs">
                        <span class="material-symbols-outlined text-[16px]">person_add</span>
                        Assign Tutor
                    </button>
                @endif
            </div>

            @if($classSession->tutors->isEmpty())
                <p class="text-body-md text-on-surface-variant">Belum ada tutor di kelas ini.</p>
            @else
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant">
                            <th class="text-left font-medium">Nama</th>
                            <th class="text-left font-medium">Persona</th>
                            <th class="text-left font-medium">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classSession->tutors as $tutor)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="font-semibold text-on-surface">{{ $tutor->user->name }}</td>
                            <td><span class="badge badge-soft">{{ $tutor->persona }}</span></td>
                            <td>
                                <form method="POST"
                                    action="{{ route('admin.class-sessions.tutor-status', [$classSession->id, $tutor->id]) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="select select-sm w-32">
                                        {{-- tutor assignment status (pivot-specific, bukan tutor_availability.status) --}}
                                        <option value="pending" {{ $tutor->pivot->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="confirmed" {{ $tutor->pivot->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.class-sessions.remove-tutor', $classSession->id) }}">
                                    @csrf
                                    <input type="hidden" name="tutor_id" value="{{ $tutor->id }}">
                                    <button type="submit" class="btn btn-ghost btn-sm text-error"
                                        onclick="return confirm('Hapus tutor dari kelas ini?')">
                                        <span class="material-symbols-outlined text-[16px]">person_remove</span>
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Siswa Section --}}
        <div class="app-card space-y-md">
            <div class="flex items-center justify-between">
                <h4 class="text-headline-md font-semibold text-on-surface">
                    Siswa
                    <span class="text-body-md font-normal text-on-surface-variant ml-xs">
                        {{ $classSession->enrollments->count() }} terdaftar
                    </span>
                </h4>
                @if($availableEnrollments->isNotEmpty())
                    <button type="button" onclick="document.getElementById('modal-assign-student').showModal()"
                        class="btn btn-ghost btn-sm gap-xs">
                        <span class="material-symbols-outlined text-[16px]">person_add</span>
                        Tambah Siswa
                    </button>
                @endif
            </div>

            @if($classSession->enrollments->isEmpty())
                <p class="text-body-md text-on-surface-variant">Belum ada siswa di kelas ini.</p>
            @else
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant">
                            <th class="text-left font-medium">Nama Siswa</th>
                            <th class="text-left font-medium">Status</th>
                            <th class="text-left font-medium">Sisa Sesi</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classSession->enrollments as $enrollment)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="font-semibold text-on-surface">{{ $enrollment->student->user->name }}</td>
                            <td>
                                <span class="badge badge-soft {{ match($enrollment->status) {'active' => 'badge-success',
                                    'waitlist' => 'badge-warning',
                                    'graduate' => 'badge-info',
                                    'expired' => 'badge-error',
                                    default => ''
                                } }}">{{ $enrollment->status }}</span>
                            </td>
                            <td class="text-on-surface">{{ $enrollment->remaining_meetings }}x</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-xs">
                                <a href="{{ route('admin.enrollments.show', $enrollment->id) }}"
                                    class="btn btn-ghost btn-sm gap-xs">
                                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                    Detail
                                </a>
                                <form method="POST" action="{{ route('admin.class-sessions.remove', $classSession->id) }}">
                                    @csrf
                                    <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                                    <button type="submit" class="btn btn-ghost btn-sm text-error"
                                        onclick="return confirm('Keluarkan siswa dari kelas ini?')">
                                        <span class="material-symbols-outlined text-[16px]">logout</span>
                                        Keluarkan
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Jadwal Section --}}
        <div class="app-card space-y-md"
            x-data="{ showCustomTime: false }">
            <div class="flex items-center justify-between">
                <h4 class="text-headline-md font-semibold text-on-surface">Jadwal</h4>
            </div>

            {{-- Form tambah jadwal --}}
            <form method="POST" action="{{ route('admin.class-sessions.schedules.store', $classSession->id) }}"
                class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr auto">
                @csrf

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Hari</label>
                    <select name="day" class="select w-full" required>
                        <option value="" disabled selected>Pilih hari...</option>
                        @foreach($days as $day)
                            <option value="{{ $day->value }}">{{ $day->value }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fieldset" x-data>
                    <label class="fieldset-legend text-on-surface">Time Block</label>
                    <select name="time_block" class="select w-full" required
                        @change="$refs.customWrap.style.display = $event.target.value === 'Custom' ? 'block' : 'none'">
                        <option value="" disabled selected>Pilih slot...</option>
                        @foreach($timeBlocks as $block)
                            <option value="{{ $block->value }}">{{ $block->value }}</option>
                        @endforeach
                    </select>
                    <div x-ref="customWrap" style="display:none" class="mt-xs">
                        <input type="text" name="custom_time" class="input w-full"
                            placeholder="Contoh: 07.00-08.30">
                    </div>
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Ruangan</label>
                    <select name="classroom_id" class="select w-full" required>
                        <option value="" disabled selected>Pilih ruangan...</option>
                        @foreach($classrooms as $room)
                            <option value="{{ $room->id }}">{{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end pb-xs">
                    <button aria-label="Tambah" type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]">add</span>
                    </button>
                </div>
            </form>

            {{-- List jadwal --}}
            @if($classSession->schedules->isEmpty())
                <p class="text-body-md text-on-surface-variant">Belum ada jadwal.</p>
            @else
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant">
                            <th class="text-left font-medium">Hari</th>
                            <th class="text-left font-medium">Time Block</th>
                            <th class="text-left font-medium">Ruangan</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classSession->schedules->sortBy(['day', 'time_block']) as $schedule)
                        <tr class="border-b border-surface-border last:border-0">
                            <td class="font-semibold text-on-surface">{{ $schedule->day }}</td>
                            <td class="font-mono text-primary-container font-bold">{{ $schedule->time_block }}</td>
                            <td class="text-on-surface-variant">{{ $schedule->classroom->name }}</td>
                            <td class="text-right">
                                <form method="POST"
                                    action="{{ route('admin.class-sessions.schedules.destroy', [$classSession->id, $schedule->id]) }}">
                                    @csrf @method('DELETE')
                                    <button aria-label="Hapus" type="submit" class="btn btn-ghost btn-sm text-error"
                                        onclick="return confirm('Hapus jadwal ini?')">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Delete --}}
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Hapus Kelas</h4>
            <p class="text-body-sm text-on-surface-variant">Kelas hanya bisa dihapus jika tidak ada siswa aktif.</p>
            <form method="POST" action="{{ route('admin.class-sessions.destroy', $classSession->id) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost gap-sm text-error"
                    onclick="return confirm('Hapus class session ini?')">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                    Hapus Class Session
                </button>
            </form>
        </div>

    </div>

    {{-- Modal Assign Tutor --}}
    <dialog id="modal-assign-tutor" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Assign Tutor</h3>
            <form method="POST" action="{{ route('admin.class-sessions.assign-tutor', $classSession->id) }}"
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
                    <button type="button" onclick="document.getElementById('modal-assign-tutor').close()"
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

    {{-- Modal Assign Siswa --}}
    <dialog id="modal-assign-student" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Siswa ke Kelas</h3>
            <p class="text-body-sm text-on-surface-variant mb-md">
                Hanya menampilkan enrollment aktif di program yang sama yang belum masuk kelas manapun.
            </p>
            <form method="POST" action="{{ route('admin.class-sessions.assign', $classSession->id) }}"
                class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Pilih Siswa</label>
                    <select name="enrollment_id" class="select w-full" required>
                        <option value="" disabled selected>Pilih siswa...</option>
                        @foreach($availableEnrollments as $enrollment)
                            <option value="{{ $enrollment->id }}">{{ $enrollment->student->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-action mt-lg">
                    <button type="button" onclick="document.getElementById('modal-assign-student').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        Tambah
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

</x-app-layout>
