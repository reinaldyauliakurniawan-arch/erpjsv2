<x-app-layout>
<x-slot name="title">Trial Balance</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Trial Balance</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Saldo semua akun dari seluruh jurnal</p>
        </div>
        <a href="{{ route('finance.exports.journals') }}" class="btn btn-sm btn-ghost border border-surface-border">
            <span class="material-symbols-outlined text-base">download</span>
            Export
        </a>
    </div>

    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        @if($rows->isEmpty())
        <div class="text-center py-xl text-on-surface-variant">
            <span class="material-symbols-outlined text-4xl">balance</span>
            <p class="mt-sm text-sm">Belum ada data jurnal</p>
        </div>
        @else
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Kode</th>
                    <th class="text-left font-semibold py-sm">Nama Akun</th>
                    <th class="text-left font-semibold py-sm">Tipe</th>
                    <th class="text-right font-semibold py-sm">Debit</th>
                    <th class="text-right font-semibold py-sm">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm font-mono text-on-surface-variant">{{ $row->code }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $row->name }}</td>
                    <td class="py-sm">
                        <span class="badge badge-soft text-xs">{{ $row->type }}</span>
                    </td>
                    <td class="py-sm text-sm text-right text-on-surface">
                        @if($row->debit > 0)
                            Rp {{ number_format($row->debit, 0, ',', '.') }}
                        @else
                            <span class="text-on-surface-variant">—</span>
                        @endif
                    </td>
                    <td class="py-sm text-sm text-right text-on-surface">
                        @if($row->credit > 0)
                            Rp {{ number_format($row->credit, 0, ',', '.') }}
                        @else
                            <span class="text-on-surface-variant">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border">
                    <td colspan="3" class="py-sm text-sm font-semibold text-on-surface">Total</td>
                    <td class="py-sm text-sm font-semibold text-right text-on-surface">
                        Rp {{ number_format($totalDebit, 0, ',', '.') }}
                    </td>
                    <td class="py-sm text-sm font-semibold text-right text-on-surface">
                        Rp {{ number_format($totalCredit, 0, ',', '.') }}
                    </td>
                </tr>
                @if(abs($totalDebit - $totalCredit) > 0.001)
                <tr>
                    <td colspan="5" class="py-sm">
                        <span class="flex items-center gap-xs text-sm text-error">
                            <span class="material-symbols-outlined text-base">error</span>
                            Tidak balance — selisih Rp {{ number_format(abs($totalDebit - $totalCredit), 0, ',', '.') }}
                        </span>
                    </td>
                </tr>
                @else
                <tr>
                    <td colspan="5" class="py-sm">
                        <span class="flex items-center gap-xs text-sm text-success">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            Balance
                        </span>
                    </td>
                </tr>
                @endif
            </tfoot>
        </table>
        @endif
    </div>

</div>
</x-app-layout>



