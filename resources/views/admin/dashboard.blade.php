<x-app-layout>
<x-slot name="title">Dashboard</x-slot>

@php
    $totalAlerts     = $stats['pending_tutors_enrollments']
        + $unpaidInstallments->filter(fn($i) => \Carbon\Carbon::today()->isAfter($i->due_date))->count()
        + $expiringEnrollments->count();
    $overdueCount    = $unpaidInstallments->filter(fn($i) => \Carbon\Carbon::today()->isAfter($i->due_date))->count();
    $eduLabels            = $educationStats->pluck('education_level')->values()->toArray();
    $eduTotals            = $educationStats->pluck('total')->values()->toArray();
    $distLabels           = $enrollmentDistribution->keys()->values()->toArray();
    $distTotals           = $enrollmentDistribution->values()->toArray();
@endphp

<div class="p-lg space-y-lg" x-data="{
    waitingTab: 'reguler',
    alertOpen: false,
    waitSearch: '',
    expiringSearch: '',
    installSearch: '',
    newStudentSearch: '',
}">

    {{-- ═══════════════════════════════════════════
         1. HEADER + ALERT BANNER (sampingan)
    ════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-md">

        {{-- Header kiri --}}
        <div>
            <p class="text-label-lg text-on-surface-variant uppercase tracking-widest mb-xs">
                {{ now()->translatedFormat('l, d F Y') }}
            </p>
            <h1 class="text-headline-lg font-bold text-on-surface">Dashboard Utama</h1>
            <p class="text-body-md text-on-surface-variant mt-xs">Ringkasan operasional Just Speak hari ini.</p>
        </div>

        {{-- Alert banner kanan --}}
        @if($totalAlerts > 0)
        <div class="relative" style="min-width:20rem" x-data="{ open: false }" @click.outside="open = false">

            <button @click="open = !open"
                class="w-full flex items-center justify-between gap-sm px-md py-sm app-card hover:bg-surface-container-low transition-colors">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-error text-sm">notifications_active</span>
                    <span class="text-label-lg font-bold text-error">{{ $totalAlerts }} peringatan aktif</span>
                </div>
                <div class="flex items-center gap-xs">
                    @if($stats['pending_tutors_enrollments'] > 0)
                        <span class="badge badge-error badge-soft text-xs">Tutor {{ $stats['pending_tutors_enrollments'] }}</span>
                    @endif
                    @if($overdueCount > 0)
                        <span class="badge badge-error badge-soft text-xs">Overdue {{ $overdueCount }}</span>
                    @endif
                    @if($expiringEnrollments->count() > 0)
                        <span class="badge badge-warning badge-soft text-xs">Expiring {{ $expiringEnrollments->count() }}</span>
                    @endif
                    <span class="material-symbols-outlined text-error text-sm" x-text="open ? 'expand_less' : 'expand_more'"></span>
                </div>
            </button>

            <div x-show="open" x-transition
                class="absolute right-0 mt-xs w-full min-w-80 bg-surface-container-lowest border border-surface-border rounded-lg shadow-lg z-50 overflow-hidden">

                <div class="px-md py-sm border-b border-surface-border">
                    <p class="text-label-lg font-bold text-on-surface uppercase tracking-widest">Peringatan Aktif</p>
                </div>

                <div class="divide-y divide-surface-border max-h-96 overflow-y-auto">

                    @if($stats['pending_tutors_enrollments'] > 0)
                    <a href="{{ route('admin.enrollments.index') }}?status=waitlist"
                        class="flex items-start gap-sm px-md py-sm hover:bg-surface-container-low transition-colors block">
                        <span class="material-symbols-outlined text-error shrink-0 text-sm mt-xs">warning</span>
                        <div>
                            <p class="text-body-md font-bold text-on-surface">{{ $stats['pending_tutors_enrollments'] }} Enrollment Tanpa Tutor</p>
                            <p class="text-label-lg text-on-surface-variant">Menunggu tutor ditetapkan</p>
                        </div>
                        <span class="material-symbols-outlined text-on-surface-variant text-sm ml-auto shrink-0">chevron_right</span>
                    </a>
                    @endif

                    @foreach($unpaidInstallments->filter(fn($i) => \Carbon\Carbon::today()->isAfter($i->due_date)) as $inst)
                    <a href="{{ route('admin.enrollments.show', $inst->enrollment_id) }}"
                        class="flex items-start gap-sm px-md py-sm hover:bg-surface-container-low transition-colors block">
                        <span class="material-symbols-outlined text-error shrink-0 text-sm mt-xs">credit_card_off</span>
                        <div class="min-w-0">
                            <p class="text-body-md font-bold text-on-surface truncate">{{ $inst->enrollment->student->user->name }}</p>
                            <p class="text-label-lg text-on-surface-variant whitespace-nowrap">Cicilan overdue · IDR {{ number_format($inst->amount) }}</p>
                        </div>
                        <span class="material-symbols-outlined text-on-surface-variant text-sm ml-auto shrink-0">chevron_right</span>
                    </a>
                    @endforeach

                    @foreach($expiringEnrollments as $enrollment)
                    @php $daysLeft = (int) \Carbon\Carbon::today()->diffInDays($enrollment->expiry_date, false); @endphp
                    <a href="{{ route('admin.enrollments.show', $enrollment->id) }}"
                        class="flex items-start gap-sm px-md py-sm hover:bg-surface-container-low transition-colors block">
                        <span class="material-symbols-outlined {{ $daysLeft <= 3 ?'text-error' : 'text-warning' }} shrink-0 text-sm mt-xs">schedule</span>
                        <div class="min-w-0">
                            <p class="text-body-md font-bold text-on-surface truncate">{{ $enrollment->student->user->name }}</p>
                            <p class="text-label-lg text-on-surface-variant whitespace-nowrap">Berakhir {{ $daysLeft }} hari lagi · {{ $enrollment->program->name }}</p>
                        </div>
                        <span class="material-symbols-outlined text-on-surface-variant text-sm ml-auto shrink-0">chevron_right</span>
                    </a>
                    @endforeach

                </div>

                <div class="px-md py-sm border-t border-surface-border">
                    <a href="{{ route('admin.enrollments.index') }}" class="text-label-lg font-bold text-secondary hover:underline">
                        Lihat semua enrollment →
                    </a>
                </div>

            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════
         2. KPI STRIP
    ════════════════════════════════════════════ --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:var(--spacing-md)">

        {{-- Siswa Aktif --}}
        <a href="{{ route('admin.students.index') }}"
            class="app-card flex flex-col gap-md hover:bg-surface-container-low transition-colors">
            <div class="app-icon-badge">
                <span class="material-symbols-outlined text-secondary">school</span>
            </div>
            <div>
                <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Siswa Aktif</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $activeStudents }}</p>
            </div>
        </a>

        {{-- Room Occupancy --}}
        <a href="{{ route('admin.schedule.index') }}"
            class="app-card flex flex-col gap-md hover:bg-surface-container-low transition-colors">
            <div class="app-icon-badge">
                <span class="material-symbols-outlined text-secondary">meeting_room</span>
            </div>
            <div>
                <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Room Occupancy</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $occupancyRate }}%</p>
                {{-- Progress bar --}}
                <div class="w-full bg-surface-container-high rounded-full h-1.5 mt-sm">
                    <div class="bg-secondary h-1.5 rounded-full transition-all"
                        style="width: {{ $occupancyRate }}%"></div>
                </div>
                <p class="text-label-lg text-on-surface-variant mt-xs">{{ $occupiedCount }} / {{ $totalSlots }} slot</p>
            </div>
        </a>

        {{-- Waiting Reguler --}}
        <button @click="waitingTab = 'reguler'"
            :class="waitingTab ==='reguler' ? 'ring-2 ring-secondary' : ''"
            class="app-card flex flex-col gap-md text-left hover:bg-surface-container-low transition-all">
            <div class="flex items-center justify-between w-full">
                <div class="w-10 h-10 rounded-lg bg-warning/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-warning">groups</span>
                </div>
                <span class="badge badge-soft text-xs">Reguler</span>
            </div>
            <div>
                <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Waiting List Group</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $waitingReguler->count() }}</p>
            </div>
        </button>

        {{-- Waiting Private + Semi --}}
        <button @click="waitingTab = 'private'"
            :class="waitingTab ==='private' || waitingTab === 'semi' ? 'ring-2 ring-error' : ''"
            class="app-card flex flex-col gap-md text-left hover:bg-surface-container-low transition-all">
            <div class="flex items-center justify-between w-full">
                <div class="w-10 h-10 rounded-lg bg-error/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-error">person_search</span>
                </div>
                <span class="badge badge-error badge-soft text-xs">Priority</span>
            </div>
            <div>
                <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Waiting Private & Semi</p>
                <p class="text-headline-lg font-bold text-error mt-xs">{{ $waitingPrivate->count() + $waitingSemi->count() }}</p>
            </div>
        </button>

    </div>

    {{-- ═══════════════════════════════════════════
         3. WAITING LIST TABLE
    ════════════════════════════════════════════ --}}
    <div class="app-card app-card--flush">

        {{-- Header + Tab sejajar --}}
        <div class="px-lg py-md border-b border-surface-border flex items-center justify-between">
            <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-on-surface-variant">format_list_bulleted</span>
                <h2 class="text-headline-md font-semibold text-on-surface"
                    x-text="waitingTab === 'reguler' ? 'Daftar Tunggu Reguler' : waitingTab === 'private' ? 'Daftar Tunggu Private' : 'Daftar Tunggu Semi-Private'">
                </h2>
            </div>
            <div class="flex items-center gap-xs">
                <button @click="waitingTab = 'reguler'"
                    :class="waitingTab ==='reguler' ? 'btn-primary' : 'btn-ghost'"
                    class="btn btn-xs">Reguler</button>
                <button @click="waitingTab = 'private'"
                    :class="waitingTab ==='private' ? 'btn-primary' : 'btn-ghost'"
                    class="btn btn-xs">Private</button>
                <button @click="waitingTab = 'semi'"
                    :class="waitingTab ==='semi' ? 'btn-primary' : 'btn-ghost'"
                    class="btn btn-xs">Semi</button>
                <div class="relative ml-xs">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">search</span>
                    <input type="text" x-model="waitSearch" placeholder="Cari siswa..."
                        class="input input-sm pl-8 w-40 bg-surface-container-low border-surface-border rounded-lg text-body-md">
                </div>
            </div>
        </div>

        {{-- Tab: Reguler --}}
        <div x-show="waitingTab === 'reguler'">
            @if($waitingReguler->isEmpty())
                <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">inbox</span>
                    <p class="text-body-md">Tidak ada siswa di waiting list reguler.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <div style="max-height:20rem;overflow-y:auto">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead class="sticky top-0 bg-surface-container-lowest z-10">
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Nama Siswa</th>
                                <th>Program</th>
                                <th>Tanggal Daftar</th>
                                <th>Lama Tunggu</th>
                                <th>Kelas</th>
                                <th>Kendala</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitingReguler as $e)
                            @php
                                $waitDays        = (int) \Carbon\Carbon::parse($e->created_at)->diffInDays(now());
                                $confirmedTutor  = $e->tutors->firstWhere('pivot.status', 'confirmed');
                                $pendingTutor    = $e->tutors->firstWhere('pivot.status', 'pending');
                                $enrolledCount   = $e->classSession ? $e->classSession->enrollments()->whereIn('status', ['active','waitlist'])->count() : 0;
                                $minQuota        = $e->program->min_quota ?? 0;
                                $studentName     = $e->student->user->name;
                            @endphp
                            <tr class="hover:bg-surface-container-low transition-colors"
                                data-name="{{ strtolower($studentName) }}"
                                x-show="waitSearch === '' || $el.dataset.name.includes(waitSearch.toLowerCase())">
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="app-avatar app-avatar--sm">
                                            {{ strtoupper(substr($studentName, 0, 2)) }}
                                        </div>
                                        <a href="{{ route('admin.enrollments.show', $e->id) }}"
                                            class="font-semibold text-body-md hover:text-primary-container">{{ $studentName }}</a>
                                    </div>
                                </td>
                                <td class="text-body-md">{{ $e->program->name }}</td>
                                <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge badge-soft {{ $waitDays > 7 ?'badge-error' : 'badge-warning' }} whitespace-nowrap">
                                        {{ $waitDays }} hari
                                    </span>
                                </td>
                                <td>
                                    @if($e->classSession)
                                        <span class="text-body-sm text-on-surface">{{ $e->classSession->name }}</span>
                                        <span class="text-[11px] text-on-surface-variant block">{{ $enrolledCount }}/{{ $minQuota }} quota</span>
                                    @else
                                        <span class="text-body-sm text-on-surface-variant italic">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$e->class_session_id)
                                        <span class="text-body-sm text-error">Belum ada kelas</span>
                                    @elseif($e->tutors->isEmpty())
                                        <span class="text-body-sm text-error">Belum ada tutor</span>
                                    @elseif(!$confirmedTutor && $pendingTutor)
                                        <span class="text-body-sm text-warning">Tutor belum confirm</span>
                                    @elseif($enrolledCount < $minQuota)
                                        <span class="text-body-sm text-warning">Quota belum terpenuhi ({{ $enrolledCount }}/{{ $minQuota }})</span>
                                    @else
                                        <span class="text-body-sm text-warning">Menunggu konfirmasi admin</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            </div>
            @endif
        </div>

        {{-- Tab: Private --}}
        <div x-show="waitingTab === 'private'">
            @if($waitingPrivate->isEmpty())
                <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">inbox</span>
                    <p class="text-body-md">Tidak ada siswa di waiting list private.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <div style="max-height:20rem;overflow-y:auto">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead class="sticky top-0 bg-surface-container-lowest z-10">
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Nama Siswa</th>
                                <th>Program</th>
                                <th>Tanggal Daftar</th>
                                <th>Lama Tunggu</th>
                                <th>Tutor</th>
                                <th>Kendala</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitingPrivate as $e)
                            @php
                                $waitDays       = (int) \Carbon\Carbon::parse($e->created_at)->diffInDays(now());
                                $confirmedTutor = $e->tutors->firstWhere('pivot.status', 'confirmed');
                                $pendingTutor   = $e->tutors->firstWhere('pivot.status', 'pending');
                                $studentName    = $e->student->user->name;
                            @endphp
                            <tr class="hover:bg-surface-container-low transition-colors"
                                data-name="{{ strtolower($studentName) }}"
                                x-show="waitSearch === '' || $el.dataset.name.includes(waitSearch.toLowerCase())">
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-error/20 flex items-center justify-center font-bold text-error text-xs shrink-0">
                                            {{ strtoupper(substr($studentName, 0, 2)) }}
                                        </div>
                                        <a href="{{ route('admin.enrollments.show', $e->id) }}"
                                            class="font-semibold text-body-md hover:text-primary-container">{{ $studentName }}</a>
                                    </div>
                                </td>
                                <td class="text-body-md">{{ $e->program->name }}</td>
                                <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge badge-soft {{ $waitDays > 7 ?'badge-error' : 'badge-warning' }} whitespace-nowrap">
                                        {{ $waitDays }} hari
                                    </span>
                                </td>
                                <td>
                                    @if($confirmedTutor)
                                        <span class="badge badge-success badge-soft whitespace-nowrap">{{ $confirmedTutor->user->name }}</span>
                                    @elseif($pendingTutor)
                                        <span class="badge badge-warning badge-soft whitespace-nowrap">{{ $pendingTutor->user->name }}</span>
                                    @else
                                        <span class="text-body-sm text-on-surface-variant italic">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($e->tutors->isEmpty())
                                        <span class="text-body-sm text-error">Belum ada tutor</span>
                                    @elseif(!$confirmedTutor && $pendingTutor)
                                        <span class="text-body-sm text-warning">Tutor belum confirm</span>
                                    @elseif($confirmedTutor && !$e->class_session_id)
                                        <span class="text-body-sm text-error">Belum ada jadwal</span>
                                    @else
                                        <span class="text-body-sm text-warning">Menunggu konfirmasi admin</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            </div>
            @endif
        </div>

        {{-- Tab: Semi-Private --}}
        <div x-show="waitingTab === 'semi'">
            @if($waitingSemi->isEmpty())
                <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">inbox</span>
                    <p class="text-body-md">Tidak ada siswa di waiting list semi-private.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <div style="max-height:20rem;overflow-y:auto">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead class="sticky top-0 bg-surface-container-lowest z-10">
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Nama Siswa</th>
                                <th>Program</th>
                                <th>Tanggal Daftar</th>
                                <th>Lama Tunggu</th>
                                <th>Kendala</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitingSemi as $e)
                            @php
                                $waitDays    = (int) \Carbon\Carbon::parse($e->created_at)->diffInDays(now());
                                $studentName = $e->student->user->name;
                            @endphp
                            <tr class="hover:bg-surface-container-low transition-colors"
                                data-name="{{ strtolower($studentName) }}"
                                x-show="waitSearch === '' || $el.dataset.name.includes(waitSearch.toLowerCase())">
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-warning/20 flex items-center justify-center font-bold text-warning text-xs shrink-0">
                                            {{ strtoupper(substr($studentName, 0, 2)) }}
                                        </div>
                                        <a href="{{ route('admin.enrollments.show', $e->id) }}"
                                            class="font-semibold text-body-md hover:text-primary-container">{{ $studentName }}</a>
                                    </div>
                                </td>
                                <td class="text-body-md">{{ $e->program->name }}</td>
                                <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge badge-soft {{ $waitDays > 7 ?'badge-error' : 'badge-warning' }} whitespace-nowrap">
                                        {{ $waitDays }} hari
                                    </span>
                                </td>
                                <td>
                                    @if(!$e->class_session_id)
                                        <span class="text-body-sm text-error">Belum ada kelas</span>
                                    @elseif($e->tutors->isEmpty())
                                        <span class="text-body-sm text-error">Belum ada tutor</span>
                                    @else
                                        <span class="text-body-sm text-warning">Menunggu konfirmasi tutor</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            </div>
            @endif
        </div>

    </div>

    {{-- ═══════════════════════════════════════════
         4. ACTION TABLES — side by side
    ════════════════════════════════════════════ --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--spacing-lg)">

        {{-- Expiring Enrollments --}}
        <div class="app-card app-card--flush" style="display:flex;flex-direction:column">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between gap-sm">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-warning">schedule</span>
                    <h3 class="text-headline-md font-semibold text-on-surface">Expiring dalam 7 Hari</h3>
                    <span class="badge badge-soft">{{ $expiringEnrollments->count() }}</span>
                </div>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">search</span>
                    <input type="text" x-model="expiringSearch" placeholder="Cari siswa..."
                        class="input input-sm pl-8 w-40 bg-surface-container-low border-surface-border rounded-lg text-body-md">
                </div>
            </div>
            @if($expiringEnrollments->isEmpty())
                <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <p class="text-body-md">Tidak ada enrollment yang akan segera berakhir.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <div style="max-height:18rem;overflow-y:auto">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead class="sticky top-0 bg-surface-container-lowest z-10">
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Student</th>
                                <th>Program</th>
                                <th>Expiry</th>
                                <th>Sisa</th>
                                <th>Pertemuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expiringEnrollments as $enrollment)
                            @php
                                $daysLeft    = (int) \Carbon\Carbon::today()->diffInDays($enrollment->expiry_date, false);
                                $studentName = $enrollment->student->user->name;
                            @endphp
                            <tr class="hover:bg-surface-container-low transition-colors"
                                data-name="{{ strtolower($studentName) }}"
                                x-show="expiringSearch === '' || $el.dataset.name.includes(expiringSearch.toLowerCase())">
                                <td>
                                    <a href="{{ route('admin.enrollments.show', $enrollment->id) }}"
                                        class="font-semibold text-body-md hover:text-primary-container">{{ $studentName }}</a>
                                </td>
                                <td class="text-body-md">{{ $enrollment->program->name }}</td>
                                <td class="text-body-md whitespace-nowrap">{{ \Carbon\Carbon::parse($enrollment->expiry_date)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge {{ $daysLeft <= 3 ?'badge-error' : 'badge-warning' }} badge-soft whitespace-nowrap">
                                        {{ $daysLeft }} hari
                                    </span>
                                </td>
                                <td class="text-body-md">{{ $enrollment->remaining_meetings }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            </div>
            @endif
        </div>

        {{-- Installments Belum Lunas --}}
        <div class="app-card app-card--flush" style="display:flex;flex-direction:column">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between gap-sm">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-error">credit_card_off</span>
                    <h3 class="text-headline-md font-semibold text-on-surface">Installments Belum Lunas</h3>
                    <span class="badge badge-soft">{{ $unpaidInstallments->count() }}</span>
                </div>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">search</span>
                    <input type="text" x-model="installSearch" placeholder="Cari siswa..."
                        class="input input-sm pl-8 w-40 bg-surface-container-low border-surface-border rounded-lg text-body-md">
                </div>
            </div>
            @if($unpaidInstallments->isEmpty())
                <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <p class="text-body-md">Semua cicilan sudah lunas.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <div style="max-height:18rem;overflow-y:auto">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead class="sticky top-0 bg-surface-container-lowest z-10">
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Student</th>
                                <th>Program</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unpaidInstallments as $inst)
                            @php
                                $isOverdue   = \Carbon\Carbon::today()->isAfter($inst->due_date);
                                $studentName = $inst->enrollment->student->user->name;
                            @endphp
                            <tr class="hover:bg-surface-container-low transition-colors"
                                data-name="{{ strtolower($studentName) }}"
                                x-show="installSearch === '' || $el.dataset.name.includes(installSearch.toLowerCase())">
                                <td>
                                    <a href="{{ route('admin.enrollments.show', $inst->enrollment_id) }}"
                                        class="font-semibold text-body-md hover:text-primary-container">{{ $studentName }}</a>
                                </td>
                                <td class="text-body-md">{{ $inst->enrollment->program->name }}</td>
                                <td class="text-body-md whitespace-nowrap">IDR {{ number_format($inst->amount) }}</td>
                                <td class="text-body-md whitespace-nowrap">{{ \Carbon\Carbon::parse($inst->due_date)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge {{ $isOverdue ?'badge-error' : 'badge-warning' }} badge-soft whitespace-nowrap">
                                        {{ $isOverdue ? 'Overdue' : 'Upcoming' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            </div>
            @endif
        </div>

    </div>

    {{-- ═══════════════════════════════════════════
         5. INSIGHT ROW — Donut + Bar Chart
    ════════════════════════════════════════════ --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--spacing-lg)">

        {{-- Donut: Distribusi Jenjang --}}
        <div class="app-card app-card--flush">
            <div class="px-lg py-md border-b border-surface-border">
                <h3 class="text-headline-md font-semibold text-on-surface">Distribusi Jenjang</h3>
                <p class="text-label-lg text-on-surface-variant mt-xs">Keseluruhan siswa terdaftar</p>
            </div>
            <div class="p-lg flex flex-col gap-md">
                <div style="position:relative;height:220px;width:100%">
                    <canvas id="educationDonutChart"></canvas>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px" class="text-sm text-on-surface">
                    @php
                        // Brand-aligned palette per BRAND_PERSONALITY_GUIDE.md Section 3:
                        // - Primary dark green #065f46 = rgb(6, 95, 70)
                        // - Secondary bright green #059669 = rgb(5, 150, 105) — brand identity
                        // - Tertiary amber #b45309 = rgb(180, 83, 9) — used sparingly
                        // - Neutral slate #6b7280 = rgb(107, 114, 128) — for "no data"
                        // Brand rule: "Never replace green with blue or purple."
                        // Previously this map used Tailwind sky (#0ea5e9) and indigo
                        // (#6366f1) — both violate the brand guide. Replaced with
                        // brand-aligned variants.
                        $eduColorMap = [
                            'SD'          => 'rgba(5,150,105,0.85)',   // secondary green (brand)
                            'SMP'         => 'rgba(4,120,87,0.75)',    // success green (darker)
                            'SMA'         => 'rgba(6,95,70,0.7)',      // primary dark green
                            'Kuliah'      => 'rgba(180,83,9,0.7)',     // tertiary amber
                            'Umum'        => 'rgba(217,119,6,0.65)',   // warning amber (lighter)
                            'Tidak diisi' => 'rgba(107,114,128,0.5)', // neutral outline
                        ];
                    @endphp
                    @foreach($educationStats as $stat)
                    @php $color = $eduColorMap[$stat->education_level] ?? 'rgba(148,163,184,0.8)'; @endphp
                    <div class="flex items-center gap-xs">
                        <span style="width:10px;height:10px;border-radius:2px;background:{{ $color }};flex-shrink:0;display:inline-block"></span>
                        <span class="text-on-surface-variant">{{ $stat->education_level }}</span>
                        <span class="font-medium ml-auto pl-sm">{{ $stat->total }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Bar: Distribusi Waiting List --}}
        <div class="app-card app-card--flush" style="display:flex;flex-direction:column">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between">
                <div>
                    <h3 class="text-headline-md font-semibold text-on-surface">Distribusi Siswa Aktif</h3>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Group · Private · Semi-Private</p>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant">bar_chart</span>
            </div>
            <div class="p-lg" style="flex:1;display:flex;flex-direction:column">
                <div style="position:relative;height:220px;width:100%">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════
         6. NEW STUDENTS — full width
    ════════════════════════════════════════════ --}}
    <div class="app-card app-card--flush">
        <div class="px-lg py-md border-b border-surface-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-sm">
            <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-on-surface-variant">person_add</span>
                <h3 class="text-headline-md font-semibold text-on-surface">Murid Baru 30 Hari Terakhir</h3>
                <span class="badge badge-soft">{{ $newStudents->count() }}</span>
            </div>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">search</span>
                <input type="text" x-model="newStudentSearch" placeholder="Cari siswa..."
                    class="input input-sm pl-8 w-48 bg-surface-container-low border-surface-border rounded-lg text-body-md">
            </div>
        </div>
        @if($newStudents->isEmpty())
            <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-sm">inbox</span>
                <p class="text-body-md">Belum ada murid baru bulan ini.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <div style="max-height:18rem;overflow-y:auto">
                <div class="app-table-wrapper">
<table class="table table-sm">
                    <thead class="sticky top-0 bg-surface-container-lowest z-10">
                        <tr class="text-label-lg text-on-surface-variant">
                            <th>Nama</th>
                            <th>Jenjang</th>
                            <th>Bergabung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($newStudents as $student)
                        @php $studentName = $student->user->name ?? '-'; @endphp
                        <tr class="hover:bg-surface-container-low transition-colors"
                            data-name="{{ strtolower($studentName) }}"
                            x-show="newStudentSearch === '' || $el.dataset.name.includes(newStudentSearch.toLowerCase())">
                            <td>
                                <div class="flex items-center gap-sm">
                                    <div class="app-avatar app-avatar--sm">
                                        {{ strtoupper(substr($studentName, 0, 2)) }}
                                    </div>
                                    <span class="font-semibold text-body-md text-on-surface">{{ $studentName }}</span>
                                </div>
                            </td>
                            <td>
                                @if($student->education_level)
                                    <span class="badge badge-soft whitespace-nowrap">{{ $student->education_level }}</span>
                                @else
                                    <span class="text-on-surface-variant">—</span>
                                @endif
                            </td>
                            <td class="text-label-lg text-on-surface-variant whitespace-nowrap">
                                {{ $student->created_at->diffForHumans() }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
</div>
            </div>
        </div>
        @endif
    </div>

</div>

{{-- ═══════════════════════════════════════════
     CHART.JS
════════════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function() {

    // Bar Chart — Distribusi Siswa Aktif
    const distCanvas = document.getElementById('distributionChart');
    if (distCanvas && typeof Chart !== 'undefined') {
        new Chart(distCanvas, {
            type: 'bar',
            data: {
                labels: @json($distLabels),
                datasets: [{
                    label: 'Jumlah Siswa Aktif',
                    data: @json($distTotals),
                    backgroundColor: [
                        'rgba(5,150,105,0.75)',
                        'rgba(5,150,105,0.5)',
                        'rgba(5,150,105,0.25)',
                        'rgba(5,150,105,0.15)',
                    ],
                    borderColor: [
                        'rgba(5,150,105,1)',
                        'rgba(5,150,105,1)',
                        'rgba(5,150,105,1)',
                        'rgba(5,150,105,1)',
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: (ctx) => ` ${ctx.parsed.y} siswa aktif` }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 13 } } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, border: { display: false }, ticks: { stepSize: 1, font: { size: 12 } } }
                }
            }
        });
    }

    // Donut Chart — Education Distribution
    const donutCanvas = document.getElementById('educationDonutChart');
    if (donutCanvas && typeof Chart !== 'undefined') {
        new Chart(donutCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: @json($eduLabels),
                datasets: [{
                    data: @json($eduTotals),
                    backgroundColor: [
                        'rgba(5,150,105,0.85)',   // secondary green
                        'rgba(4,120,87,0.75)',    // success green
                        'rgba(6,95,70,0.7)',      // primary dark green
                        'rgba(180,83,9,0.7)',     // tertiary amber
                        'rgba(217,119,6,0.65)',   // warning amber
                        'rgba(107,114,128,0.5)',  // neutral
                    ],
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: ctx => ctx.label + ': ' + ctx.raw + ' siswa' }
                    }
                }
            }
        });
    }

}, 100); });
</script>

</x-app-layout>
