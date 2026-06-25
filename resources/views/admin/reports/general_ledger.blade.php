<x-app-layout>
<x-slot name="title">General Ledger</x-slot>

<div class="p-lg space-y-md" style="max-width:72rem">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-md flex-wrap">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">General Ledger</h1>
            <p class="text-sm text-on-surface-variant mt-xs">
                Buku besar per akun · {{ \Carbon\Carbon::parse($from)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($to)->translatedFormat('d M Y') }}
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
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Debet</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalDebet,0,',','.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Kredit</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalKredit,0,',','.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Akun Aktif</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">{{ $activeAccounts }}</p>
        </div>
    </div>

    {{-- Ledger per Akun --}}
    <div class="space-y-md">
        @forelse($ledger as $account)
        <div class="app-card app-card--flush">

            {{-- Account Header --}}
            <div class="flex items-center justify-between px-lg py-md bg-surface-container border-b border-surface-border flex-wrap gap-sm">
                <div>
                    <p class="font-semibold text-on-surface">{{ $account['code'] }} · {{ $account['name'] }}</p>
                    <p class="text-xs text-on-surface-variant mt-xs">{{ $account['type'] }}</p>
                </div>
                <div class="text-right flex flex-col items-end gap-xs">
                    <span class="badge badge-soft text-xs">
                        Normal: {{ $account['normal_debet'] ? 'DEBET' : 'KREDIT' }}
                    </span>
                    <p class="text-sm font-semibold text-on-surface">
                        Saldo Akhir:
                        @if($account['normal_debet'])
                            <span class="{{ $account['final_balance'] >= 0 ? 'text-on-surface' : 'text-error' }}">
                                Rp {{ number_format(abs($account['final_balance']),0,',','.') }}
                                {{ $account['final_balance'] < 0 ? '(Cr)' : '' }}
                            </span>
                        @else
                            <span class="{{ $account['final_balance'] >= 0 ? 'text-on-surface' : 'text-error' }}">
                                Rp {{ number_format(abs($account['final_balance']),0,',','.') }}
                                {{ $account['final_balance'] < 0 ? '(Dr)' : '' }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Tabel Transaksi --}}
            <div class="overflow-x-auto">
                <div class="app-table-wrapper">
<table class="w-full text-sm">
                    <thead class="bg-surface-container text-xs text-on-surface-variant uppercase tracking-wide border-b border-surface-border">
                        <tr>
                            <th class="px-md py-sm text-left w-28">Tanggal</th>
                            <th class="px-md py-sm text-left">Keterangan</th>
                            <th class="px-md py-sm text-left w-40 hidden sm:table-cell">Referensi</th>
                            <th class="px-md py-sm text-right w-36">Debet</th>
                            <th class="px-md py-sm text-right w-36">Kredit</th>
                            <th class="px-md py-sm text-right w-36">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border text-on-surface">

                        {{-- Saldo Awal --}}
                        <tr class="bg-surface-container/40">
                            <td class="px-md py-sm text-on-surface-variant text-xs">{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</td>
                            <td class="px-md py-sm italic text-on-surface-variant text-xs" colspan="2">Saldo Awal</td>
                            <td class="px-md py-sm text-right text-on-surface-variant text-xs hidden sm:table-cell"></td>
                            <td class="px-md py-sm text-right text-on-surface-variant text-xs">—</td>
                            <td class="px-md py-sm text-right text-xs font-medium">
                                Rp {{ number_format(abs($account['opening_balance']),0,',','.') }}
                            </td>
                        </tr>

                        @forelse($account['rows'] as $row)
                        <tr class="hover:bg-surface-container/50 transition-colors">
                            <td class="px-md py-sm text-on-surface-variant">
                                {{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}
                            </td>
                            <td class="px-md py-sm">{{ $row['description'] }}</td>
                            <td class="px-md py-sm hidden sm:table-cell">
                                <span class="badge badge-soft font-mono text-xs whitespace-nowrap">{{ $row['reference'] }}</span>
                            </td>
                            <td class="px-md py-sm text-right font-mono">
                                {{ $row['debit'] > 0 ? 'Rp '.number_format($row['debit'],0,',','.') : '—' }}
                            </td>
                            <td class="px-md py-sm text-right font-mono">
                                {{ $row['credit'] > 0 ? 'Rp '.number_format($row['credit'],0,',','.') : '—' }}
                            </td>
                            <td class="px-md py-sm text-right font-mono font-medium">
                                Rp {{ number_format(abs($row['running']),0,',','.') }}
                                @if($row['running'] < 0)
                                    <span class="text-error text-xs">({{ $account['normal_debet'] ? 'Cr' : 'Dr' }})</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-md py-lg text-center text-on-surface-variant text-sm">
                                Tidak ada transaksi di periode ini.
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
</div>
            </div>
        </div>
        @empty
        <div class="app-card text-center text-on-surface-variant">
            Tidak ada data untuk periode ini.
        </div>
        @endforelse
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
    window.location.href = `{{ route('finance.reports.general-ledger') }}?period=${period}&from=${from}&to=${to}`;
}
</script>
</x-app-layout>
