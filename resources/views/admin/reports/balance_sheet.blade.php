<x-app-layout>
<x-slot name="title">Balance Sheet</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Balance Sheet</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Posisi keuangan per tanggal tertentu</p>
        </div>
        <div class="flex gap-sm">
            <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">
                <span class="material-symbols-outlined text-base">download</span>
                Export CSV
            </button>
            <button type="button" onclick="document.getElementById('modal-saldo-awal').showModal()" class="btn btn-sm btn-ghost border border-surface-border">
                <span class="material-symbols-outlined text-base">edit_note</span>
                Saldo Awal
            </button>
        </div>
    </div>

    {{-- Filter --}}
    <div class="app-card">
        <form method="GET" action="{{ route('finance.reports.balance-sheet') }}" class="flex items-end gap-md flex-wrap">
            <div class="fieldset">
                <label class="fieldset-legend">Per Tanggal</label>
                <input type="date" name="as_of" class="input input-sm" value="{{ $asOf }}">
            </div>
            <button type="submit" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    @php $isBalance = abs($totalAsset - ($totalLiability + $totalEquity)) < 1; @endphp
    <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Aset</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalAsset, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Liabilitas</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalLiability, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Ekuitas</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totalEquity, 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Status</p>
            <p class="text-headline-md font-bold mt-xs {{ $isBalance ?'text-success' : 'text-error' }}">
                {{ $isBalance ? 'Balance ✓' : 'Tidak Balance ✗' }}
            </p>
            @if(!$isBalance)
            <p class="text-xs text-error mt-xs">Selisih: Rp {{ number_format(abs($totalAsset - ($totalLiability + $totalEquity)), 0, ',', '.') }}</p>
            @endif
        </div>
    </div>

    <div class="app-card">
        <div id="bs-table"></div>
    </div>

</div>

<script>
const bsData = @json($rows->values());

function fmt(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
}

document.addEventListener('DOMContentLoaded', function() {
    new Tabulator("#bs-table", {
        data: bsData,
        layout: "fitColumns",
        pagination: "local",
        paginationSize: 30,
        initialSort: [{ column: "type", dir: "asc" }],
        columns: [
            { title: "Tipe", field: "type", width: 110,
              formatter: cell => `<span class="badge badge-soft text-xs">${cell.getValue()}</span>` },
            { title: "Kode", field: "code", width: 100, headerFilter: "input" },
            { title: "Nama Akun", field: "name", minWidth: 200, headerFilter: "input" },
            { title: "Saldo", field: "balance", hozAlign: "right", formatter: cell => fmt(cell.getValue()) },
        ],
    });
});

function exportCSV() {
    const asOf = '{{ $asOf }}';
    window.location.href = `{{ route("finance.exports.balance-sheet") }}?as_of=${asOf}`;
}
</script>

<dialog id="modal-saldo-awal" class="modal">
    <div class="modal-box max-w-2xl">
        <h3 class="text-lg font-semibold text-on-surface mb-md">Input Saldo Awal</h3>
        <p class="text-sm text-on-surface-variant mb-md">Masukkan saldo awal per akun. Kosongkan jika tidak ada saldo awal.</p>

        <form method="POST" action="{{ route('finance.opening-balance.store') }}">
            @csrf
            <div class="space-y-xs max-h-96 overflow-y-auto pr-sm">
                @foreach($accounts as $account)
                <div class="grid items-center gap-sm" style="grid-template-columns: 3fr 1fr 1fr;">
                    <div>
                        <span class="text-xs font-mono text-on-surface-variant">{{ $account->code }}</span>
                        <span class="text-sm text-on-surface ml-xs">{{ $account->name }}</span>
                        <span class="badge badge-soft text-xs ml-xs">{{ $account->type }}</span>
                    </div>
                    <input type="number" name="balances[{{ $account->id }}][debit]"
                        class="input input-sm w-full text-right" placeholder="Debit" min="0"
                        value="{{ $obBalances->get($account->id)?->debit ?? 0 }}">
                    <input type="number" name="balances[{{ $account->id }}][credit]"
                        class="input input-sm w-full text-right" placeholder="Kredit" min="0"
                        value="{{ $obBalances->get($account->id)?->credit ?? 0 }}">
                </div>
                @endforeach
            </div>

            <div class="mt-md text-xs text-on-surface-variant">
                Asset & Expense → isi di kolom Debit. Liability, Equity, Revenue → isi di kolom Kredit.
            </div>

            <div class="modal-action">
                <button type="button" onclick="document.getElementById('modal-saldo-awal').close()" class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn bg-primary-container text-on-primary border-none">Simpan</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>
</x-app-layout>
