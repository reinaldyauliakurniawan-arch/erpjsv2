<x-app-layout>
<x-slot name="title">Dashboard</x-slot>

<div class="p-lg space-y-lg" x-data="{ activeWaiting: 'reguler', privateTab: 'private' }">

    {{-- ═══════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between">
        <div>
            <p class="text-label-lg text-on-surface-variant uppercase tracking-widest mb-xs">
                {{ now()->translatedFormat('l, d F Y') }}
            </p>
            <h1 class="text-headline-lg font-bold text-on-surface">Dashboard Utama</h1>
            <p class="text-body-md text-on-surface-variant mt-xs">Ringkasan operasional Just Speak hari ini.</p>
        </div>
        {{-- Alert summary pill di header --}}
        @php
            $totalAlerts = $stats['pending_tutors_enrollments']
                + $unpaidInstallments->filter(fn($i) => \Carbon\Carbon::today()->isAfter($i->due_date))->count()
                + $expiringEnrollments->count();
        @endphp
        @if($totalAlerts > 0)
        <div class="flex items-center gap-sm px-md py-sm bg-error-container rounded-full border border-error/20">
            <span class="material-symbols-outlined text-error text-sm">notifications_active</span>
            <span class="text-label-lg font-bold text-error">{{ $totalAlerts }} peringatan aktif</span>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════
         KPI STRIP — 4 kartu sejajar
    ════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-md">

        {{-- Siswa --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md">
            <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-secondary">school</span>
            </div>
            <div>
                <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Jumlah Siswa</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $stats['students_count'] }}</p>
            </div>
        </div>

        {{-- Tutors --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md">
            <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-secondary">person_search</span>
            </div>
            <div>
                <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Tutors</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $stats['tutors_count'] }}</p>
            </div>
        </div>

        {{-- Waiting Reguler --}}
        <button @click="activeWaiting = 'reguler'"
            :class="activeWaiting === 'reguler' ? 'ring-2 ring-secondary' : ''"
            class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md text-left transition-all hover:bg-surface-container-low">
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

        {{-- Waiting Private --}}
        <button @click="activeWaiting = 'private'"
            :class="activeWaiting === 'private' ? 'ring-2 ring-error' : ''"
            class="bg-error-container border border-surface-border rounded-lg p-lg flex flex-col gap-md text-left transition-all hover:opacity-90">
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
         WAITING LIST TABLE
    ════════════════════════════════════════════ --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
        <div class="px-lg py-md border-b border-surface-border flex items-center justify-between">
            <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-on-surface-variant">format_list_bulleted</span>
                <h2 class="text-headline-md font-semibold text-on-surface"
                    x-text="activeWaiting === 'reguler' ? 'Daftar Tunggu Reguler' : 'Daftar Tunggu Private / Semi-Private'">
                </h2>
            </div>
            <div x-show="activeWaiting === 'private'" class="flex gap-xs">
                <button @click="privateTab = 'private'"
                    :class="privateTab === 'private' ? 'btn-primary' : 'btn-ghost'"
                    class="btn btn-xs">Private</button>
                <button @click="privateTab = 'semi'"
                    :class="privateTab === 'semi' ? 'btn-primary' : 'btn-ghost'"
                    class="btn btn-xs">Semi-Private</button>
            </div>
        </div>

        {{-- Reguler --}}
        <div x-show="activeWaiting === 'reguler'" class="overflow-x-auto">
            @if($waitingReguler->isEmpty())
                <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-sm">inbox</span>
                    <p class="text-body-md">Tidak ada siswa di waiting list reguler.</p>
                </div>
            @else
                <table class="table table-sm">
                    <thead>
                        <tr class="text-label-lg text-on-surface-variant">
                            <th>Nama Siswa</th>
                            <th>Program</th>
                            <th>Tanggal Daftar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($waitingReguler as $e)
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td>
                                <div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-secondary text-xs">
                                        {{ strtoupper(substr($e->student->user->name, 0, 2)) }}
                                    </div>
                                    <span class="font-semibold text-body-md">{{ $e->student->user->name }}</span>
                                </div>
                            </td>
                            <td class="text-body-md">{{ $e->program->name }}</td>
                            <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                            <td>
                                @if($e->class_session_id)
                                    <span class="badge badge-success badge-soft">Ada Kelas</span>
                                @else
                                    <span class="badge badge-warning badge-soft">Belum Ada Kelas</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Private --}}
        <div x-show="activeWaiting === 'private'">
            <div x-show="privateTab === 'private'" class="overflow-x-auto">
                @if($waitingPrivate->isEmpty())
                    <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">inbox</span>
                        <p class="text-body-md">Tidak ada siswa di waiting list private.</p>
                    </div>
                @else
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Nama Siswa</th><th>Program</th><th>Tanggal Daftar</th><th>Status Penempatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitingPrivate as $e)
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-error/20 flex items-center justify-center font-bold text-error text-xs">
                                            {{ strtoupper(substr($e->student->user->name, 0, 2)) }}
                                        </div>
                                        <span class="font-semibold text-body-md">{{ $e->student->user->name }}</span>
                                    </div>
                                </td>
                                <td class="text-body-md">{{ $e->program->name }}</td>
                                <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                <td>
                                    @if($e->tutors->isEmpty())
                                        <span class="text-on-surface-variant text-body-md italic">Belum dapat tutor</span>
                                    @else
                                        <span class="badge badge-success badge-soft">{{ $e->tutors->first()->user->name ?? '-' }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div x-show="privateTab === 'semi'" class="overflow-x-auto">
                @if($waitingSemi->isEmpty())
                    <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">inbox</span>
                        <p class="text-body-md">Tidak ada siswa di waiting list semi-private.</p>
                    </div>
                @else
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Nama Siswa</th><th>Program</th><th>Tanggal Daftar</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitingSemi as $e)
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-warning/20 flex items-center justify-center font-bold text-warning text-xs">
                                            {{ strtoupper(substr($e->student->user->name, 0, 2)) }}
                                        </div>
                                        <span class="font-semibold text-body-md">{{ $e->student->user->name }}</span>
                                    </div>
                                </td>
                                <td class="text-body-md">{{ $e->program->name }}</td>
                                <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                <td>
                                    @if($e->class_session_id)
                                        <span class="badge badge-success badge-soft">Sudah ada kelas</span>
                                    @else
                                        <span class="badge badge-warning badge-soft">Belum ada kelas</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════
         MAGAZINE GRID — Chart + Alerts
         Kolom kiri 8/12 (chart), kanan 4/12 (alerts)
    ════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

        {{-- Chart: Distribusi Waiting List --}}
        <div class="lg:col-span-8 bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between">
                <div>
                    <h3 class="text-headline-md font-semibold text-on-surface">Distribusi Waiting List</h3>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Reguler · Private · Semi-Private</p>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant">bar_chart</span>
            </div>
            <div class="p-lg">
                <canvas id="waitingChart" height="120"></canvas>
            </div>
        </div>

        {{-- Alerts Panel --}}
        <div class="lg:col-span-4 bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <div class="flex items-center gap-sm mb-md">
                <span class="material-symbols-outlined text-on-surface-variant">shield_with_heart</span>
                <h3 class="text-headline-md font-semibold text-on-surface">Peringatan Penting</h3>
            </div>

            @php $overdueCount = $unpaidInstallments->filter(fn($i) => \Carbon\Carbon::today()->isAfter($i->due_date))->count(); @endphp

            @if($stats['pending_tutors_enrollments'] > 0)
            <div class="flex gap-md p-md bg-error-container rounded-lg border-l-4 border-error">
                <span class="material-symbols-outlined text-error shrink-0">warning</span>
                <div>
                    <p class="text-body-md font-bold text-on-surface">{{ $stats['pending_tutors_enrollments'] }} Pending Assignment</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Enrollment menunggu tutor ditetapkan.</p>
                </div>
            </div>
            @endif

            @if($overdueCount > 0)
            <div class="flex gap-md p-md bg-error-container rounded-lg border-l-4 border-error">
                <span class="material-symbols-outlined text-error shrink-0">credit_card_off</span>
                <div>
                    <p class="text-body-md font-bold text-on-surface">{{ $overdueCount }} Installment Overdue</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Cicilan melewati batas jatuh tempo.</p>
                </div>
            </div>
            @endif

            @if($expiringEnrollments->count() > 0)
            <div class="flex gap-md p-md bg-tertiary-fixed rounded-lg border-l-4 border-warning">
                <span class="material-symbols-outlined text-warning shrink-0">schedule</span>
                <div>
                    <p class="text-body-md font-bold text-on-surface">{{ $expiringEnrollments->count() }} Enrollment Expiring</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Berakhir dalam 7 hari ke depan.</p>
                </div>
            </div>
            @endif

            @if($stats['pending_tutors_enrollments'] == 0 && $overdueCount == 0 && $expiringEnrollments->count() == 0)
            <div class="flex gap-md p-md bg-secondary-container rounded-lg border-l-4 border-secondary">
                <span class="material-symbols-outlined text-secondary shrink-0">check_circle</span>
                <div>
                    <p class="text-body-md font-bold text-on-surface">Semua Aman</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Tidak ada peringatan aktif.</p>
                </div>
            </div>
            @endif

            {{-- Divider + stat ringkas --}}
            <div class="border-t border-surface-border pt-md space-y-sm">
                <div class="flex items-center justify-between text-body-md">
                    <span class="text-on-surface-variant">Enrollment expiring</span>
                    <span class="font-bold text-on-surface">{{ $expiringEnrollments->count() }}</span>
                </div>
                <div class="flex items-center justify-between text-body-md">
                    <span class="text-on-surface-variant">Cicilan belum lunas</span>
                    <span class="font-bold text-on-surface">{{ $unpaidInstallments->count() }}</span>
                </div>
                <div class="flex items-center justify-between text-body-md">
                    <span class="text-on-surface-variant">Pending tutor assignment</span>
                    <span class="font-bold {{ $stats['pending_tutors_enrollments'] > 0 ? 'text-error' : 'text-on-surface' }}">{{ $stats['pending_tutors_enrollments'] }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════
         BOTTOM GRID — Expiring + Installments + Quick Menu
         Layout: 8/12 kiri (2 tabel stack), 4/12 kanan (menu cepat)
    ════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

        {{-- Kiri: 2 tabel penting --}}
        <div class="lg:col-span-8 space-y-lg">

            {{-- Expiring Enrollments --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                <div class="px-lg py-md border-b border-surface-border flex items-center gap-sm">
                    <span class="material-symbols-outlined text-warning">schedule</span>
                    <h3 class="text-headline-md font-semibold text-on-surface flex-1">Enrollments Expiring dalam 7 Hari</h3>
                    <span class="badge badge-soft">{{ $expiringEnrollments->count() }} total</span>
                </div>
                @if($expiringEnrollments->isEmpty())
                    <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <p class="text-body-md">Tidak ada enrollment yang akan segera berakhir.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr class="text-label-lg text-on-surface-variant">
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th>Expiry Date</th>
                                    <th>Sisa Hari</th>
                                    <th>Sisa Pertemuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expiringEnrollments as $enrollment)
                                @php $daysLeft = \Carbon\Carbon::today()->diffInDays($enrollment->expiry_date, false); @endphp
                                <tr class="{{ $daysLeft <= 3 ? 'bg-error-container' : 'bg-tertiary-fixed' }}">
                                    <td class="font-semibold">{{ $enrollment->student->user->name }}</td>
                                    <td>{{ $enrollment->program->name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($enrollment->expiry_date)->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge {{ $daysLeft <= 3 ? 'badge-error' : 'badge-warning' }} badge-soft">
                                            {{ $daysLeft }} hari
                                        </span>
                                    </td>
                                    <td>{{ $enrollment->remaining_meetings }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Unpaid Installments --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                <div class="px-lg py-md border-b border-surface-border flex items-center gap-sm">
                    <span class="material-symbols-outlined text-error">credit_card_off</span>
                    <h3 class="text-headline-md font-semibold text-on-surface flex-1">Installments Belum Lunas</h3>
                    <span class="badge badge-soft">{{ $unpaidInstallments->count() }} total</span>
                </div>
                @if($unpaidInstallments->isEmpty())
                    <div class="p-lg flex items-center gap-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <p class="text-body-md">Semua cicilan sudah lunas.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
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
                                @php $isOverdue = \Carbon\Carbon::today()->isAfter($inst->due_date); @endphp
                                <tr class="{{ $isOverdue ? 'bg-error-container' : '' }}">
                                    <td class="font-semibold">{{ $inst->enrollment->student->user->name }}</td>
                                    <td>{{ $inst->enrollment->program->name }}</td>
                                    <td>IDR {{ number_format($inst->amount) }}</td>
                                    <td>{{ \Carbon\Carbon::parse($inst->due_date)->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge {{ $isOverdue ? 'badge-error' : 'badge-warning' }} badge-soft">
                                            {{ $isOverdue ? 'Overdue' : 'Upcoming' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>

        {{-- Kanan: Menu Cepat --}}
        <div class="lg:col-span-4">
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg sticky top-4">
                <div class="flex items-center gap-sm mb-md">
                    <span class="material-symbols-outlined text-on-surface-variant">grid_view</span>
                    <h3 class="text-headline-md font-semibold text-on-surface">Menu Cepat</h3>
                </div>
                <div class="grid grid-cols-2 gap-sm">
                    @php
                        $menus = [
                            ['route' => 'admin.students.index',   'icon' => 'school',              'label' => 'Students'],
                            ['route' => 'admin.tutors.index',     'icon' => 'person_search',       'label' => 'Tutors'],
                            ['route' => 'admin.enrollments.index','icon' => 'how_to_reg',          'label' => 'Enrollments'],
                            ['route' => 'admin.schedule.index',   'icon' => 'calendar_month',      'label' => 'Jadwal'],
                            ['route' => 'admin.attendance.index', 'icon' => 'assignment_turned_in','label' => 'Absensi'],
                            ['route' => 'admin.imports.index',    'icon' => 'upload_file',         'label' => 'Import'],
                            ['route' => 'admin.programs.index',   'icon' => 'menu_book',           'label' => 'Programs'],
                            ['route' => 'admin.classrooms.index', 'icon' => 'meeting_room',        'label' => 'Classrooms'],
                        ]
                    @endphp
                    @foreach($menus as $menu)
                    <a href="{{ route($menu['route']) }}"
                       class="group flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low hover:border-secondary/30 transition-all text-center">
                        <span class="material-symbols-outlined text-secondary group-hover:scale-110 transition-transform">{{ $menu['icon'] }}</span>
                        <span class="text-label-lg font-bold text-on-surface-variant uppercase">{{ $menu['label'] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>

{{-- ═══════════════════════════════════════════
     CHART.JS — Waiting List Bar Chart
════════════════════════════════════════════ --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('waitingChart');
    if (!canvas || typeof Chart === 'undefined') return;

    // Deteksi warna dari CSS variable (support dark mode)
    const style = getComputedStyle(document.documentElement);
    const getVar = (v) => style.getPropertyValue(v).trim();

    // Data dari Blade — di-pass lewat PHP
    const data = {
        labels: ['Reguler', 'Private', 'Semi-Private'],
        datasets: [{
            label: 'Jumlah Siswa',
            data: [
                {{ $waitingReguler->count() }},
                {{ $waitingPrivate->count() }},
                {{ $waitingSemi->count() }}
            ],
            backgroundColor: [
                'rgba(var(--color-warning-rgb, 234, 179, 8), 0.15)',
                'rgba(var(--color-error-rgb, 239, 68, 68), 0.15)',
                'rgba(var(--color-secondary-rgb, 99, 102, 241), 0.15)',
            ],
            borderColor: [
                'rgb(234, 179, 8)',
                'rgb(239, 68, 68)',
                'rgb(99, 102, 241)',
            ],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    };

    new Chart(canvas, {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.parsed.y} siswa`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { font: { size: 13 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    border: { display: false },
                    ticks: {
                        stepSize: 1,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
});
</script>
@endpush

</x-app-layout>
