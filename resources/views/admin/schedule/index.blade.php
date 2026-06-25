<x-app-layout>
    <x-slot name="title">Schedule</x-slot>

    <script>
        window.classroomMap = @json($classrooms->keyBy('name')->map(fn($c) => $c->id));
        window.allClassSessions = @json($classSessionsJson);

        function isSlotPast(dateStr, timeBlock) {
            const endTime = timeBlock.split('-')[1].trim(); // e.g. "10:30"
            const [hour, minute] = endTime.split(':').map(Number);
            const slotEnd = new Date(dateStr);
            slotEnd.setHours(hour, minute, 0, 0);
            return slotEnd < new Date();
        }

        function guardSlot(dateStr, timeBlock, callback) {
            if (isSlotPast(dateStr, timeBlock)) {
                alert('Slot ini sudah lewat dan tidak bisa diubah.');
                return;
            }
            callback();
        }
    </script>

    <div class="p-lg space-y-lg" x-data="{
        view: 'room',
        day: '{{ ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][now()->dayOfWeek] }}',
        days: ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'],
        init() { if (this.day === 'Minggu' && {{ $weekOffset }} === 0) this.day = 'Senin'; },
        occupancyModal: false,
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
                <div class="flex items-center gap-sm">
                    <p class="text-body-md text-on-surface-variant">Overview jadwal harian per ruangan & tutor</p>

                </div>
                <div class="flex items-center gap-sm mt-sm">
                    @if($weekOffset > -1)
                    <a href="{{ route('admin.schedule.index', ['week' => $weekOffset - 1]) }}" class="btn btn-ghost btn-sm">
                        <span class="material-symbols-outlined text-base">chevron_left</span>
                    </a>
                    @endif
                    <span class="text-body-sm text-on-surface-variant">
                        {{ $weekOffset === 0 ? 'Minggu Ini' : ($weekOffset === 1 ? 'Minggu Depan' : ($weekOffset === -1 ? 'Minggu Lalu' : 'Minggu Ini')) }}
                    </span>
                    @if($weekOffset < 2)
                    <a href="{{ route('admin.schedule.index', ['week' => $weekOffset + 1]) }}" class="btn btn-ghost btn-sm">
                        <span class="material-symbols-outlined text-base">chevron_right</span>
                    </a>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-md">
                <div class="flex gap-sm">
                    <div class="app-card px-md py-sm flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary-container text-lg">meeting_room</span>
                        <div>
                            <p class="text-[9px] font-bold uppercase text-on-surface-variant leading-none mb-xs">Room Occupancy</p>
                            <p class="text-body-md font-bold text-on-surface leading-none">{{ $occupancyRate }}% <span class="text-[10px] font-normal text-on-surface-variant">{{ $occupiedCount }}/{{ $totalSlots }} slot</span></p>
                        </div>
                    </div>
                    <div @click="occupancyModal = true" class="app-card px-md py-sm flex items-center gap-sm cursor-pointer hover:bg-surface-container transition-colors">
                        <span class="material-symbols-outlined text-primary-container text-lg">person</span>
                        <div>
                            <p class="text-[9px] font-bold uppercase text-on-surface-variant leading-none mb-xs">Tutor Occupancy</p>
                            <p class="text-body-md font-bold text-on-surface leading-none">{{ $tutorOccupancyRate }}% <span class="text-[10px] font-normal text-on-surface-variant">{{ $tutorAvailOccupied }}/{{ $tutorAvailTotal }} slot</span></p>
                        </div>
                    </div>
                </div>
                <div class="inline-flex rounded-lg overflow-hidden border border-primary-container">
                <button type="button"
                    @click="view = 'room'"
                    :class="view ==='room' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
                    class="flex items-center gap-xs px-md py-sm text-body-md font-semibold transition-all">
                    <span class="material-symbols-outlined text-[18px]">meeting_room</span>
                    Per Ruangan
                </button>
                <button type="button"
                    @click="view = 'tutor'"
                    :class="view ==='tutor' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
                    class="flex items-center gap-xs px-md py-sm text-body-md font-semibold border-l border-primary-container transition-all">
                    <span class="material-symbols-outlined text-[18px]">person_search</span>
                    Per Tutor
                </button>
            </div>
        </div>
    </div>
        {{-- Occupancy Modal --}}
        <div x-show="occupancyModal" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click.self="occupancyModal = false" @keydown.escape.window="occupancyModal = false">
            <div class="bg-surface-container-lowest rounded-lg shadow-xl w-full max-w-md mx-md flex flex-col max-h-[80vh]">
                <div class="flex items-center justify-between px-lg py-md border-b border-surface-border">
                    <h3 class="text-title-md font-semibold text-on-surface">Tutor Availability</h3>
                    <button aria-label="Tutup" @click="occupancyModal = false" class="btn btn-ghost btn-sm btn-circle">
                        <span class="material-symbols-outlined text-base">close</span>
                    </button>
                </div>
                <div class="overflow-y-auto p-lg space-y-sm" style="max-height: 60vh;">

                    @foreach($tutorStats as $t)
                    <div class="flex items-center justify-between p-sm bg-surface-container border border-surface-border rounded-lg">
                        <div>
                            <p class="text-body-sm font-semibold text-on-surface">{{ $t['name'] }}</p>
                            <p class="text-[10px] text-on-surface-variant">{{ $t['occupied'] }} occupied · {{ $t['free'] }} free · {{ $t['avail'] }} total</p>
                        </div>
                        <p class="text-body-md font-bold {{ $t['ratio'] < 30 ? 'text-success' : ($t['ratio'] < 70 ? 'text-warning' : 'text-error') }}">{{ $t['ratio'] }}%</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        {{-- Day Tabs --}}
        <div class="border-b border-surface-border">
            <nav class="flex gap-xs -mb-px overflow-x-auto">
                <template x-for="d in days" :key="d">
                    <button type="button"
                        @click="day = d"
                        :class="day === d ?'border-b-2 border-primary-container text-primary-container font-semibold'
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
            <div class="app-card"
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
                bookingNotes: '',
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
                            <div x-show='day === @json($d)' x-cloak
                                class="grid gap-xs mb-xs"
                                style="grid-template-columns: 150px repeat(6, 1fr)">
                                <div class="bg-primary-container text-on-primary px-sm py-sm rounded-lg text-[11px] font-bold flex items-center">
                                    {{ $classroom->name }}
                                    <span class="block text-[9px] font-normal opacity-70 ml-xs">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</span>
                                </div>
                                @foreach($timeBlocks as $block)
                                    @php
                                        $schedule  = $daySchedules->where('time_block', $block)->first();
                                        $slotBookings = $dayBookings->where('classroom_id', $classroom->id)->where('time_block', $block);
                                        $booking   = $slotBookings->where('type', 'temporary')->first() ?? $slotBookings->where('type', 'regular_skip')->first();
                                        $isSkipped = $slotBookings->where('type', 'regular_skip')->isNotEmpty() && !$slotBookings->where('type', 'temporary')->count();
                                        $isTemp    = $booking && $booking->type === 'temporary';
                                    @endphp

                                    @if($schedule && !$isSkipped && !$isTemp)
                                        {{-- Reguler aktif --}}
                                        <button type="button"
                                            @click='guardSlot(@json($date), @json($block), () => { modal = true; modalType = "skip"; selectedRoom = @json($classroom->name); selectedDay = @json($d); selectedBlock = @json($block); classroomId = @json($classroom->id); selectedDate = @json($date); selectedClassSessionId = @json($schedule->class_session_id); selectedScheduleId = @json($schedule->id); })'
                                            class="bg-error-container border border-error/30 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-error/20 transition-colors"
                                            title="Jadwal reguler — klik untuk skip">
                                            <span class="material-symbols-outlined text-on-error-container text-sm">lock</span>
                                            <span class="text-[9px] font-bold text-on-error-container text-center leading-tight truncate w-full">
                                                {{ $schedule->classSession?->name ?? '—' }}
                                            </span>
                                        </button>

                                    @elseif($isTemp)
                                        {{-- Temporary booking --}}
                                        <button type="button"
                                            @click='modal = true; modalType = "temp_info";
                                                selectedRoom = @json($classroom->name);
                                                selectedBlock = @json($block);
                                                selectedDate = @json($date);
                                                selectedBookingId = {{ $booking->id }};
                                                bookingNotes = @json($booking->notes ?? "");'
                                            class="bg-amber-50 border border-amber-200 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-amber-100 transition-colors"
                                            title="Temporary booking">
                                            <span class="material-symbols-outlined text-warning text-sm">event</span>
                                            <span class="text-[9px] font-bold text-warning text-center leading-tight truncate w-full">
                                                {{ $booking->tutor?->user->name ?? $booking->notes ?? 'Temp' }}
                                            </span>
                                        </button>

                                    @elseif($isSkipped)
                                        {{-- Di-skip, slot available --}}
                                        <button type="button"
                                            @click='guardSlot(@json($date), @json($block), () => { modal = true; modalType = "temporary"; selectedRoom = @json($classroom->name); selectedDay = @json($d); selectedBlock = @json($block); classroomId = @json($classroom->id); selectedDate = @json($date); })'
                                            class="bg-warning/10 border border-warning/30 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-warning/20 transition-colors"
                                            title="Reguler skip — tersedia untuk booking">
                                            <span class="material-symbols-outlined text-warning text-sm">event_available</span>
                                            <span class="text-[9px] font-bold text-warning text-center leading-tight">Skip</span>
                                        </button>

                                    @else
                                        {{-- Kosong --}}
                                        <button type="button"
                                            @click='guardSlot(@json($date), @json($block), () => { modal = true; modalType = "temporary"; selectedRoom = @json($classroom->name); selectedDay = @json($d); selectedBlock = @json($block); classroomId = @json($classroom->id); selectedDate = @json($date); })'
                                            class="bg-success/10 border border-success/30 px-xs py-xs rounded-lg flex items-center justify-center hover:bg-success/20 transition-colors"
                                            title="Kosong — klik untuk booking">
                                            <span class="material-symbols-outlined text-success text-sm">add_circle</span>
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
                <div x-show='day === @json($d)' x-cloak>
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
                                        <span class="badge badge-soft text-[10px] whitespace-nowrap">{{ $enrollment->student->user->name }}</span>
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
                    <span class="flex items-center gap-xs text-[11px] text-on-surface-variant"><span class="w-3 h-3 rounded-sm inline-block" style="background:#fde68a"></span> Temporary booking</span>
                    <span class="flex items-center gap-xs text-[11px] text-on-surface-variant"><span class="w-3 h-3 rounded-sm inline-block" style="background:#bbf7d0"></span> Kosong</span>
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
                            <button aria-label="Tutup" @click="modal = false" class="btn btn-ghost btn-sm btn-circle">
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
                                    <button type="submit" class="btn bg-warning text-on-warning border-none hover:opacity-90">
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
                                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                                        <span class="material-symbols-outlined text-[18px]">save</span>
                                        Booking
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- TEMP INFO --}}
<div x-show="modalType === 'temp_info'">
    <p class="text-body-sm text-on-surface-variant mb-md">Slot ini sudah di-booking sementara.</p>
    <div x-show="bookingNotes" class="p-sm bg-surface-container-low border border-surface-border rounded-lg mb-md">
        <p class="text-[10px] font-bold uppercase text-on-surface-variant mb-xs">Catatan</p>
        <p class="text-body-sm text-on-surface" x-text="bookingNotes"></p>
    </div>
    <div class="flex justify-end gap-sm mt-md">
        <button type="button" @click="modal = false" class="btn btn-ghost">Tutup</button>
        <form method="POST" :action="'/admin/room-bookings/' + selectedBookingId"
            @submit.prevent="if(confirm('Hapus booking ini?')) $el.submit()">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-error border-none">
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
                            <div x-show='day === @json($d)' x-cloak>
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
                    <div class="app-card app-card--flush">
                        @foreach($days as $d)
                        <div x-show='day === @json($d)' x-cloak>
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
                                <tbody>
                                    @foreach($dayGroups[$d]->sortBy('time_block') as $schedule)
                                    <tbody class="divide-y divide-surface-border" x-data="{ editing: false }">
                                    <tr class="hover:bg-surface-container-low/50 transition-colors group">
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
                                                    <span class="badge badge-soft text-[10px] whitespace-nowrap">{{ $enrollment->student->user->name }}</span>
                                                @empty
                                                    <span class="text-xs text-on-surface-variant">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-lg py-lg text-right">
                                            <div class="flex justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button aria-label="Edit" type="button" @click="editing = !editing"
                                                    class="p-sm hover:bg-surface-container-high rounded-full transition-colors">
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                </button>
                                                <form method="POST" action="{{ route('admin.schedule.destroy', $schedule->id) }}"
                                                    onsubmit="return confirm('Hapus jadwal ini?')">
                                                    @csrf @method('DELETE')
                                                    <button aria-label="Hapus" type="submit" class="p-sm hover:bg-error-container rounded-full text-on-error-container transition-colors">
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
                                                <button aria-label="Simpan" type="submit"
                                                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 mb-xs">
                                                    <span class="material-symbols-outlined text-[18px]">save</span>
                                                </button>
                                                <button aria-label="Tutup" type="button" @click="editing = false" class="btn btn-ghost mb-xs">
                                                    <span class="material-symbols-outlined text-[18px]">close</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    </tbody>
                                    @endforeach
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
            <div class="app-card">
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
                            <div x-show='day === @json($d)' x-cloak>
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
                    <div class="app-card app-card--flush">
                        @foreach($days as $d)
                        <div x-show='day === @json($d)' x-cloak>
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
                                                    <span class="badge badge-soft text-[10px] whitespace-nowrap">{{ $enrollment->student->user->name }}</span>
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
            <div class="app-card">
                <p class="text-body-md text-on-surface-variant text-center py-lg">Belum ada jadwal.</p>
            </div>
            @endif
        </div>

    </div>
</x-app-layout>
