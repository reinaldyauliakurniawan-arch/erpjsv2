<x-app-layout>
    <x-slot name="title">Self-Study Tracker</x-slot>

    <div class="p-lg space-y-lg">

        {{-- Header + Filters --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg">
            <div class="space-y-xs">
                <h1 class="text-headline-lg font-bold text-on-surface">Self-Study Tracker</h1>
                <p class="text-body-md text-on-surface-variant">Monitor progress belajar mandiri seluruh siswa per kelas.</p>
            </div>
            <div class="flex flex-col gap-sm items-end">
                <div class="bg-surface-container-low p-xs rounded-lg flex border border-surface-border">
                    <a href="{{ request()->fullUrlWithQuery(['period' => 'week']) }}"
                        class="px-md py-xs rounded-lg text-label-md font-semibold transition-all {{ $period ==='week' ? 'bg-surface-container-lowest shadow-sm text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                        Minggu Ini
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['period' => 'all']) }}"
                        class="px-md py-xs rounded-lg text-label-md font-semibold transition-all {{ $period ==='all' ? 'bg-surface-container-lowest shadow-sm text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                        Semua Waktu
                    </a>
                </div>
                <div class="bg-surface-container-low p-xs rounded-lg flex items-center gap-xs border border-surface-border" x-data="{ open: false }">
                    <span class="text-label-sm font-bold text-on-surface-variant px-xs">Kelas:</span>
                    <div class="relative">
                        <button @click="open = !open" class="px-sm py-xs rounded-lg text-label-sm font-semibold transition-all bg-surface-container-lowest shadow-sm flex items-center gap-xs text-on-surface">
                            {{ $classFilter ? $sessions->firstWhere('id', $classFilter)?->name ?? 'Semua' : 'Semua' }}
                            <span class="material-symbols-outlined text-[16px]">expand_more</span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition
                            class="absolute right-0 mt-xs bg-surface-container-lowest border border-surface-border rounded-lg shadow-md z-10 min-w-[140px] py-xs">
                            <a href="{{ request()->fullUrlWithQuery(['class' => null]) }}"
                                class="block px-md py-xs text-label-sm font-semibold transition-all {{ !$classFilter ?'text-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}">
                                Semua
                            </a>
                            @foreach($sessions as $session)
                            <a href="{{ request()->fullUrlWithQuery(['class' => $session->id]) }}"
                                class="block px-md py-xs text-label-sm font-semibold transition-all {{ $classFilter == $session->id ?'text-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}">
                                {{ $session->name }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stat Cards --}}
        @php
            $allStudents = collect($classes)->flatMap(fn($c) => $c['students']);
            $totalStudents = $allStudents->count();
            $completedAny = $allStudents->filter(fn($s) => $s['practices']->filter(fn($p) => $p['completion_status'] === 'completed')->count() > 0)->count();
            $belowTarget = $allStudents->filter(fn($s) => !$s['target_met'])->count();
            $avgPct = $totalStudents > 0 ? round($allStudents->avg('percentage')) : 0;
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-md">
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg p-lg">
                <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Total Siswa</p>
                <p class="text-headline-md font-bold text-on-surface">{{ $totalStudents }}</p>
            </div>
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg p-lg">
                <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Rata-rata Progress</p>
                <p class="text-headline-md font-bold text-primary">{{ $avgPct }}%</p>
            </div>
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg p-lg">
                <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Ada Progress</p>
                <p class="text-headline-md font-bold text-tertiary">{{ $completedAny }}</p>
            </div>
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg p-lg">
                <p class="text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Di Bawah Target</p>
                <p class="text-headline-md font-bold {{ $belowTarget > 0 ?'text-error' : 'text-tertiary' }}">{{ $belowTarget }}</p>
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-12 gap-lg items-start">

            {{-- Weekly Progress Table (col 8) --}}
            <section class="col-span-12 lg:col-span-8 app-card app-card--flush"
                x-data="{ expanded: null }">
                <div class="p-lg border-b border-surface-border flex justify-between items-center">
                    <div class="flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary">calendar_view_week</span>
                        <h3 class="text-title-lg font-semibold text-on-surface">Weekly Progress</h3>
                    </div>
                    <span class="px-sm py-xs bg-primary-container text-on-primary rounded-full text-label-sm font-bold uppercase tracking-wider">Active Cycle</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container text-on-surface-variant text-label-sm uppercase tracking-wider">
                            <tr>
                                <th class="px-lg py-sm font-semibold">Siswa</th>
                                <th class="px-lg py-sm font-semibold text-center">Materi</th>
                                <th class="px-lg py-sm font-semibold text-center">Menit</th>
                                <th class="px-lg py-sm font-semibold text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-border">
                            @forelse($classes as $class)
                                <tr class="bg-surface-container">
                                    <td colspan="4" class="px-lg py-xs">
                                        <span class="text-label-sm font-bold text-primary uppercase tracking-wider">{{ $class['name'] }}</span>
                                    </td>
                                </tr>
                                @forelse($class['students'] as $student)
                                <tr class="hover:bg-surface-container-low transition-colors cursor-pointer"
                                    @click="expanded = expanded === '{{ $class['id'] }}-{{ $student['id'] }}' ? null : '{{ $class['id'] }}-{{ $student['id'] }}'">
                                    <td class="px-lg py-md">
                                        <div class="flex items-center gap-sm">
                                            <span class="material-symbols-outlined text-on-surface-variant text-[18px] transition-transform duration-200"
                                                :style="expanded === '{{ $class['id'] }}-{{ $student['id'] }}' ? 'transform:rotate(90deg)' : ''">chevron_right</span>
                                            <div class="w-8 h-8 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-label-sm shrink-0">
                                                {{ strtoupper(substr($student['name'], 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="text-body-md font-semibold text-on-surface leading-tight">{{ $student['name'] }}</p>
                                                @if($student['target_met'])
                                                    <p class="text-label-sm text-tertiary flex items-center gap-xs">
                                                        <span class="material-symbols-outlined text-[14px]">verified</span> Target tercapai
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-lg py-md">
                                        <div class="flex justify-center gap-xs">
                                            @foreach($student['practices'] as $p)
                                                @if($p['completion_status'] === 'completed')
                                                    <span class="material-symbols-outlined text-tertiary text-[18px]" style="font-variation-settings:'FILL' 1">check_circle</span>
                                                @elseif($p['completion_status'] === 'in_progress')
                                                    <span class="material-symbols-outlined text-secondary text-[18px]" style="font-variation-settings:'FILL' 1">radio_button_checked</span>
                                                @else
                                                    <span class="material-symbols-outlined text-on-surface-variant text-[18px]">radio_button_unchecked</span>
                                                @endif
                                            @endforeach
                                            @if($student['practices']->isEmpty())
                                                <span class="text-label-sm text-on-surface-variant">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-lg py-md text-center text-body-md text-on-surface">{{ $student['total_minutes'] }}m</td>
                                    <td class="px-lg py-md text-right">
                                        <span class="font-bold {{ $student['target_met'] ? 'text-tertiary' : ($student['percentage'] >= 50 ? 'text-primary' : 'text-on-surface-variant') }}">
                                            {{ $student['percentage'] }}%
                                        </span>
                                    </td>
                                </tr>

                                {{-- Expandable Breakdown --}}
                                <tr x-show="expanded === '{{ $class['id'] }}-{{ $student['id'] }}'" x-transition>
                                    <td colspan="4" class="p-0">
                                        <div class="bg-surface-container-low border-y border-surface-border p-lg">
                                            <div class="flex items-center gap-sm mb-md">
                                                <span class="material-symbols-outlined text-primary text-[16px]">list_alt</span>
                                                <p class="text-label-sm font-bold text-primary uppercase tracking-wide">Practice Breakdown — {{ $student['name'] }}</p>
                                            </div>
                                            <div class="rounded-lg border border-surface-border overflow-hidden">
                                                <table class="w-full text-left border-collapse">
                                                    <thead class="bg-surface-container text-on-surface-variant text-label-sm uppercase">
                                                        <tr>
                                                            <th class="px-md py-sm font-semibold">Judul</th>
                                                            <th class="px-md py-sm font-semibold">Status</th>
                                                            <th class="px-md py-sm font-semibold">Dibuka</th>
                                                            <th class="px-md py-sm font-semibold">Selesai</th>
                                                            <th class="px-md py-sm font-semibold">Durasi</th>
                                                            <th class="px-md py-sm font-semibold">Refleksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-surface-border">
                                                        @forelse($student['practices'] as $p)
                                                        <tr class="bg-white">
                                                            <td class="px-md py-sm text-body-sm font-medium text-on-surface">{{ $p['title'] }}</td>
                                                            <td class="px-md py-sm">
                                                                @if($p['completion_status'] === 'completed')
                                                                    <span class="inline-flex items-center gap-xs px-xs py-0.5 rounded bg-tertiary/10 text-tertiary text-label-sm font-bold">
                                                                        <span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1">check_circle</span>Selesai
                                                                    </span>
                                                                @elseif($p['completion_status'] === 'in_progress')
                                                                    <span class="inline-flex items-center gap-xs px-xs py-0.5 rounded bg-surface-container text-secondary text-label-sm font-bold">
                                                                        <span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1">radio_button_checked</span>Dibuka
                                                                    </span>
                                                                @else
                                                                    <span class="inline-flex items-center gap-xs px-xs py-0.5 rounded border border-surface-border text-on-surface-variant text-label-sm font-bold">
                                                                        <span class="material-symbols-outlined text-[13px]">radio_button_unchecked</span>Belum Mulai
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="px-md py-sm text-body-sm text-on-surface-variant">{{ $p['opened_at'] ? \Carbon\Carbon::parse($p['opened_at'])->format('d M, H:i') : '—' }}</td>
                                                            <td class="px-md py-sm text-body-sm text-on-surface-variant">{{ $p['completed_at'] ? \Carbon\Carbon::parse($p['completed_at'])->format('d M, H:i') : '—' }}</td>
                                                            <td class="px-md py-sm text-body-sm text-on-surface-variant">{{ $p['estimated_duration'] ? $p['estimated_duration'].'m' : '—' }}</td>
                                                            <td class="px-md py-sm text-body-sm text-on-surface-variant italic max-w-[200px]">{{ $p['reflection'] ?? '—' }}</td>
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="6" class="px-md py-sm text-body-sm text-on-surface-variant text-center">Belum ada materi assigned.</td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-lg py-md text-body-sm text-on-surface-variant text-center">Belum ada siswa.</td>
                                </tr>
                                @endforelse
                            @empty
                            <tr>
                                <td colspan="4" class="px-lg py-md text-body-sm text-on-surface-variant text-center">Belum ada data kelas.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Right Column (col 4) --}}
            <div class="col-span-12 lg:col-span-4 space-y-lg">

                {{-- Overall Progress --}}
                <section class="app-card app-card--flush">
                    <div class="p-lg border-b border-surface-border flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary">analytics</span>
                        <h3 class="text-title-lg font-semibold text-on-surface">Overall Progress</h3>
                    </div>
                    <div class="p-lg space-y-lg">
                        @forelse($classes as $class)
                        <div class="space-y-sm">
                            <p class="text-label-sm font-bold text-primary uppercase tracking-wider border-b border-surface-border pb-xs">{{ $class['name'] }}</p>
                            @forelse($class['students'] as $student)
                            <div class="space-y-xs">
                                <div class="flex justify-between items-center">
                                    <p class="text-body-sm font-semibold text-on-surface">{{ $student['name'] }}</p>
                                    <div class="flex items-center gap-xs">
                                        @if($student['target_met'])
                                            <span class="material-symbols-outlined text-tertiary text-[14px]">workspace_premium</span>
                                        @endif
                                        <span class="text-label-md font-bold {{ $student['target_met'] ? 'text-tertiary' : ($student['percentage'] >= 50 ? 'text-primary' : 'text-on-surface-variant') }}">
                                            {{ $student['percentage'] }}%
                                        </span>
                                    </div>
                                </div>
                                <div class="w-full bg-surface-container h-2 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-700 {{ $student['target_met'] ? 'bg-tertiary' : 'bg-primary' }}"
                                        style="width: {{ min($student['percentage'], 100) }}%"></div>
                                </div>
                                <p class="text-label-sm text-on-surface-variant">{{ round($student['total_minutes'] / 60, 1) }} hrs total</p>
                            </div>
                            @empty
                            <p class="text-body-sm text-on-surface-variant">Belum ada siswa.</p>
                            @endforelse
                        </div>
                        @empty
                        <p class="text-body-sm text-on-surface-variant text-center">Belum ada data.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Legend --}}
                <section class="bg-surface-container-low border border-surface-border rounded-lg p-lg">
                    <h4 class="text-label-sm font-bold text-on-surface-variant uppercase tracking-widest mb-md">Keterangan</h4>
                    <div class="space-y-sm">
                        <div class="flex items-center gap-sm p-sm bg-surface-container-lowest rounded-lg border border-surface-border">
                            <span class="material-symbols-outlined text-on-surface-variant text-[18px]">radio_button_unchecked</span>
                            <span class="text-body-sm">Belum Mulai</span>
                        </div>
                        <div class="flex items-center gap-sm p-sm bg-surface-container-lowest rounded-lg border border-surface-border">
                            <span class="material-symbols-outlined text-secondary text-[18px]" style="font-variation-settings:'FILL' 1">radio_button_checked</span>
                            <span class="text-body-sm">Dibuka</span>
                        </div>
                        <div class="flex items-center gap-sm p-sm bg-surface-container-lowest rounded-lg border border-surface-border">
                            <span class="material-symbols-outlined text-tertiary text-[18px]" style="font-variation-settings:'FILL' 1">check_circle</span>
                            <span class="text-body-sm">Selesai</span>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </div>
</x-app-layout>
