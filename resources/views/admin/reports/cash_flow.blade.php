<x-app-layout>
<x-slot name="title">Cash Flow</x-slot>

<div class="p-lg space-y-md" style="max-width:72rem">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-md flex-wrap">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Laporan Arus Kas</h1>
            <p class="text-sm text-on-surface-variant mt-xs">
                {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
            </p>
        </div>
        <button type="button" onclick="window.print()" class="btn btn-sm btn-ghost border border-surface-border no-print">
            <span class="material-symbols-outlined text-base">print</span>
            Cetak
        </button>
    </div>

    {{-- Filter --}}
    <div class="app-card no-print">
        <div class="flex items-end gap-md flex-wrap">
            <div class="fieldset">
                <label class="fieldset-legend">Periode</label>
                <select id="filter-period" class="select select-sm" onchange="toggleCustom(this)">
                    <option value="month"   {{ $period === 'month'   ? 'selected' : '' }}>Bulan Ini</option>
                    <option value="quarter" {{ $period === 'quarter' ? 'selected' : '' }}>Kuartal Ini</option>
                    <option value="year"    {{ $period === 'year'    ? 'selected' : '' }}>Tahun Ini</option>
                    <option value="custom"  {{ $period === 'custom'  ? 'selected' : '' }}>Custom</option>
                </select>
            </div>
            <div id="custom-range" class="flex gap-sm items-end {{ $period ==='custom' ? '' : 'hidden' }}">
                <div class="fieldset">
                    <label class="fieldset-legend">Dari</label>
                    <input type="date" id="filter-from" class="input input-sm" value="{{ $from }}">
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend">Sampai</label>
                    <input type="date" id="filter-to" class="input input-sm" value="{{ $to }}">
                </div>
            </div>
            <button type="button" onclick="applyFilter()" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md no-print" style="grid-template-columns:repeat(3,1fr)">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Arus Kas Bersih</p>
            <p class="text-xl font-bold mt-xs {{ $netChange >= 0 ?'text-success' : 'text-error' }}">
                {{ $netChange >= 0 ? '' : '-' }}Rp {{ number_format(abs($netChange),0,',','.') }}
            </p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Kas Akhir Periode</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($cashEnding,0,',','.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Aktivitas Operasi</p>
            <p class="text-xl font-bold mt-xs {{ $netOperating >= 0 ?'text-on-surface' : 'text-error' }}">
                {{ $netOperating >= 0 ? '' : '-' }}Rp {{ number_format(abs($netOperating),0,',','.') }}
            </p>
        </div>
    </div>

    {{-- Tabel Formal --}}
    <div class="app-card app-card--flush">

        <div class="px-lg py-md bg-surface-container border-b border-surface-border">
            <p class="font-semibold text-on-surface">Laporan Arus Kas (Metode Langsung)</p>
            <p class="text-xs text-on-surface-variant mt-xs">Just Speak · {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}</p>
        </div>

        <table class="w-full text-sm">

            {{-- I. Operasi --}}
            <thead>
                <tr class="bg-surface-container border-b border-surface-border">
                    <th class="px-lg py-sm text-left text-xs font-semibold text-on-surface uppercase tracking-wide" colspan="2">
                        I. Aktivitas Operasi
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-border">
                @forelse($operating as $row)
                <tr class="hover:bg-surface-container/50 transition-colors">
                    <td class="px-lg py-sm text-on-surface pl-10">{{ $row->name }}</td>
                    <td class="px-lg py-sm text-right font-mono {{ $row->net < 0 ?'text-error' : 'text-on-surface' }}">
                        {{ $row->net < 0 ? '(' : '' }}Rp {{ number_format(abs($row->net),0,',','.') }}{{ $row->net < 0 ? ')' : '' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="px-lg py-sm text-on-surface-variant italic">Tidak ada data.</td>
                </tr>
                @endforelse
                <tr class="bg-surface-container font-semibold border-t border-surface-border">
                    <td class="px-lg py-sm text-on-surface">Arus Kas Bersih dari Aktivitas Operasi</td>
                    <td class="px-lg py-sm text-right font-mono {{ $netOperating < 0 ?'text-error' : 'text-on-surface' }}">
                        {{ $netOperating < 0 ? '(' : '' }}Rp {{ number_format(abs($netOperating),0,',','.') }}{{ $netOperating < 0 ? ')' : '' }}
                    </td>
                </tr>
            </tbody>

            {{-- II. Investasi --}}
            <thead>
                <tr class="bg-surface-container border-y border-surface-border">
                    <th class="px-lg py-sm text-left text-xs font-semibold text-on-surface uppercase tracking-wide" colspan="2">
                        II. Aktivitas Investasi
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-border">
                @forelse($investing as $row)
                <tr class="hover:bg-surface-container/50 transition-colors">
                    <td class="px-lg py-sm text-on-surface pl-10">{{ $row->name }}</td>
                    <td class="px-lg py-sm text-right font-mono {{ $row->net < 0 ?'text-error' : 'text-on-surface' }}">
                        {{ $row->net < 0 ? '(' : '' }}Rp {{ number_format(abs($row->net),0,',','.') }}{{ $row->net < 0 ? ')' : '' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="px-lg py-sm text-on-surface-variant italic">Tidak ada data.</td>
                </tr>
                @endforelse
                <tr class="bg-surface-container font-semibold border-t border-surface-border">
                    <td class="px-lg py-sm text-on-surface">Arus Kas Bersih dari Aktivitas Investasi</td>
                    <td class="px-lg py-sm text-right font-mono {{ $netInvesting < 0 ?'text-error' : 'text-on-surface' }}">
                        {{ $netInvesting < 0 ? '(' : '' }}Rp {{ number_format(abs($netInvesting),0,',','.') }}{{ $netInvesting < 0 ? ')' : '' }}
                    </td>
                </tr>
            </tbody>

            {{-- III. Pendanaan --}}
            <thead>
                <tr class="bg-surface-container border-y border-surface-border">
                    <th class="px-lg py-sm text-left text-xs font-semibold text-on-surface uppercase tracking-wide" colspan="2">
                        III. Aktivitas Pendanaan
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-border">
                @forelse($financing as $row)
                <tr class="hover:bg-surface-container/50 transition-colors">
                    <td class="px-lg py-sm text-on-surface pl-10">{{ $row->name }}</td>
                    <td class="px-lg py-sm text-right font-mono {{ $row->net < 0 ?'text-error' : 'text-on-surface' }}">
                        {{ $row->net < 0 ? '(' : '' }}Rp {{ number_format(abs($row->net),0,',','.') }}{{ $row->net < 0 ? ')' : '' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="px-lg py-sm text-on-surface-variant italic">Tidak ada data.</td>
                </tr>
                @endforelse
                <tr class="bg-surface-container font-semibold border-t border-surface-border">
                    <td class="px-lg py-sm text-on-surface">Arus Kas Bersih dari Aktivitas Pendanaan</td>
                    <td class="px-lg py-sm text-right font-mono {{ $netFinancing < 0 ?'text-error' : 'text-on-surface' }}">
                        {{ $netFinancing < 0 ? '(' : '' }}Rp {{ number_format(abs($netFinancing),0,',','.') }}{{ $netFinancing < 0 ? ')' : '' }}
                    </td>
                </tr>
            </tbody>

            {{-- Summary --}}
            <tfoot>
                <tr class="bg-primary text-on-primary">
                    <td class="px-lg py-md font-semibold">Kenaikan (Penurunan) Kas Bersih</td>
                    <td class="px-lg py-md text-right font-mono text-lg font-bold">
                        {{ $netChange < 0 ? '(' : '' }}Rp {{ number_format(abs($netChange),0,',','.') }}{{ $netChange < 0 ? ')' : '' }}
                    </td>
                </tr>
                <tr class="border-t border-surface-border">
                    <td class="px-lg py-sm text-on-surface">Kas pada Awal Periode</td>
                    <td class="px-lg py-sm text-right font-mono text-on-surface">
                        Rp {{ number_format($cashOpening,0,',','.') }}
                    </td>
                </tr>
                <tr class="bg-surface-container border-t-2 border-primary">
                    <td class="px-lg py-md font-bold text-on-surface uppercase text-xs tracking-wide">Kas pada Akhir Periode</td>
                    <td class="px-lg py-md text-right font-mono font-bold text-lg text-primary">
                        Rp {{ number_format($cashEnding,0,',','.') }}
                    </td>
                </tr>
                @if(isset($cashDifference) && $cashDifference != 0)
                <tr class="bg-error/10 border-t border-error">
                    <td colspan="2" class="px-lg py-sm text-sm text-error">
                        ⚠ Ada selisih Rp {{ number_format(abs($cashDifference),0,',','.') }} — kemungkinan ada transaksi kas yang belum dikategorikan ke operating/investing/financing.
                    </td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>

</div>

<style>
@media print {
    .no-print { display: none !important; }
}
</style>

<script>
function toggleCustom(sel) {
    document.getElementById('custom-range').classList.toggle('hidden', sel.value !== 'custom');
}

function getPeriodRange(period) {
    const now = new Date();
    let from, to = now.toISOString().split('T')[0];
    if (period === 'month') {
        from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    } else if (period === 'quarter') {
        const q = Math.floor(now.getMonth() / 3);
        from = new Date(now.getFullYear(), q * 3, 1).toISOString().split('T')[0];
    } else if (period === 'year') {
        from = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
    }
    return { from, to };
}

function applyFilter() {
    const period = document.getElementById('filter-period').value;
    let from, to;
    if (period === 'custom') {
        from = document.getElementById('filter-from').value;
        to   = document.getElementById('filter-to').value;
        if (!from || !to) return;
    } else {
        ({ from, to } = getPeriodRange(period));
    }
    window.location.href = `{{ route('finance.reports.cash-flow') }}?period=${period}&from=${from}&to=${to}`;
}
</script>
</x-app-layout>
