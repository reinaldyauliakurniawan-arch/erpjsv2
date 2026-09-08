<x-app-layout>
    <x-slot name="title">Finance Imports</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 56rem">

        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <h3 class="text-headline-lg font-semibold text-on-surface">Finance Imports</h3>

        {{-- Import COA --}}
        <div class="app-card space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Chart of Accounts (COA)</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: code, name, type (Asset/Liability/Equity/Revenue/Expense)</p>
                    <p class="text-body-sm text-on-surface-variant mt-xs">Baris yang bermasalah dilaporkan satu per satu (mis. tipe akun tak dikenal, atau kode yang sudah dipakai akun lain yang sudah ada transaksinya). Baris yang benar tetap masuk; baris yang salah dilewati dengan alasannya.</p>
                </div>
                <a href="{{ route('finance.exports.coa') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Export
                </a>
            </div>
            <form method="POST" action="{{ route('finance.imports.coa') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

        {{-- Import Journals --}}
        <div class="app-card space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Journals</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: date, description, reference, account_code, debit, credit</p>
                    <p class="text-body-sm text-on-surface-variant mt-xs">Tiap jurnal (dikelompokkan per reference) dicek: tanggal masuk akal, kode akun ada, debit = kredit. Jurnal yang gagal dilaporkan lengkap dengan alasannya (mis. selisih debit-kredit sekian, kode akun mana yang tidak ketemu); reference yang sudah ada dilewati.</p>
                </div>
                <a href="{{ route('finance.exports.journals') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Export
                </a>
            </div>
            <form method="POST" action="{{ route('finance.imports.journals') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

    </div>
</x-app-layout>
