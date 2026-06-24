<x-app-layout>
<script>
function isSlotPast(dateStr, timeBlock) {
    const endTime = timeBlock.split('-')[1].trim();
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
<x-slot name="title">Jadwal</x-slot>

<div class="p-lg space-y-lg" x-data="{
    view: '{{ collect($myByDay)->isNotEmpty() ? 'mine' : 'room' }}',
    day: '{{ ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][now()->dayOfWeek] }}',
    days: ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'],
    modal: false,
    modalType: '',
    selectedRoom: '',
    selectedBlock: '',
    selectedDate: '',
    classroomId: '',
    bookingId: null,
    bookingNotes: '',
    selectedScheduleId: '',
}" x-init="
    if (day === 'Minggu' && {{ $weekOffset }} === 0) day = 'Senin';
">

    @if(session('success'))
    <div class="alert alert-success alert-soft">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Header + Week Nav --}}
    <div class="flex items-center justify-between flex-wrap gap-md">
        <div>
            <h2 class="text-headline-lg font-semibold text-on-surface">Jadwal</h2>
            <p class="text-sm text-on-surface-variant mt-xs">
                {{ $weekStart->isoFormat('D MMM') }} — {{ $weekStart->copy()->endOfWeek()->isoFormat('D MMM YYYY') }}
            </p>
        </div>
        <div class="flex items-center gap-sm">
            @if($weekOffset > -1)
            <a href="{{ route('tutor.schedule.index', ['week' => $weekOffset - 1]) }}"
                class="btn btn-ghost btn-sm">
                <span class="material-symbols-outlined text-base">chevron_left</span>
                Minggu Lalu
            </a>
            @endif
            @if($weekOffset !== 0)
            <a href="{{ route('tutor.schedule.index') }}"
                class="btn btn-ghost btn-sm text-primary-container">
                Minggu Ini
            </a>
            @endif
            @if($weekOffset < 2)
            <a href="{{ route('tutor.schedule.index', ['week' => $weekOffset + 1]) }}"
                class="btn btn-ghost btn-sm">
                Minggu Depan
                <span class="material-symbols-outlined text-base">chevron_right</span>
            </a>
            @endif
        </div>
    </div>

    {{-- Toggle --}}
    <div class="inline-flex rounded-lg overflow-hidden border border-primary-container">
        <button type="button"
            @click="view = 'mine'"
            :class="view ==='mine' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
            class="flex items-center gap-xs px-md py-sm text-sm font-semibold transition-all">
            <span class="material-symbols-outlined text-[18px]">person</span>
            Jadwal Saya
        </button>
        <button type="button"
            @click="view = 'room'"
            :class="view ==='room' ? 'bg-primary-container text-on-primary' : 'bg-surface-container-lowest text-primary-container hover:bg-surface'"
            class="flex items-center gap-xs px-md py-sm text-sm font-semibold border-l border-primary-container transition-all">
            <span class="material-symbols-outlined text-[18px]">meeting_room</span>
            Ruangan
        </button>
    </div>

    {{-- VIEW: JADWAL SAYA --}}
    <div x-show="view === 'mine'" x-cloak class="space-y-md">

        @php
            $todayDay = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][now()->dayOfWeek];
            $todaySchedules = $myByDay->get($todayDay, collect());
        @endphp

        <div class="app-card">
            <div class="flex items-center justify-between mb-md">
                <h4 class="text-sm font-semibold text-on-surface">
                    Sesi Hari Ini
                    <span class="text-on-surface-variant font-normal">({{ now()->isoFormat('dddd, D MMM') }})</span>
                </h4>
                <a href="{{ route('tutor.attendance.index') }}"
                    class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Input Absensi
                </a>
            </div>
            @if($todaySchedules->isEmpty())
                <p class="text-sm text-on-surface-variant">Tidak ada sesi hari ini.</p>
            @else
                <div class="app-table-wrapper">
<table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                            <th class="text-left font-medium py-sm">Sesi</th>
                            <th class="text-left font-medium py-sm">Kelas & Siswa</th>
                            <th class="text-left font-medium py-sm">Ruangan</th>
                            <th class="text-left font-medium py-sm">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todaySchedules->sortBy('time_block') as $slot)
                        <tr class="border-b border-surface-border">
                            <td class="py-sm text-sm font-mono text-primary-container">{{ $slot->time_block }}</td>
                            <td class="py-sm text-sm text-on-surface">
                                {{ $slot->classSession?->name ?? '—' }}
                                <span class="text-on-surface-variant text-xs block">
                                    {{ $slot->classSession?->enrollments->map(fn($e) => $e->student->user->name)->join(', ') ?: '—' }}
                                </span>
                            </td>
                            <td class="py-sm text-sm text-on-surface-variant">{{ $slot->classroom?->name ?? '—' }}</td>
                            <td class="py-sm">
                                @php
                                    $todayDate = now()->toDateString();
                                    $alreadySkipped = $slot->roomBookings
                                        ->where('type', 'regular_skip')
                                        ->filter(fn($b) => \Carbon\Carbon::parse($b->date)->toDateString() === $todayDate)
                                        ->isNotEmpty();
                                @endphp
                                @if(!$alreadySkipped)
                                <button type="button"
                                    @click="guardSlot(@json($todayDate), @json($slot->time_block), () => { modal = true; modalType = 'skip'; selectedBlock = @json($slot->time_block); selectedDate = @json($todayDate); classroomId = @json($slot->classroom_id); selectedScheduleId = @json($slot->id); })"
                                    class="btn btn-xs btn-ghost text-warning hover:bg-yellow-50">
                                    <span class="material-symbols-outlined text-[14px]">event_busy</span>
                                    Skip
                                </button>
                                @else
                                @php
                                    $skipBooking = $slot->roomBookings
                                        ->where('type', 'regular_skip')
                                        ->first(fn($b) => \Carbon\Carbon::parse($b->date)->toDateString() === $todayDate);
                                @endphp
                                <div class="text-xs text-warning font-semibold">
                                    Skipped
                                    @if($skipBooking?->notes)
                                        <span class="block text-on-surface-variant font-normal italic">{{ $skipBooking->notes }}</span>
                                    @endif
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
</div>
            @endif
        </div>

        <div class="app-card">
            <h4 class="text-sm font-semibold text-on-surface mb-md">Jadwal Mingguan</h4>
            @if($myByDay->isEmpty())
                <p class="text-sm text-on-surface-variant">Belum ada jadwal tetap.</p>
            @else
                <div class="space-y-md">
                    @foreach(['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $d)
                    @if(isset($myByDay[$d]))
                    <div>
                        <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-xs">{{ $d }}</p>
                        <div class="flex flex-wrap gap-sm">
                            @foreach($myByDay[$d]->sortBy('time_block') as $slot)
                            <div class="bg-primary-container/10 border border-primary-container/30 rounded-lg px-md py-sm text-xs flex items-center gap-sm">
                                <span class="font-mono font-semibold text-primary-container">{{ $slot->time_block }}</span>
                                <span class="text-on-surface-variant">·</span>
                                <span class="text-on-surface">{{ $slot->classSession?->name ?? '—' }}</span>
                                <span class="text-on-surface-variant">·</span>
                                <span class="text-on-surface-variant">{{ $slot->classroom?->name ?? '—' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- VIEW: RUANGAN (Matrix) --}}
    <div x-show="view === 'room'" x-cloak class="space-y-md">

        <div class="border-b border-surface-border">
            <nav class="flex gap-xs -mb-px overflow-x-auto">
                <template x-for="d in days" :key="d">
                    <button type="button"
                        @click="day = d"
                        :class="day === d ?'border-b-2 border-primary-container text-primary-container font-semibold'
                            : 'text-on-surface-variant hover:text-on-surface'"
                        class="px-md py-sm text-sm whitespace-nowrap transition-colors"
                        x-text="d">
                    </button>
                </template>
            </nav>
        </div>

@php $timeBlocks = ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00']; @endphp

        <div class="overflow-x-auto">
        <div class="min-w-[700px] space-y-xs">

            <div class="grid gap-xs" style="grid-template-columns: 140px repeat({{ count($timeBlocks) }}, 1fr)">
                <div></div>
                @foreach($timeBlocks as $block)
                <div class="bg-surface-container-low px-xs py-xs text-center rounded-t-lg text-[10px] font-bold text-on-surface-variant uppercase">
                    {{ $block }}
                </div>
                @endforeach
            </div>

            @foreach($classrooms as $classroom)
                @foreach($days as $d)
                @php
                    $date         = $weekDates[$d];
                    $dayBookings  = $bookings->get($date, collect());
                    $daySchedules = $byRoom->get($classroom->name, collect())->get($d, collect());
                    $isPast       = \Carbon\Carbon::parse($date)->isPast() && !\Carbon\Carbon::parse($date)->isToday();
                @endphp
                <div x-show="day === '{{ $d }}'" x-cloak
                    class="grid gap-xs"
                    style="grid-template-columns: 140px repeat({{ count($timeBlocks) }}, 1fr)">

                    <div class="bg-primary-container text-on-primary px-sm py-sm rounded-lg text-[11px] font-bold flex items-center justify-between">
                        <span>{{ $classroom->name }}</span>
                        <span class="text-[9px] font-normal opacity-70">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</span>
                    </div>

                    @foreach($timeBlocks as $block)
                    @php
                        $schedule  = $daySchedules->where('time_block', $block)->first();
                        $slotBookings = $dayBookings->where('classroom_id', $classroom->id)->where('time_block', $block);
                        $booking   = $slotBookings->where('type', 'temporary')->first() ?? $slotBookings->where('type', 'regular_skip')->first();
                        $isSkipped = $slotBookings->where('type', 'regular_skip')->isNotEmpty() && !$slotBookings->where('type', 'temporary')->count();
                        $isTemp    = $booking && $booking->type === 'temporary';
                        $isMyTemp  = $isTemp && $booking->tutor_id === $tutor->id;
                        $isMySlot  = $schedule && $schedule->classSession?->tutors?->contains('id', $tutor->id);
                    @endphp

                    @if($schedule && !$isSkipped && !$isTemp)
                        <div class="{{ $isMySlot ?'bg-primary-container/20 border-primary-container' : 'bg-error-container border-error/30' }} border px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs"
                            title="{{ $isMySlot ? 'Jadwal saya' : 'Reguler' }}">
                            <span class="material-symbols-outlined {{ $isMySlot ?'text-primary-container' : 'text-on-error-container' }} text-sm">
                                {{ $isMySlot ? 'star' : 'lock' }}
                            </span>
                            <span class="text-[9px] font-bold {{ $isMySlot ?'text-primary-container' : 'text-on-error-container' }} text-center leading-tight truncate w-full">
                                {{ $schedule->classSession?->name ?? '—' }}
                            </span>
                        </div>

                    @elseif($isMyTemp)
                        <button type="button"
                            @click="guardSlot(@json($date), @json($block), () => { modal = true; modalType = 'cancel'; selectedRoom = @json($classroom->name); selectedBlock = @json($block); selectedDate = @json($date); bookingId = {{ $booking->id }}; bookingNotes = {{ json_encode($booking->notes ?? '') }}; })"
                            class="bg-warning/10 border border-warning/30 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs hover:bg-amber-100 transition-colors"
                            title="Booking saya — klik untuk cancel">
                            <span class="material-symbols-outlined text-on-tertiary-container text-sm">event_available</span>
                            <span class="text-[9px] font-bold text-on-tertiary-container text-center leading-tight">Saya</span>
                        </button>

                    @elseif($isTemp)
                        <div class="bg-warning/10 border border-amber-200 px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs opacity-60">
                            <span class="material-symbols-outlined text-warning text-sm">event_busy</span>
                            <span class="text-[9px] font-bold text-warning text-center leading-tight">Penuh</span>
                        </div>

                    @elseif($isSkipped || !$schedule)
                        @if($isPast)
                        <div class="bg-surface border border-surface-border px-xs py-xs rounded-lg flex items-center justify-center opacity-40">
                            <span class="material-symbols-outlined text-on-surface-variant text-sm">remove</span>
                        </div>
                        @else
                        <button type="button"
                            @click="guardSlot(@json($date), @json($block), () => { modal = true; modalType = 'book'; selectedRoom = @json($classroom->name); selectedBlock = @json($block); selectedDate = @json($date); classroomId = @json($classroom->id); })"
                            class="{{ $isSkipped ?'bg-warning/10 border-warning/30 hover:bg-warning/20' : 'bg-success/10 border-success/30 hover:bg-success/20' }} border px-xs py-xs rounded-lg flex flex-col items-center justify-center gap-xs transition-colors">
                            <span class="material-symbols-outlined {{ $isSkipped ?'text-warning' : 'text-success' }} text-sm">add_circle</span>
                            <span class="text-[9px] font-bold {{ $isSkipped ?'text-warning' : 'text-success' }} text-center">
                                {{ $isSkipped ? 'Skip' : 'Kosong' }}
                            </span>
                        </button>
                        @endif
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

        <div class="flex items-center gap-md flex-wrap mt-sm">
            <span class="flex items-center gap-xs text-[11px] text-on-surface-variant">
                <span class="w-3 h-3 rounded-sm inline-block bg-primary-container/20 border border-primary-container"></span> Jadwal saya
            </span>
            <span class="flex items-center gap-xs text-[11px] text-on-surface-variant">
                <span class="w-3 h-3 rounded-sm inline-block" style="background:#fecaca"></span> Reguler
            </span>
            <span class="flex items-center gap-xs text-[11px] text-on-surface-variant">
                <span class="w-3 h-3 rounded-sm inline-block" style="background:#fef08a"></span> Skip (bisa booking)
            </span>
            <span class="flex items-center gap-xs text-[11px] text-on-surface-variant">
                <span class="w-3 h-3 rounded-sm inline-block" style="background:#bbf7d0"></span> Kosong (bisa booking)
            </span>
            <span class="flex items-center gap-xs text-[11px] text-on-surface-variant">
                <span class="w-3 h-3 rounded-sm inline-block" style="background:#fde68a; border:1px solid #fbbf24"></span> Booking saya
            </span>
            <span class="flex items-center gap-xs text-[11px] text-on-surface-variant">
                <span class="w-3 h-3 rounded-sm inline-block opacity-60" style="background:#fde68a"></span> Penuh
            </span>
        </div>

    </div>

    {{-- Modal --}}
    <div x-show="modal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @keydown.escape.window="modal = false">
        <div class="bg-surface-container-lowest rounded-lg border border-surface-border w-full max-w-sm p-lg space-y-md shadow-xl"
            @click.outside="modal = false">

            <div class="flex items-center justify-between">
                <h4 class="text-base font-semibold text-on-surface"
                    x-text="modalType === 'book' ? 'Book Slot' : modalType === 'skip' ? 'Skip Sesi' : 'Cancel Booking'">
                </h4>
                <button aria-label="Tutup" @click="modal = false" class="btn btn-ghost btn-sm btn-circle">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-sm">
                <div class="bg-surface-container-low rounded-lg px-md py-sm">
                    <p class="text-[10px] font-bold uppercase text-on-surface-variant">Ruangan</p>
                    <p class="text-sm font-semibold text-on-surface" x-text="selectedRoom || classroomId"></p>
                </div>
                <div class="bg-surface-container-low rounded-lg px-md py-sm">
                    <p class="text-[10px] font-bold uppercase text-on-surface-variant">Tanggal</p>
                    <p class="text-sm font-semibold text-on-surface" x-text="selectedDate"></p>
                </div>
                <div class="bg-surface-container-low rounded-lg px-md py-sm col-span-2">
                    <p class="text-[10px] font-bold uppercase text-on-surface-variant">Sesi</p>
                    <p class="text-sm font-semibold text-on-surface" x-text="selectedBlock"></p>
                </div>
            </div>

            {{-- Book form --}}
            <div x-show="modalType === 'book'">
                <form method="POST" action="{{ route('tutor.room-bookings.store') }}">
                    @csrf
                    <input type="hidden" name="classroom_id" x-bind:value="classroomId">
                    <input type="hidden" name="date" x-bind:value="selectedDate">
                    <input type="hidden" name="time_block" x-bind:value="selectedBlock">
                    <div class="fieldset mb-md">
                        <label class="fieldset-legend">Catatan (opsional)</label>
                        <input type="text" name="notes" class="input w-full input-sm"
                            placeholder="Misal: makeup session, trial...">
                    </div>
                    <div class="flex justify-end gap-sm">
                        <button type="button" @click="modal = false" class="btn btn-ghost btn-sm">Batal</button>
                        <button type="submit"
                            class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90">
                            <span class="material-symbols-outlined text-base">save</span>
                            Book Slot
                        </button>
                    </div>
                </form>
            </div>

            {{-- Skip form --}}
            <div x-show="modalType === 'skip'">
                <p class="text-sm text-on-surface-variant mb-md">Tandai sesi ini sebagai skip. Admin akan diberitahu.</p>
                <form method="POST" action="{{ route('tutor.room-bookings.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="regular_skip">
                    <input type="hidden" name="classroom_id" x-bind:value="classroomId">
                    <input type="hidden" name="date" x-bind:value="selectedDate">
                    <input type="hidden" name="time_block" x-bind:value="selectedBlock">
                    <input type="hidden" name="schedule_id" x-bind:value="selectedScheduleId">
                    <div class="fieldset mb-md">
                        <label class="fieldset-legend">Alasan (opsional)</label>
                        <input type="text" name="notes" class="input w-full input-sm"
                            placeholder="Misal: sakit, izin...">
                    </div>
                    <div class="flex justify-end gap-sm">
                        <button type="button" @click="modal = false" class="btn btn-ghost btn-sm">Batal</button>
                        <button type="submit" class="btn btn-sm border-none"
                            style="--btn-bg: oklch(79.5% .184 86.047); --btn-fg: oklch(42.1% .095 57.708);">
                            <span class="material-symbols-outlined text-base">event_busy</span>
                            Skip Sesi
                        </button>
                    </div>
                </form>
            </div>

            {{-- Cancel form --}}
            <div x-show="modalType === 'cancel'">
                <p class="text-sm text-on-surface-variant mb-md">Batalkan booking slot ini?</p>
                <div x-show="bookingNotes" class="p-sm bg-surface-container-low border border-surface-border rounded-lg mb-md">
                    <p class="text-[10px] font-bold uppercase text-on-surface-variant mb-xs">Catatan</p>
                    <p class="text-body-sm text-on-surface" x-text="bookingNotes"></p>
                </div>
                <form method="POST" :action="`{{ url('tutor/room-bookings') }}/${bookingId}`">
                    @csrf
                    @method('DELETE')
                    <div class="flex justify-end gap-sm">
                        <button type="button" @click="modal = false" class="btn btn-ghost btn-sm">Tidak</button>
                        <button type="submit" class="btn btn-sm border-none"
                            style="--btn-bg: oklch(63.7% .237 25.331); --btn-fg: #fff;">
                            <span class="material-symbols-outlined text-base">cancel</span>
                            Cancel Booking
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>
</x-app-layout>
