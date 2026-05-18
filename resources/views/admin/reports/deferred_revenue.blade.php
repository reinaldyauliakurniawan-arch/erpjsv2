<x-app-layout>
    <x-slot name="title">Deferred Revenue</x-slot>

    <div class="p-lg space-y-md">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-on-surface">Deferred Revenue</h1>
                <p class="text-sm text-on-surface-variant mt-xs">Pendapatan yang belum diakui dari enrollment aktif</p>
            </div>
        </div>

        {{-- Summary --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Deferred Revenue</p>
            <p class="text-2xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalDeferred, 0, ',', '.') }}</p>
            <p class="text-xs text-on-surface-variant mt-xs">{{ $enrollments->count() }} enrollment aktif</p>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            @if($enrollments->isEmpty())
                <div class="text-center py-xl text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl">savings</span>
                    <p class="mt-sm text-sm">Tidak ada deferred revenue</p>
                </div>
            @else
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                            <th class="text-left font-semibold py-sm">Siswa</th>
                            <th class="text-left font-semibold py-sm">Program</th>
                            <th class="text-right font-semibold py-sm">Total Sesi</th>
                            <th class="text-right font-semibold py-sm">Terpakai</th>
                            <th class="text-right font-semibold py-sm">Sisa</th>
                            <th class="text-right font-semibold py-sm">Sudah Dibayar</th>
                            <th class="text-right font-semibold py-sm">Rate/Sesi</th>
                            <th class="text-right font-semibold py-sm">Deferred</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $e)
                        <tr class="border-b border-surface-border">
                            <td class="py-sm text-sm text-on-surface">{{ $e['student_name'] }}</td>
                            <td class="py-sm text-sm text-on-surface">{{ $e['program_name'] }}</td>
                            <td class="py-sm text-sm text-right text-on-surface">{{ $e['total_meetings'] }}</td>
                            <td class="py-sm text-sm text-right text-on-surface">{{ $e['meetings_used'] }}</td>
                            <td class="py-sm text-sm text-right text-on-surface">{{ $e['remaining'] }}</td>
                            <td class="py-sm text-sm text-right text-on-surface-variant">Rp {{ number_format($e['paid_amount'], 0, ',', '.') }}</td>
                            <td class="py-sm text-sm text-right text-on-surface-variant">Rp {{ number_format($e['rate_per_meeting'], 0, ',', '.') }}</td>
                            <td class="py-sm text-sm text-right font-medium text-on-surface">Rp {{ number_format($e['deferred_amount'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-surface-border">
                            <td colspan="7" class="py-sm text-sm font-semibold text-on-surface">Total</td>
                            <td class="py-sm text-sm font-semibold text-right text-on-surface">
                                Rp {{ number_format($totalDeferred, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>

    </div>
</x-app-layout>



