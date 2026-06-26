<x-app-layout>
    <x-slot name="title">Laporan Keuangan</x-slot>

    <div class="p-lg space-y-lg">

        {{-- Header --}}
        <div class="app-page-header">
            <h1 class="app-page-header__title">Laporan Keuangan</h1>
            <p class="app-page-header__subtitle">Ringkasan saldo semua akun berdasarkan jurnal yang telah diposting.</p>
        </div>

        {{-- Quick links to individual reports --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-md">
            <a href="{{ route('finance.reports.general-ledger') }}" class="app-card flex items-center gap-md hover:bg-surface-container-low transition-colors">
                <div class="app-icon-badge"><span class="material-symbols-outlined">menu_book</span></div>
                <div>
                    <p class="font-semibold text-on-surface">General Ledger</p>
                    <p class="text-body-sm text-on-surface-variant">Detail transaksi per akun</p>
                </div>
            </a>
            <a href="{{ route('finance.reports.trial-balance') }}" class="app-card flex items-center gap-md hover:bg-surface-container-low transition-colors">
                <div class="app-icon-badge"><span class="material-symbols-outlined">balance</span></div>
                <div>
                    <p class="font-semibold text-on-surface">Trial Balance</p>
                    <p class="text-body-sm text-on-surface-variant">Saldo semua akun</p>
                </div>
            </a>
            <a href="{{ route('finance.reports.profit-loss') }}" class="app-card flex items-center gap-md hover:bg-surface-container-low transition-colors">
                <div class="app-icon-badge"><span class="material-symbols-outlined">trending_up</span></div>
                <div>
                    <p class="font-semibold text-on-surface">Profit &amp; Loss</p>
                    <p class="text-body-sm text-on-surface-variant">Pendapatan vs beban</p>
                </div>
            </a>
            <a href="{{ route('finance.reports.balance-sheet') }}" class="app-card flex items-center gap-md hover:bg-surface-container-low transition-colors">
                <div class="app-icon-badge"><span class="material-symbols-outlined">summarize</span></div>
                <div>
                    <p class="font-semibold text-on-surface">Balance Sheet</p>
                    <p class="text-body-sm text-on-surface-variant">Posisi keuangan</p>
                </div>
            </a>
            <a href="{{ route('finance.reports.cash-flow') }}" class="app-card flex items-center gap-md hover:bg-surface-container-low transition-colors">
                <div class="app-icon-badge"><span class="material-symbols-outlined">water</span></div>
                <div>
                    <p class="font-semibold text-on-surface">Cash Flow</p>
                    <p class="text-body-sm text-on-surface-variant">Arus kas</p>
                </div>
            </a>
            <a href="{{ route('finance.reports.equity-statement') }}" class="app-card flex items-center gap-md hover:bg-surface-container-low transition-colors">
                <div class="app-icon-badge"><span class="material-symbols-outlined">change_history</span></div>
                <div>
                    <p class="font-semibold text-on-surface">Perubahan Ekuitas</p>
                    <p class="text-body-sm text-on-surface-variant">Modal awal dan akhir</p>
                </div>
            </a>
        </div>

        {{-- Account balances summary --}}
        <div>
            <h2 class="text-headline-md font-semibold text-on-surface mb-md">Saldo Akun</h2>
            <div class="app-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Akun</th>
                            <th>Tipe</th>
                            <th class="text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalDebit = 0; $totalCredit = 0; @endphp
                        @foreach($accounts as $account)
                            @php
                                $balance = $balances[$account->code] ?? 0;
                                if (in_array($account->type, ['Asset', 'Expense'])) {
                                    $totalDebit += abs($balance);
                                } else {
                                    $totalCredit += abs($balance);
                                }
                            @endphp
                            <tr>
                                <td class="font-mono">{{ $account->code }}</td>
                                <td>{{ $account->name }}</td>
                                <td><span class="badge badge-soft">{{ $account->type }}</span></td>
                                <td class="text-right font-mono {{ $balance < 0 ? 'text-error' : 'text-on-surface' }}">
                                    Rp {{ number_format(abs($balance), 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color: var(--color-surface-container-low); font-weight: 600;">
                            <td colspan="3" class="text-right">Total Debit:</td>
                            <td class="text-right font-mono">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td>
                        </tr>
                        <tr style="background-color: var(--color-surface-container-low); font-weight: 600;">
                            <td colspan="3" class="text-right">Total Credit:</td>
                            <td class="text-right font-mono">Rp {{ number_format($totalCredit, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>
