<x-app-layout>
<x-slot name="title">Dashboard</x-slot>

<div class="p-lg space-y-lg">

    {{-- Greeting --}}
    <div>
        <h3 class="text-headline-lg font-bold text-on-surface">Halo, {{ Auth::user()->name }} 👋</h3>
        <p class="text-sm text-on-surface-variant mt-xs">{{ $today->translatedFormat('l, d F Y') }}</p>
    </div>

    {{-- Enrollment Cards --}}
    @forelse($enrollments as $enrollment)
    @php
        $attended  = $attendanceCounts[$enrollment->id] ?? 0;
        $total     = $enrollment->program->total_meetings ?? 0;
        $progress  = $total > 0 ? round(($attended / $total) * 100) : 0;
        $remaining = $enrollment->remaining_meetings ?? 0;
        $next      = $nextSessions[$enrollment->id] ?? null;

        $statusColor = match($enrollment->status) {
            'active'    => 'badge-success',
            'graduate' => 'badge-neutral',
            'cancelled' => 'badge-error',
            default     => 'badge-neutral',
        };
        $payColor = match($enrollment->payment_status) {
            'full'    => 'badge-success',
            'partial' => 'badge-warning',
            'pending'  => 'badge-error',
            default   => 'badge-neutral',
        };
    @endphp

    <div class="app-card space-y-md">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-md">
            <div>
                <p class="text-base font-semibold text-on-surface">{{ $enrollment->program->name }}</p>
                <p class="text-sm text-on-surface-variant mt-xs">{{ $enrollment->classSession?->name ?? '—' }}</p>
                {{-- Tutor --}}
                @if($enrollment->tutors->isNotEmpty())
                <p class="text-xs text-on-surface-variant mt-xs flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[14px]">person</span>
                    {{ $enrollment->tutors->map(fn($t) => $t->user->name)->join(', ') }}
                </p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-xs">
                <span class="badge badge-soft {{ $statusColor }} capitalize whitespace-nowrap">{{ $enrollment->status }}</span>
                <span class="badge badge-soft {{ $payColor }} capitalize whitespace-nowrap">{{ str_replace('_', ' ', $enrollment->payment_status) }}</span>
            </div>
        </div>

        {{-- Next Session --}}
        @if($next && $enrollment->status === 'active')
        <div class="flex items-center gap-sm px-md py-sm rounded-lg {{ $next['is_today'] ? 'bg-primary-container/20 border border-primary-container/40' : 'bg-surface border border-surface-border' }}">
            <span class="material-symbols-outlined {{ $next['is_today'] ? 'text-primary-container' : 'text-on-surface-variant' }} text-base">
                {{ $next['is_today'] ? 'today' : 'event' }}
            </span>
            <div>
                <p class="text-xs font-semibold {{ $next['is_today'] ? 'text-primary-container' : 'text-on-surface-variant' }} uppercase tracking-wide">
                    {{ $next['is_today'] ? 'Sesi Hari Ini' : 'Sesi Berikutnya' }}
                </p>
                <p class="text-sm text-on-surface">
                    {{ $next['day'] }}, {{ $next['date'] }} · {{ $next['time_block'] }} · {{ $next['classroom'] }}
                </p>
            </div>
        </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-md text-center">
            <div class="bg-surface rounded-lg p-sm">
                <p class="text-headline-lg font-bold text-primary-container">{{ $attended }}</p>
                <p class="text-xs text-on-surface-variant">Hadir</p>
            </div>
            <div class="bg-surface rounded-lg p-sm">
                <p class="text-headline-lg font-bold text-on-surface">{{ $total }}</p>
                <p class="text-xs text-on-surface-variant">Total Sesi</p>
            </div>
            <div class="bg-surface rounded-lg p-sm">
                <p class="text-headline-lg font-bold text-on-surface">{{ $remaining }}</p>
                <p class="text-xs text-on-surface-variant">Sisa</p>
            </div>
        </div>

        {{-- Progress --}}
        <div>
            <div class="flex justify-between text-xs text-on-surface-variant mb-xs">
                <span>Progress kehadiran</span>
                <span>{{ $progress }}%</span>
            </div>
            <div class="w-full bg-surface rounded-full h-2">
                <div class="bg-primary-container h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
            </div>
        </div>

        {{-- Installments --}}
        @if($enrollment->payment_method === 'installment' && $enrollment->installments->count())
        <div>
            <p class="text-xs font-semibold text-on-surface-variant mb-sm uppercase tracking-wide">Cicilan</p>
            <div class="flex items-center gap-xs flex-wrap">
                @foreach($enrollment->installments as $i => $inst)
                @php
                    $ic = $inst->paid_at
                        ? 'bg-success/20 border-success text-success'
                        : 'bg-surface border-surface-border text-on-surface-variant';
                @endphp
                <div class="flex flex-col items-center border rounded-lg px-md py-sm {{ $ic }} text-center min-w-[80px]">
                    <span class="material-symbols-outlined text-base">
                        {{ $inst->paid_at ? 'check_circle' : 'radio_button_unchecked' }}
                    </span>
                    <p class="text-xs font-semibold mt-xs">Cicilan {{ $i + 1 }}</p>
                    <p class="text-[10px]">Rp {{ number_format($inst->amount, 0, ',', '.') }}</p>
                    @if($inst->due_date)
                    <p class="text-[10px] opacity-70">{{ \Carbon\Carbon::parse($inst->due_date)->format('d M') }}</p>
                    @endif
                </div>
                @if(!$loop->last)
                <div class="h-px w-4 bg-surface-border flex-shrink-0"></div>
                @endif
                @endforeach
            </div>
        </div>
        @endif

    </div>
    @empty
    <div class="app-card p-xl text-center">
        <span class="material-symbols-outlined text-[48px] text-on-surface-variant">school</span>
        <p class="text-sm text-on-surface-variant mt-sm">Belum ada enrollment aktif.</p>
    </div>
    @endforelse

    {{-- Riwayat Kehadiran --}}
    @if($attendanceHistory->count())
    <div class="app-card"
        x-data="{ activeEnrollment: {{ $enrollments->first()?->id ?? 'null' }} }">

        <p class="text-sm font-semibold text-on-surface mb-md">Riwayat Kehadiran</p>

        {{-- Tab per enrollment kalau lebih dari 1 --}}
        @if($enrollments->count() > 1)
        <div class="flex gap-xs mb-md flex-wrap">
            @foreach($enrollments as $enrollment)
            <button type="button"
                @click="activeEnrollment = {{ $enrollment->id }}"
                :class="activeEnrollment === {{ $enrollment->id }} ?'bg-primary-container text-on-primary'
                    : 'bg-surface text-on-surface-variant hover:bg-surface-container-low'"
                class="px-md py-xs rounded-lg text-xs font-semibold border border-surface-border transition-colors">
                {{ $enrollment->program->name }}
            </button>
            @endforeach
        </div>
        @endif

        {{-- History per enrollment --}}
        @foreach($enrollments as $enrollment)
        @php
            $history = $attendanceHistory->where('enrollment_id', $enrollment->id)->values();
        @endphp
        <div x-show="activeEnrollment === {{ $enrollment->id }}" x-cloak>
            @if($history->isEmpty())
                <p class="text-sm text-on-surface-variant text-center py-md">Belum ada riwayat kehadiran.</p>
            @else
            <div class="space-y-sm">
                @foreach($history as $rec)
                <div class="border border-surface-border rounded-lg p-md {{ $rec->is_present ?'border-l-4 border-l-success' : 'border-l-4 border-l-error' }}">
                    <div class="flex items-start justify-between gap-md">
                        <div>
                            <p class="text-sm font-semibold text-on-surface">
                                {{ \Carbon\Carbon::parse($rec->date)->isoFormat('D MMM YYYY') }}
                                <span class="font-normal text-on-surface-variant">· {{ $rec->time_block }}</span>
                            </p>
                            {{-- Catatan kelas --}}
                            @if($rec->class_notes)
                            <p class="text-xs text-on-surface-variant mt-xs flex items-start gap-xs">
                                <span class="material-symbols-outlined text-[13px] mt-px">book</span>
                                <span><span class="font-semibold">Catatan kelas:</span> {{ $rec->class_notes }}</span>
                            </p>
                            @endif
                            {{-- Catatan personal --}}
                            @if($rec->personal_notes)
                            <p class="text-xs text-on-surface-variant mt-xs flex items-start gap-xs">
                                <span class="material-symbols-outlined text-[13px] mt-px">comment</span>
                                <span><span class="font-semibold">Catatan tutor:</span> {{ $rec->personal_notes }}</span>
                            </p>
                            @endif
                        </div>
                        <div class="flex-shrink-0">
                            @if($rec->is_present)
                                <span class="badge badge-soft badge-success text-xs">
                                    <span class="material-symbols-outlined text-[13px]">check_circle</span> Hadir
                                </span>
                            @else
                                <span class="badge badge-soft badge-error text-xs">
                                    <span class="material-symbols-outlined text-[13px]">cancel</span> Absen
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach

    </div>
    @endif

</div>
</x-app-layout>
