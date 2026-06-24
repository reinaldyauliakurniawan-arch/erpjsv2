<x-app-layout>
<x-slot name="title">Self-Study Tracker</x-slot>

<div class="p-lg space-y-lg">

    <div class="space-y-xs">
        <h1 class="text-headline-lg font-semibold text-on-surface">Self-Study Tracker</h1>
        <p class="text-sm text-on-surface-variant">Pantau progress belajar mandirimu.</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">

        {{-- Weekly Goal --}}
        <div class="app-card flex flex-col justify-between gap-md">
            <div class="flex justify-between items-start">
                <div>
                    <span class="badge badge-soft text-primary mb-sm inline-block">Weekly Goal</span>
                    <p class="text-2xl font-bold text-on-surface mt-xs">
                        {{ $weeklyMinutes }} min
                        <span class="text-base font-normal text-on-surface-variant">/ {{ $weeklyTarget }} min</span>
                    </p>
                </div>
                <div class="w-12 h-12 bg-surface-container-low rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1">timer</span>
                </div>
            </div>
            <div>
                <div class="flex justify-between text-xs text-on-surface-variant mb-xs">
                    <span>Progress</span>
                    <span class="font-bold text-primary">{{ $weeklyPct }}%</span>
                </div>
                <div class="w-full bg-surface-container h-3 rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all duration-700" style="width: {{ $weeklyPct }}%"></div>
                </div>
            </div>
        </div>

        {{-- Total Completed --}}
        <div class="app-card flex flex-col justify-between gap-md">
            <div class="flex justify-between items-start">
                <div>
                    <span class="badge badge-soft text-tertiary mb-sm inline-block">Total Selesai</span>
                    <p class="text-2xl font-bold text-on-surface mt-xs">
                        {{ round($totalMinutes / 60, 1) }} jam
                    </p>
                </div>
                <div class="w-12 h-12 bg-surface-container-low rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-tertiary" style="font-variation-settings:'FILL' 1">auto_stories</span>
                </div>
            </div>
            <div class="flex items-center gap-xs text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                {{ $practices->where('completion_status', 'completed')->count() }} dari {{ $practices->count() }} practice selesai
            </div>
        </div>

    </div>

    {{-- Practice Breakdown --}}
    <div class="app-card app-card--flush">
        <div class="p-lg border-b border-surface-border">
            <h3 class="text-title-lg font-semibold text-on-surface">Practice Breakdown</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container text-on-surface-variant text-label-sm uppercase tracking-wider">
                    <tr>
                        <th class="px-lg py-sm font-semibold">Judul</th>
                        <th class="px-lg py-sm font-semibold">Status</th>
                        <th class="px-lg py-sm font-semibold">Durasi</th>
                        <th class="px-lg py-sm font-semibold">Deadline</th>
                        <th class="px-lg py-sm font-semibold">Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-border">
                    @forelse($practices as $p)
                    <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="px-lg py-md">
                            <div class="flex items-center gap-sm">
                                <span class="material-symbols-outlined text-on-surface-variant text-[18px]">edit_note</span>
                                <span class="text-body-sm font-medium text-on-surface">{{ $p['title'] }}</span>
                            </div>
                        </td>
                        <td class="px-lg py-md">
                            @if($p['completion_status'] === 'completed')
                                <span class="inline-flex items-center gap-xs px-xs py-0.5 rounded-md bg-tertiary/10 text-tertiary text-label-sm font-bold">
                                    <span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1">check_circle</span>Selesai
                                </span>
                            @elseif($p['opened_at'])
                                <span class="inline-flex items-center gap-xs px-xs py-0.5 rounded-md bg-surface-container text-secondary text-label-sm font-bold">
                                    <span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1">radio_button_checked</span>Dibuka
                                </span>
                            @else
                                <span class="inline-flex items-center gap-xs px-xs py-0.5 rounded-md border border-surface-border text-on-surface-variant text-label-sm font-bold">
                                    <span class="material-symbols-outlined text-[13px]">radio_button_unchecked</span>Belum Mulai
                                </span>
                            @endif
                        </td>
                        <td class="px-lg py-md text-body-sm text-on-surface-variant">
                            {{ $p['estimated_duration'] ? $p['estimated_duration'].' min' : '—' }}
                        </td>
                        <td class="px-lg py-md text-body-sm text-on-surface-variant">
                            {{ $p['deadline'] ? \Carbon\Carbon::parse($p['deadline'])->translatedFormat('d M Y') : '—' }}
                        </td>
                        <td class="px-lg py-md text-body-sm text-on-surface-variant">
                            {{ $p['completed_at'] ? \Carbon\Carbon::parse($p['completed_at'])->translatedFormat('d M Y, H:i') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-lg py-lg text-center text-body-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[48px] block mb-sm">inbox</span>
                            Belum ada practice yang di-assign ke kamu.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
</x-app-layout>
