<x-app-layout>
<x-slot name="title">Balance Sheet</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Balance Sheet</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Posisi keuangan per tanggal tertentu</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <form method="GET" action="{{ route('finance.reports.balance-sheet') }}" class="flex items-end gap-md">
            <div class="fieldset">
                <label class="fieldset-legend">Per Tanggal</label>
                <input type="date" name="as_of" class="input w-full" value="{{ $asOf }}">
            </div>
            <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Tampilkan
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Aset</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalAsset, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Liabilitas</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalLiability, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Ekuitas</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalEquity, 0, ',', '.') }}</p>
        </div>
    </div>

    @foreach(['Asset' => 'Aset', 'Liability' => 'Liabilitas', 'Equity' => 'Ekuitas'] as $type => $label)
    @php $group = $rows->where('type', $type); @endphp
    @if($group->isNotEmpty())
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">{{ $label }}</h2>
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Kode</th>
                    <th class="text-left font-semibold py-sm">Nama Akun</th>
                    <th class="text-right font-semibold py-sm">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group as $row)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm font-mono text-on-surface-variant">{{ $row->code }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $row->name }}</td>
                    <td class="py-sm text-sm text-right text-on-surface">Rp {{ number_format($row->balance, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border">
                    <td colspan="2" class="py-sm text-sm font-semibold text-on-surface">Total {{ $label }}</td>
                    <td class="py-sm text-sm font-semibold text-right text-on-surface">
                        Rp {{ number_format($group->sum('balance'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
    @endforeach

</div>
</x-app-layout>



