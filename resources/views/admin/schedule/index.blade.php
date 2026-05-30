<x-app-layout>
    <x-slot name="title">Schedule</x-slot>

    <script>
        window.classroomMap = @json($classrooms->keyBy('name')->map(fn($c) => $c->id));
        window.allClassSessions = @json($classSessionsJson);
    </script>

    <div class="p-lg space-y-lg" x-data="{
        view: 'room',
        day: 'Senin',
        days: ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']
    }">

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
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-md flex-wrap">
            <div>
                <h2 class="text-headline-lg font-semibold text-on-surface">Schedule</h2>
                <p class="text-body-md text-on-surface-variant">Overview jadwal harian per ruangan & tutor</p>
            </div>
            <div class="inline-flex rounded-lg overflow-hidden border border-primary-container">
                <button type="button"
                    @click="view = 'room'"
                    :class="view === 'room' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
                    class="flex items-center gap-xs px-md py-sm text-body-md font-semibold transition-all">
                    <span class="material-symbols-outlined text-[18px]">meeting_room</span>
                    Per Ruangan
                </button>
                <button type="button"
                    @click="view = 'tutor'"
                    :class="view === 'tutor' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
                    class="flex items-center gap-xs px-md py-sm text-body-md font-semibold border-l border-primary-container transition-all">
                    <span class="material-symbols-outlined text-[18px]">person_search</span>
                    Per Tutor
                </button>
            </div>
        </div>

        {{-- Day Tabs --}}
        <div class="border-b border-surface-border">
            <nav class="flex gap-xs -mb-px overflow-x-auto">
                <template x-for="d in days" :key="d">
                    <button type="button"
                        @click="day = d"
                        :class="day === d
                            ? 'border-b-2 border-primary-container text-primary-container font-semibold'
                            : 'text-on-surface-variant hover:text-on-surface'"
                        class="px-md py-sm text-body-md whitespace-nowrap transition-colors"
                        x-text="d">
                    </button>
                </template>
            </nav>
        </div>

        {{-- VIEW: PER RUANGAN --}}
        <div x-show="view === 'room'" x-cloak class="space-y-lg">

            {{-- Matrix Ketersediaan --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg"
                x-data="{
                    modal: false,
                    modalType: '',
                    selectedRoom: '',
                    selectedDay: '',
                    selectedBlock: '',
                    classroomId: '',
                    selectedDate: '',
                    selectedClassSessionId: '',
                    selectedScheduleId: '',
                    selectedBookingId: '',
                }">
                <div class="flex items-center justify-between mb-lg">
                    <h3 class="text-body-lg font-semibold text-on-surface flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary-container">grid_view</span>
                        Ketersediaan Ruang
                    </h3>
                    <span class="text-body-sm text-on-surface-variant italic">*klik slot untuk aksi</span>
                </div>
                <div class="overflow-x-auto">
                    <div class="min-w-[700px]">
                        @php $timeBlocks = ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00']; @endphp

                        {{-- Time block headers --}}
                        <div class="grid gap-xs mb-xs" style="grid-template-columns: 150px repeat(6, 1fr)">
                            <div></div>
                            @foreach($timeBlocks as $block)
                                <div class="bg-surface-container-low px-xs py-xs text-center rounded-t-lg text-[10px] font-bold text-on-surface-variant uppercase">{{ $block }}</div>
                            @endforeach
                        </div>

                        @foreach($classrooms as $classroom)
                            @foreach($days as $d)
                            @php
                                $date         = $weekDates[$d];
                                $dayBookings  = $bookings->get($date, collect());
                                $daySchedules = $byRoom->get($classroom->name, collect())->get($d, collect());
                            @endphp
                            <div x-show="day === '{{ $d }}'" x-cloak
                                class="grid gap-xs mb-xs"
                                style="grid-template-columns: 150px repeat(6, 1fr)">
                                <div class="bg-primary-container text-on-primary px-sm py-sm rounded-lg text-[11px] font-bold flex items-center">
                                    {{ $classroom->name }}
                                    <span class="block text-[9px] font-normal opacity-70 ml-xs">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</span>
                                </div>
                                @foreach($timeBlocks as $block)
                                    @php
                                        $schedule  = $daySchedules->where('time_block', $block)->first();
                                        $booking   = $dayBookings->where('classroom_id', $classroom->id)->where('time_block', $block)->first();
                                        $isSkipped = $booking && $booking->type === 'regular_skip';
                                        $isTemp    = $booking && $booking->type === 'temporary';
                                    @endphp

                                    @if($schedule && !$isSkipped)
                                        {{-- Reguler aktif --}}
                                        <button type="button"
                                            @click="modal = true; modalType = 'skip';
                                                    selectedRoom = '{{ $classroom->name }}';
                                                    selectedDay = '{{ $d }}';
                                                    selectedBlock = '{{ $block }}';
                                                    classroomId = '{{ $classroom->id }}';
                                                    selectedDate = '{{ $date }}';
                                                    selectedClassSessionId = '{{ $schedule->class_session_id }}';
                                                    selectedScheduleId = '{{ $schedule->id }}';"
                                            class="bg-red-50 border border-red-200 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-red-100 transition-colors"
                                            title="Jadwal reguler — klik untuk skip">
                                            <span class="material-symbols-outlined text-red-400 text-sm">lock</span>
                                            <span class="text-[9px] font-bold text-red-400 text-center leading-tight truncate w-full">
                                                {{ $schedule->classSession?->name ?? '—' }}
                                            </span>
                                        </button>

                                    @elseif($isTemp)
                                        {{-- Temporary booking --}}
                                        <button type="button"
                                            @click="modal = true; modalType = 'temp_info';
                                                selectedRoom = '{{ $classroom->name }}';
                                                selectedBlock = '{{ $block }}';
                                                selectedDate = '{{ $date }}';
                                                selectedBookingId = '{{ $booking->id }}';"
                                            class="bg-blue-50 border border-blue-200 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-blue-100 transition-colors"
                                            title="Temporary booking">
                                            <span class="material-symbols-outlined text-blue-400 text-sm">event</span>
                                            <span class="text-[9px] font-bold text-blue-400 text-center leading-tight truncate w-full">
                                                {{ $booking->tutor?->user->name ?? $booking->notes ?? 'Temp' }}
                                            </span>
                                        </button>

                                    @elseif($isSkipped)
                                        {{-- Di-skip, slot available --}}
                                        <button type="button"
                                            @click="modal = true; modalType = 'temporary';
                                                    selectedRoom = '{{ $classroom->name }}';
                                                    selectedDay = '{{ $d }}';
                                                    selectedBlock = '{{ $block }}';
                                                    classroomId = '{{ $classroom->id }}';
                                                    selectedDate = '{{ $date }}';"
                                            class="bg-yellow-50 border border-yellow-200 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-yellow-100 transition-colors"
                                            title="Reguler skip — tersedia untuk booking">
                                            <span class="material-symbols-outlined text-yellow-500 text-sm">event_available</span>
                                            <span class="text-[9px] font-bold text-yellow-500 text-center leading-tight">Skip</span>
                                        </button>

                                    @else
                                        {{-- Kosong --}}
                                        <button type="button"
                                            @click="modal = true; modalType = 'temporary';
                                                    selectedRoom = '{{ $classroom->name }}';
                                                    selectedDay = '{{ $d }}';
                                                    selectedBlock = '{{ $block }}';
                                                    classroomId = '{{ $classroom->id }}';
                                                    selectedDate = '{{ $date }}';"
                                            class="bg-emerald-50 border border-emerald-200 px-xs py-xs rounded-lg flex items-center justify-center hover:bg-emerald-100 transition-colors"
                                            title="Kosong — klik untuk booking">
                                            <span class="material-symbols-outlined text-emerald-600 text-sm">add_circle</span>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Custom Timeblock Sessions --}}
                @php $standardBlocks = ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00']; @endphp
                @foreach($days as $d)
                <div x-show="day === '{{ $d }}'" x-cloak>
                    @php
                        $customSchedules = collect();
                        foreach($byRoom as $roomName => $dayGroups) {
                            $slots = isset($dayGroups[$d]) ? $dayGroups[$d] : collect();
                            $customSchedules = $customSchedules->merge(
                                $slots->filter(fn($s) => !in_array($s->time_block, $standardBlocks))
                            );
                        }
                    @endphp
                    @if($customSchedules->isNotEmpty())
                    <div class="mt-md">
                        <p class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-sm">Sesi Non-Standar</p>
                        <div class="flex flex-wrap gap-sm">
                            @foreach($customSchedules->sortBy('time_block') as $schedule)
                            <div class="bg-surface-container-lowest border border-surface-border rounded-lg px-md py-sm shadow-sm flex items-center gap-md">
                                <div class="bg-primary-container/10 rounded-lg px-sm py-xs">
                                    <span class="font-mono text-xs font-bold text-primary-container">{{ $schedule->time_block }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-on-surface">{{ $schedule->classSession?->name ?? '—' }}</p>
                                    <p class="text-xs text-on-surface-variant">
                                        {{ $schedule->classroom?->name ?? '—' }} ·
                                        {{ $schedule->classSession?->tutors->map(fn($t) => $t->user->name)->join(', ') ?: '—' }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-xs ml-auto">
                                    @foreach($schedule->classSession?->enrollments ?? [] as $enrollment)
                                        <span class="badge badge-soft text-[10px]">{{ $enrollment->student->user->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach

                {{-- Legend --}}
                <div class="flex items-center gap-md mt-md flex-wrap">
                    <span class="flex items-center gap-xs text-[11px] text-on-surface-variant"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fecaca"></span> Reguler aktif</span>
                    <span class="flex items-center gap-xs text-[11px] text-on-surface-variant"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fef08a"></span> Skip (available)</span>
                    <span class="flex items-center gap-xs text-[11px] text-on-surface-variant"><span class="w-3 h-3 rounded-sm inline-block" style="background:#bfdbfe"></span> Temporary booking</span>
                    <span class="flex items-center gap-xs text-[11px] text-on-surface-variant"><span class="w-3 h-3 rounded-sm inline-block" style="background:#a7f3d0"></span> Kosong</span>
                </div>

                {{-- Modal --}}
                <div x-show="modal" x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                    @keydown.escape.window="modal = false">
                    <div class="bg-surface-container-lowest rounded-lg border border-surface-border w-full max-w-md p-lg space-y-md shadow-xl"
                        @click.outside="modal = false">

                        <div class="flex items-center justify-between">
                            <h4 class="text-body-lg font-semibold text-on-surface"
                                x-text="modalType === 'skip' ? 'Skip Jadwal Reguler' : modalType === 'temporary' ? 'Temporary Booking' : 'Info Booking'">
                            </h4>
                            <button @click="modal = false" class="btn btn-ghost btn-sm btn-circle">
                                <span class="material-symbols-outlined text-[18px]">close</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-sm">
                            <div class="bg-surface-container-low rounded-lg px-md py-sm">
                                <p class="text-[10px] font-bold uppercase text-on-surface-variant">Ruangan</p>
                                <p class="text-body-md font-semibold text-on-surface" x-text="selectedRoom"></p>
                            </div>
                            <div class="bg-surface-container-low rounded-lg px-md py-sm">
                                <p class="text-[10px] font-bold uppercase text-on-surface-variant">Tanggal</p>
                                <p class="text-body-md font-semibold text-on-surface" x-text="selectedDate"></p>
                            </div>
                            <div class="bg-surface-container-low rounded-lg px-md py-sm col-span-2">
                                <p class="text-[10px] font-bold uppercase text-on-surface-variant">Time Block</p>
                                <p class="text-body-md font-semibold text-on-surface" x-text="selectedBlock"></p>
                            </div>
                        </div>

                        {{-- SKIP form --}}
                        <div x-show="modalType === 'skip'">
                            <p class="text-body-sm text-on-surface-variant mb-md">Tandai slot ini sebagai skip. Slot akan jadi available untuk booking sementara.</p>
                            <form method="POST" action="{{ route('admin.room-bookings.store') }}">
                                @csrf
                                <input type="hidden" name="type" value="regular_skip">
                                <input type="hidden" name="classroom_id" :value="classroomId">
                                <input type="hidden" name="date" :value="selectedDate">
                                <input type="hidden" name="time_block" :value="selectedBlock">
                                <input type="hidden" name="class_session_id" :value="selectedClassSessionId">
                                <input type="hidden" name="schedule_id" :value="selectedScheduleId">
                                <div class="fieldset mb-md">
                                    <label class="fieldset-legend">Catatan (opsional)</label>
                                    <input type="text" name="notes" class="input w-full" placeholder="Misal: sakit, izin...">
                                </div>
                                <div class="flex justify-end gap-sm">
                                    <button type="button" @click="modal = false" class="btn btn-ghost">Batal</button>
                                    <button type="submit" class="btn bg-yellow-400 text-yellow-900 border-none hover:opacity-90">
                                        <span class="material-symbols-outlined text-[18px]">event_busy</span>
                                        Skip
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- TEMPORARY BOOKING form --}}
                        <div x-show="modalType === 'temporary'">
                            <form method="POST" action="{{ route('admin.room-bookings.store') }}">
                                @csrf
                                <input type="hidden" name="type" value="temporary">
                                <input type="hidden" name="classroom_id" :value="classroomId">
                                <input type="hidden" name="date" :value="selectedDate">
                                <input type="hidden" name="time_block" :value="selectedBlock">
                                <div class="space-y-md">
                                    <div class="fieldset">
                                        <label class="fieldset-legend">Tutor (opsional)</label>
                                        <select name="tutor_id" class="select w-full">
                                            <option value="">— Tanpa tutor —</option>
                                            @foreach(\App\Models\Tutor::with('user')->get() as $tutor)
                                                <option value="{{ $tutor->id }}">{{ $tutor->user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fieldset">
                                        <label class="fieldset-legend">Class Session (opsional)</label>
                                        <select name="class_session_id" class="select w-full">
                                            <option value="">— Tanpa class session —</option>
                                            @foreach($classSessions as $cs)
                                                <option value="{{ $cs->id }}">{{ $cs->name }} — {{ $cs->program->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fieldset">
                                        <label class="fieldset-legend">Catatan</label>
                                        <input type="text" name="notes" class="input w-full" placeholder="Misal: meeting mendadak...">
                                    </div>
                                </div>
                                <div class="flex justify-end gap-sm mt-md">
                                    <button type="button" @click="modal = false" class="btn btn-ghost">Batal</button>
                                    <button type="submit" class="btn bg-blue-500 text-white border-none hover:opacity-90">
                                        <span class="material-symbols-outlined text-[18px]">save</span>
                                        Booking
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- TEMP INFO --}}
<div x-show="modalType === 'temp_info'">
    <p class="text-body-sm text-on-surface-variant mb-md">Slot ini sudah di-booking sementara.</p>
    <div class="flex justify-end gap-sm mt-md">
        <button type="button" @click="modal = false" class="btn btn-ghost">Tutup</button>
        <form method="POST" :action="'/admin/room-bookings/' + selectedBookingId"
            @submit.prevent="if(confirm('Hapus booking ini?')) $el.submit()">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn border-none" style="--btn-bg: oklch(63.7% .237 25.331); --btn-fg: #fff;">
                <span class="material-symbols-outlined text-[18px]">delete</span>
                Hapus
            </button>
        </form>
    </div>
</div>

                    </div>
                </div>
            </div>

            {{-- Tabel per ruangan --}}
            @php $timeBlocks = ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00']; @endphp
            @foreach($byRoom as $roomName => $dayGroups)
            <div class="grid grid-cols-12 gap-lg">

                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-primary-container text-on-primary rounded-lg p-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-primary-container mb-xs">Ruangan</p>
                            <h4 class="text-body-lg font-bold mb-sm">{{ $roomName }}</h4>
                            @foreach($days as $d)
                            <div x-show="day === '{{ $d }}'" x-cloak>
                                @php
                                    $total = isset($dayGroups[$d]) ? $dayGroups[$d]->count() : 0;
                                    $pct   = round($total / count($timeBlocks) * 100);
                                @endphp
                                <div class="w-full bg-white/20 h-1.5 rounded-full mb-xs">
                                    <div class="bg-white/60 h-full rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                                <p class="text-[11px] text-on-primary-container">{{ $total }} dari {{ count($timeBlocks) }} sesi terisi ({{ $pct }}%)</p>
                            </div>
                            @endforeach
                        </div>
                        <div class="absolute -right-4 -bottom-4 opacity-10">
                            <span class="material-symbols-outlined text-[100px]" style="font-variation-settings: 'FILL' 1;">meeting_room</span>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-9">
                    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                        @foreach($days as $d)
                        <div x-show="day === '{{ $d }}'" x-cloak>
                            @if(isset($dayGroups[$d]) && $dayGroups[$d]->count())
                            <table class="w-full border-collapse">
                                <thead class="bg-surface-container-low border-b border-surface-border">
                                    <tr>
                                        <th class="px-lg py-md text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Sesi</th>
                                        <th class="px-lg py-md text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Kelas & Tutor</th>
                                        <th class="px-lg py-md text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Siswa</th>
                                        <th class="px-lg py-md"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-border">
                                    @foreach($dayGroups[$d]->sortBy('time_block') as $schedule)
                                    <tr class="hover:bg-surface-container-low/50 transition-colors group" x-data="{ editing: false }">
                                        <td class="px-lg py-lg">
                                            <p class="font-mono text-sm font-bold text-primary-container">{{ $schedule->time_block }}</p>
                                            <p class="text-[10px] text-on-surface-variant font-bold uppercase">{{ $roomName }}</p>
                                        </td>
                                        <td class="px-lg py-lg">
                                            <a href="{{ route('admin.class-sessions.show', $schedule->class_session_id) }}"
                                                class="text-body-md font-bold text-on-surface hover:text-primary-container transition-colors">
                                                {{ $schedule->classSession?->name ?? '—' }}
                                            </a>
                                            <p class="text-xs text-on-surface-variant flex items-center gap-xs mt-xs">
                                                <span class="material-symbols-outlined text-[14px]">person</span>
                                                {{ $schedule->classSession?->tutors->map(fn($t) => $t->user->name)->join(', ') ?: '—' }}
                                            </p>
                                        </td>
                                        <td class="px-lg py-lg">
                                            <div class="flex flex-wrap gap-xs">
                                                @forelse($schedule->classSession?->enrollments ?? [] as $enrollment)
                                                    <span class="badge badge-soft text-[10px]">{{ $enrollment->student->user->name }}</span>
                                                @empty
                                                    <span class="text-xs text-on-surface-variant">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-lg py-lg text-right">
                                            <div class="flex justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button type="button" @click="editing = !editing"
                                                    class="p-sm hover:bg-surface-container-high rounded-full transition-colors">
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                </button>
                                                <form method="POST" action="{{ route('admin.schedule.destroy', $schedule->id) }}"
                                                    onsubmit="return confirm('Hapus jadwal ini?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="p-sm hover:bg-red-50 rounded-full text-red-400 transition-colors">
                                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="editing" x-cloak>
                                        <td colspan="4" class="px-lg py-md bg-surface-container-low">
                                            <form method="POST" action="{{ route('admin.schedule.update', $schedule->id) }}"
                                                class="grid gap-md items-end" style="grid-template-columns: 1fr 1fr 1fr auto auto">
                                                @csrf @method('PATCH')
                                                <div class="fieldset">
                                                    <label class="fieldset-legend text-on-surface">Ruangan</label>
                                                    <select name="classroom_id" class="select w-full" required>
                                                        @foreach($classrooms as $room)
                                                            <option value="{{ $room->id }}" {{ $room->id === $schedule->classroom_id ? 'selected' : '' }}>
                                                                {{ $room->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="fieldset">
                                                    <label class="fieldset-legend text-on-surface">Hari</label>
                                                    <select name="day" class="select w-full" required>
                                                        @foreach($days as $dayOpt)
                                                            <option value="{{ $dayOpt }}" {{ $dayOpt === $schedule->day ? 'selected' : '' }}>
                                                                {{ $dayOpt }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="fieldset">
                                                    <label class="fieldset-legend text-on-surface">Time Block</label>
                                                    <select name="time_block" class="select w-full" required>
                                                        @foreach($timeBlocks as $block)
                                                            <option value="{{ $block }}" {{ $block === $schedule->time_block ? 'selected' : '' }}>
                                                                {{ $block }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button type="submit"
                                                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 mb-xs">
                                                    <span class="material-symbols-outlined text-[18px]">save</span>
                                                </button>
                                                <button type="button" @click="editing = false" class="btn btn-ghost mb-xs">
                                                    <span class="material-symbols-outlined text-[18px]">close</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <p class="text-body-md text-on-surface-variant text-center py-lg">Tidak ada jadwal hari ini.</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
            @endforeach

            @if($byRoom->isEmpty())
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                <p class="text-body-md text-on-surface-variant text-center py-lg">Belum ada jadwal.</p>
            </div>
            @endif
        </div>

        {{-- VIEW: PER TUTOR --}}
        <div x-show="view === 'tutor'" x-cloak class="space-y-lg">
            @foreach($byTutor as $tutorName => $dayGroups)
            <div class="grid grid-cols-12 gap-lg">

                <div class="col-span-12 lg:col-span-3">
                    <div class="bg-primary-container text-on-primary rounded-lg p-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-primary-container mb-xs">Tutor</p>
                            <h4 class="text-body-lg font-bold mb-sm">{{ $tutorName }}</h4>
                            @foreach($days as $d)
                            <div x-show="day === '{{ $d }}'" x-cloak>
                                @php $total = isset($dayGroups[$d]) ? $dayGroups[$d]->count() : 0; @endphp
                                <p class="text-[11px] text-on-primary-container">{{ $total }} sesi aktif hari ini</p>
                            </div>
                            @endforeach
                        </div>
                        <div class="absolute -right-4 -bottom-4 opacity-10">
                            <span class="material-symbols-outlined text-[100px]" style="font-variation-settings: 'FILL' 1;">person</span>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-9">
                    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                        @foreach($days as $d)
                        <div x-show="day === '{{ $d }}'" x-cloak>
                            @if(isset($dayGroups[$d]) && $dayGroups[$d]->count())
                            <table class="w-full border-collapse">
                                <thead class="bg-surface-container-low border-b border-surface-border">
                                    <tr>
                                        <th class="px-lg py-md text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Sesi</th>
                                        <th class="px-lg py-md text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Kelas</th>
                                        <th class="px-lg py-md text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Siswa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-border">
                                    @foreach($dayGroups[$d]->sortBy('time_block') as $schedule)
                                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                                        <td class="px-lg py-lg">
                                            <p class="font-mono text-sm font-bold text-primary-container">{{ $schedule->time_block }}</p>
                                            <p class="text-[10px] text-on-surface-variant font-bold uppercase">{{ $schedule->classroom->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-lg py-lg">
                                            <a href="{{ route('admin.class-sessions.show', $schedule->class_session_id) }}"
                                                class="text-body-md font-bold text-on-surface hover:text-primary-container transition-colors">
                                                {{ $schedule->classSession?->name ?? '—' }}
                                            </a>
                                            <p class="text-xs text-on-surface-variant">{{ $schedule->classSession?->program->name ?? '' }}</p>
                                        </td>
                                        <td class="px-lg py-lg">
                                            <div class="flex flex-wrap gap-xs">
                                                @forelse($schedule->classSession?->enrollments ?? [] as $enrollment)
                                                    <span class="badge badge-soft text-[10px]">{{ $enrollment->student->user->name }}</span>
                                                @empty
                                                    <span class="text-xs text-on-surface-variant">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <p class="text-body-md text-on-surface-variant text-center py-lg">Tidak ada jadwal hari ini.</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
            @endforeach

            @if($byTutor->isEmpty())
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                <p class="text-body-md text-on-surface-variant text-center py-lg">Belum ada jadwal.</p>
            </div>
            @endif
        </div>

    </div>
</x-app-layout>
