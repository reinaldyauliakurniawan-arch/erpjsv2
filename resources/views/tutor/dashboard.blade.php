<x-app-layout>
<x-slot name="title">Dashboard</x-slot>

<div class="p-lg space-y-md">

    <div>
        <h1 class="text-xl font-semibold text-on-surface">Halo, {{ Auth::user()->name }} 👋</h1>
        <p class="text-sm text-on-surface-variant mt-xs">{{ now()->translatedFormat('l, d F Y') }}</p>
    </div>

    {{-- Row 1: Keuangan --}}
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:1rem;">

        {{-- Belum Dibayar --}}
        <div class="bg-primary-container rounded-lg p-lg relative overflow-hidden" style="min-height:160px;display:flex;flex-direction:column;justify-content:space-between;">
            <div style="position:relative;z-index:1;">
                <p class="text-xs text-on-primary/70 uppercase tracking-widest font-semibold">Belum Dibayar</p>
                <p class="text-on-primary font-bold mt-sm" style="font-size:2.25rem;">Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</p>
                <p class="text-xs text-on-primary/60 mt-xs">Menunggu pembayaran dari admin</p>
                @if($pendingRateCount > 0)
                <p class="text-xs text-on-primary/60 mt-xs">⚠️ {{ $pendingRateCount }} sesi belum ada rate — fee akan muncul setelah admin konfirmasi</p>
                @endif
            </div>
            @if($pendingRateCount > 0)
            <div style="position:relative;z-index:1;" class="mt-md">
                <div class="flex items-center gap-xs bg-white/10 rounded-lg px-sm py-xs w-fit">
                    <span class="material-symbols-outlined text-warning" style="font-size:16px;">warning</span>
                    <p class="text-xs text-on-primary font-semibold">{{ $pendingRateCount }} sesi rate pending</p>
                </div>
            </div>
            @endif
            <div style="position:absolute;right:-1.5rem;bottom:-1.5rem;opacity:0.1;z-index:0;">
                <span class="material-symbols-outlined text-on-primary" style="font-size:120px;font-variation-settings:'FILL' 1;">account_balance_wallet</span>
            </div>
        </div>

        {{-- Dibayar Bulan Ini --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg p-lg relative overflow-hidden" style="min-height:160px;display:flex;flex-direction:column;justify-content:space-between;">
            <div style="position:relative;z-index:1;">
                <p class="text-xs text-on-surface-variant uppercase tracking-widest font-semibold">Dibayar Bulan Ini</p>
                <p class="text-success font-bold mt-sm" style="font-size:2.25rem;">Rp {{ number_format($paidThisMonth, 0, ',', '.') }}</p>
                <p class="text-xs text-on-surface-variant mt-xs">{{ now()->translatedFormat('F Y') }}</p>
            </div>
            <div class="flex items-center gap-xs mt-md" style="position:relative;z-index:1;">
                <span class="material-symbols-outlined text-success" style="font-size:16px;font-variation-settings:'FILL' 1;">check_circle</span>
                <p class="text-xs text-on-surface-variant">{{ $pendingRateCount === 0 ? 'Semua rate terkonfirmasi' : $pendingRateCount.' rate pending' }}</p>
            </div>
            <div style="position:absolute;right:-1.5rem;bottom:-1.5rem;opacity:0.05;z-index:0;">
                <span class="material-symbols-outlined text-success" style="font-size:120px;font-variation-settings:'FILL' 1;">payments</span>
            </div>
        </div>

    </div>

    {{-- Row 2: Kelas + Sidebar --}}
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:1rem;align-items:start;">

        {{-- Kelas Aktif --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Kelas Aktif</h2>
            @if($classes->isEmpty())
            <div class="flex flex-col items-center justify-center py-lg text-center">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:2.5rem;">school</span>
                <p class="text-sm text-on-surface-variant mt-sm">Belum ada kelas yang ditugaskan</p>
            </div>
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
                    <tr class="border-b border-surface-border last:border-0">
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

        {{-- Sidebar --}}
        <div style="display:flex;flex-direction:column;gap:1rem;">

            {{-- Rate Pending --}}
            @if($pendingRateCount > 0)
            <div class="border border-warning/30 rounded-lg p-lg" style="background:rgba(234,179,8,0.08);">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-warning">warning</span>
                    <div>
                        <p class="text-sm font-semibold text-on-surface">{{ $pendingRateCount }} Rate Pending</p>
                        <p class="text-xs text-on-surface-variant mt-xs">Hubungi admin untuk konfirmasi</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Sesi Terakhir --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                <div class="flex items-center justify-between mb-md">
                    <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide">Sesi Terakhir</h2>
                    <a href="{{ route('tutor.attendance.index') }}" class="text-xs text-primary hover:underline">Semua</a>
                </div>
                @if($recentAttendances->isEmpty())
                <p class="text-sm text-on-surface-variant">Belum ada absensi</p>
                @else
                <div>
                    @foreach($recentAttendances as $att)
                    <div class="flex items-center justify-between py-xs border-b border-surface-border last:border-0">
                        <div>
                            <p class="text-sm font-medium text-on-surface leading-tight">{{ $att->classSession?->program?->name ?? '—' }}</p>
                            <p class="text-xs text-on-surface-variant">{{ $att->time_block }}</p>
                        </div>
                        <p class="text-xs text-on-surface-variant" style="margin-left:0.5rem;white-space:nowrap;">{{ \Carbon\Carbon::parse($att->date)->isoFormat('D MMM') }}</p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Row 3: Riwayat Di-Replace --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <div class="flex items-center gap-sm mb-md">
            <span class="material-symbols-outlined text-on-surface-variant">swap_horiz</span>
            <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide">Riwayat Di-Replace</h2>
        </div>
        @if($replacedHistory->isEmpty())
        <p class="text-sm text-on-surface-variant">Belum pernah di-replace</p>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Tanggal</th>
                    <th class="text-left font-semibold py-sm">Kelas</th>
                    <th class="text-left font-semibold py-sm">Sesi</th>
                    <th class="text-left font-semibold py-sm">Digantikan Oleh</th>
                </tr>
            </thead>
            <tbody>
                @foreach($replacedHistory as $r)
                <tr class="border-b border-surface-border last:border-0">
                    <td class="py-sm text-sm text-on-surface">{{ \Carbon\Carbon::parse($r->date)->isoFormat('D MMM YYYY') }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $r->class_name }}</td>
                    <td class="py-sm text-sm text-on-surface-variant">{{ $r->time_block }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $r->replaced_by }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
</x-app-layout>
