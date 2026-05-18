<x-app-layout>
<x-slot name="title">Profit & Loss</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Profit & Loss</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan dan beban dalam periode tertentu</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <form method="GET" action="{{ route('finance.reports.profit-loss') }}" class="flex items-end gap-md">
            <div class="fieldset">
                <label class="fieldset-legend">Dari</label>
                <input type="date" name="from" class="input w-full" value="{{ $from }}">
            </div>
            <div class="fieldset">
                <label class="fieldset-legend">Sampai</label>
                <input type="date" name="to" class="input w-full" value="{{ $to }}">
            </div>
            <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Pendapatan</p>
            <p class="text-xl font-bold text-success mt-xs">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Beban</p>
            <p class="text-xl font-bold text-error mt-xs">Rp {{ number_format($totalExpense, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Net Profit</p>
            <p class="text-xl font-bold mt-xs {{ $netProfit >= 0 ? 'text-success' : 'text-error' }}">
                Rp {{ number_format($netProfit, 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Revenue --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Pendapatan</h2>
        @php $revenues = $rows->where('type', 'Revenue'); @endphp
        @if($revenues->isEmpty())
            <p class="text-sm text-on-surface-variant">Tidak ada data pendapatan</p>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Kode</th>
                    <th class="text-left font-semibold py-sm">Nama Akun</th>
                    <th class="text-right font-semibold py-sm">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($revenues as $row)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm font-mono text-on-surface-variant">{{ $row->code }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $row->name }}</td>
                    <td class="py-sm text-sm text-right text-on-surface">Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border">
                    <td colspan="2" class="py-sm text-sm font-semibold text-on-surface">Total Pendapatan</td>
                    <td class="py-sm text-sm font-semibold text-right text-success">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

    {{-- Expense --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Beban</h2>
        @php $expenses = $rows->where('type', 'Expense'); @endphp
        @if($expenses->isEmpty())
            <p class="text-sm text-on-surface-variant">Tidak ada data beban</p>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Kode</th>
                    <th class="text-left font-semibold py-sm">Nama Akun</th>
                    <th class="text-right font-semibold py-sm">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenses as $row)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm font-mono text-on-surface-variant">{{ $row->code }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $row->name }}</td>
                    <td class="py-sm text-sm text-right text-on-surface">Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border">
                    <td colspan="2" class="py-sm text-sm font-semibold text-on-surface">Total Beban</td>
                    <td class="py-sm text-sm font-semibold text-right text-error">Rp {{ number_format($totalExpense, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

</div>
</x-app-layout>



