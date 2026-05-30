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
    @php
        $initMode = request('class_session_id') ? ($selectedSession ? 'own' : 'replace') : 'own';
        $initQuery = $selectedSession ? $selectedSession->name . ' — ' . $selectedSession->program->name : '';
        $initSelectedId = request('class_session_id', '');
        $searchUrl = route('tutor.attendance.search-sessions');
        $createUrl = route('tutor.attendance.create');
    @endphp
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg"
        x-data="{
            mode: '{{ $initMode }}',
            query: @js($initQuery),
            results: [],
            selectedId: '{{ $initSelectedId }}',
            loading: false,
            search() {
                if (this.query.length < 2) { this.results = []; return; }
                this.loading = true;
                fetch('{{ $searchUrl }}?q=' + encodeURIComponent(this.query) + '&mode=' + this.mode)
                    .then(r => r.json())
                    .then(data => { this.results = data; this.loading = false; });
            },
            select(item) {
                this.selectedId = item.id;
                this.query = item.name;
                this.results = [];
                window.location.href = '{{ $createUrl }}?class_session_id=' + item.id;
            }
        }">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Pilih Kelas</h2>

        <div class="flex gap-md mb-md">
            <label class="flex items-center gap-xs cursor-pointer">
                <input type="radio" x-model="mode" value="own" class="radio" @change="query = ''; results = []; selectedId = '';">
                <span class="text-sm">Kelas saya</span>
            </label>
            <label class="flex items-center gap-xs cursor-pointer">
                <input type="radio" x-model="mode" value="replace" class="radio" @change="query = ''; results = []; selectedId = '';">
                <span class="text-sm">Replacement</span>
            </label>
        </div>

        <div class="fieldset flex-1 relative">
            <label class="fieldset-legend">Cari Kelas / Program</label>
            <input type="text" class="input w-full" placeholder="Ketik nama kelas atau program..."
                x-model="query" @input.debounce.300ms="search()">
            <div x-show="results.length > 0"
                class="absolute z-10 bg-surface-container border border-surface-border rounded-lg shadow-md w-full mt-xs">
                <template x-for="item in results" :key="item.id">
                    <div class="px-md py-sm text-sm hover:bg-surface-container-high cursor-pointer"
                        x-text="item.name" @click="select(item)"></div>
                </template>
            </div>
            <p x-show="loading" class="text-xs text-on-surface-variant mt-xs">Mencari...</p>
        </div>
    </div>

    {{-- Step 2: Form Absensi --}}
    @if(request('class_session_id') && $enrollments->isNotEmpty())
    <form method="POST" action="{{ route('tutor.attendance.store') }}">
        @csrf
        <input type="hidden" name="class_session_id" value="{{ request('class_session_id') }}">
        @if($initMode === 'replace')
        <input type="hidden" name="is_replacement" value="1">
        @endif

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

                {{-- Replacement --}}
                @if($assignedTutors->isNotEmpty())
                @php $isReplace = $initMode === 'replace'; @endphp
                <div class="fieldset mt-md">
                    <label class="fieldset-legend">Replacement</label>
                    @if(!$isReplace)
                    <label class="flex items-center gap-sm cursor-pointer mb-sm">
                        <input type="checkbox" name="is_replacement" value="1" class="checkbox"
                            {{ old('is_replacement') ? 'checked' : '' }}
                            id="is_replacement_checkbox">
                        <span class="text-sm text-on-surface">Saya mereplace tutor lain di sesi ini</span>
                    </label>
                    @endif
                    <div @if(!$isReplace) id="replacement_select" style="display:{{ old('is_replacement') ? 'block' : 'none' }}" @endif>
                        <select name="replaced_tutor_id" class="select w-full" {{ $isReplace ? 'required' : '' }}>
                            <option value="">— Pilih tutor yang di-replace —</option>
                            @foreach($assignedTutors as $t)
                            <option value="{{ $t->id }}" {{ old('replaced_tutor_id') == $t->id ? 'selected' : '' }}>
                                {{ $t->user->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if(!$isReplace)
                <script>
                    document.getElementById('is_replacement_checkbox').addEventListener('change', function() {
                        document.getElementById('replacement_select').style.display = this.checked ? 'block' : 'none';
                        document.querySelector('[name="replaced_tutor_id"]').required = this.checked;
                    });
                </script>
                @endif
                @endif

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
