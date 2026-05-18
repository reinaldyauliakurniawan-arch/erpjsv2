<x-app-layout>
<x-slot name="title">Dashboard</x-slot>

<div class="p-lg space-y-md">

    <div>
        <h1 class="text-xl font-semibold text-on-surface">Dashboard</h1>
        <p class="text-sm text-on-surface-variant mt-xs">Selamat datang, {{ Auth::user()->name }}</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Belum Dibayar</p>
            <p class="text-xl font-bold text-error mt-xs">Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Dibayar Bulan Ini</p>
            <p class="text-xl font-bold text-success mt-xs">Rp {{ number_format($paidThisMonth, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Rate Pending</p>
            <p class="text-xl font-bold mt-xs {{ $pendingRateCount > 0 ? 'text-warning' : 'text-on-surface' }}">
                {{ $pendingRateCount }} sesi
            </p>
            @if($pendingRateCount > 0)
            <p class="text-xs text-warning mt-xs">Hubungi admin untuk konfirmasi rate</p>
            @endif
        </div>
    </div>

    {{-- Kelas Aktif --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Kelas Aktif</h2>
        @if($classes->isEmpty())
        <p class="text-sm text-on-surface-variant">Belum ada kelas yang ditugaskan</p>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Program</th>
                    <th class="text-left font-semibold py-sm">Siswa</th>
                    <th class="text-left font-semibold py-sm">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($classes as $class)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm text-on-surface">{{ $class->program_name }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $class->student_name }}</td>
                    <td class="py-sm">
                        <span class="badge badge-soft {{ $class->status === 'active' ? 'badge-success' : 'badge-ghost' }} text-xs">
                            {{ ucfirst($class->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Absensi Terbaru --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <div class="flex items-center justify-between mb-md">
            <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide">Absensi Terbaru</h2>
            <a href="{{ route('tutor.attendance.index') }}" class="text-xs text-primary-container hover:underline">Lihat semua</a>
        </div>
        @if($recentAttendances->isEmpty())
        <p class="text-sm text-on-surface-variant">Belum ada absensi</p>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Tanggal</th>
                    <th class="text-left font-semibold py-sm">Program</th>
                    <th class="text-left font-semibold py-sm">Sesi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentAttendances as $att)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm text-on-surface">
                        {{ \Carbon\Carbon::parse($att->date)->isoFormat('D MMM YYYY') }}
                    </td>
                    <td class="py-sm text-sm text-on-surface">{{ $att->classSession?->program?->name ?? '—' }}</td>
                    <td class="py-sm text-sm text-on-surface-variant">{{ $att->time_block }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
</x-app-layout>



