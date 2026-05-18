<x-app-layout>
<x-slot name="title">Input Absensi</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center gap-sm">
        <a href="{{ route('tutor.attendance.index') }}" class="inline-flex items-center gap-xs text-on-surface-variant hover:text-on-surface">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            <span class="text-sm">Kembali</span>
        </a>
    </div>

    <div>
        <h1 class="text-xl font-semibold text-on-surface">Input Absensi</h1>
        <p class="text-sm text-on-surface-variant mt-xs">Pilih kelas lalu tandai kehadiran siswa</p>
    </div>

    @if($errors->any())
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Step 1: Pilih Kelas --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Pilih Kelas</h2>
        <form method="GET" action="{{ route('tutor.attendance.create') }}" class="flex items-end gap-md">
            <div class="fieldset flex-1">
                <label class="fieldset-legend">Kelas / Program</label>
                <select name="class_session_id" class="select w-full"
                    onchange="this.form.submit()">
                    <option value="">— Pilih Kelas —</option>
                    @foreach($classSessions as $cs)
                    <option value="{{ $cs->id }}" {{ request('class_session_id') == $cs->id ? 'selected' : '' }}>
                        {{ $cs->name }} — {{ $cs->program->name }}
                    </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- Step 2: Form Absensi --}}
    @if(request('class_session_id') && $enrollments->isNotEmpty())
    <form method="POST" action="{{ route('tutor.attendance.store') }}">
        @csrf
        <input type="hidden" name="class_session_id" value="{{ request('class_session_id') }}">

        <div class="space-y-md">
            {{-- Info Sesi --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Detail Sesi</h2>
                <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="fieldset">
                        <label class="fieldset-legend">Tanggal <span class="text-error">*</span></label>
                        <input type="date" name="date" class="input w-full"
                            value="{{ old('date', now()->toDateString()) }}" required>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend">Sesi <span class="text-error">*</span></label>
                        <select name="time_block" class="select w-full" required>
                            <option value="">— Pilih —</option>
                            @foreach(['07:00-08:30','08:30-10:00','10:00-11:30','13:00-14:30','14:30-16:00','16:00-17:30','18:00-19:30','19:30-21:00'] as $block)
                            <option value="{{ $block }}" {{ old('time_block') === $block ? 'selected' : '' }}>{{ $block }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend">Ruangan</label>
                        <select name="classroom_id" class="select w-full">
                            <option value="">— Opsional —</option>
                            @foreach($classrooms as $room)
                            <option value="{{ $room->id }}" {{ old('classroom_id') == $room->id ? 'selected' : '' }}>
                                {{ $room->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Catatan Kelas --}}
                <div class="fieldset mt-md">
                    <label class="fieldset-legend">Catatan Kelas</label>
                    <textarea name="notes" class="textarea w-full" rows="3"
                        placeholder="Topik yang dipelajari hari ini, progress, kendala, dll...">{{ old('notes') }}</textarea>
                    <p class="fieldset-label text-xs text-on-surface-variant mt-xs">
                        Catatan ini bisa dilihat admin. Berbeda dengan catatan per siswa di bawah.
                    </p>
                </div>
            </div>

            {{-- Daftar Siswa --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">
                    Kehadiran Siswa ({{ $enrollments->count() }} siswa)
                </h2>
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                            <th class="text-left font-semibold py-sm">Nama Siswa</th>
                            <th class="text-center font-semibold py-sm">Hadir</th>
                            <th class="text-left font-semibold py-sm">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $i => $enrollment)
                        <input type="hidden" name="students[{{ $i }}][enrollment_id]" value="{{ $enrollment->id }}">
                        <tr class="border-b border-surface-border">
                            <td class="py-sm text-sm text-on-surface">{{ $enrollment->student->user->name }}</td>
                            <td class="py-sm text-center">
                                <select name="students[{{ $i }}][is_present]" class="select select-sm w-24">
                                    <option value="1" {{ old("students.$i.is_present", '1') == '1' ? 'selected' : '' }}>Hadir</option>
                                    <option value="0" {{ old("students.$i.is_present") === '0' ? 'selected' : '' }}>Absen</option>
                                </select>
                            </td>
                            <td class="py-sm">
                                <input type="text" name="students[{{ $i }}][notes]"
                                    class="input input-sm w-full"
                                    placeholder="Opsional"
                                    value="{{ old("students.$i.notes") }}">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-sm">
                <a href="{{ route('tutor.attendance.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                    <span class="material-symbols-outlined text-base">save</span>
                    Simpan Absensi
                </button>
            </div>
        </div>
    </form>
    @elseif(request('class_session_id') && $enrollments->isEmpty())
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>Tidak ada siswa aktif di kelas ini</span>
    </div>
    @endif

</div>
</x-app-layout>



