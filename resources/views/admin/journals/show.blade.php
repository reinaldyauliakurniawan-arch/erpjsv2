<x-app-layout>
<x-slot name="title">Detail Jurnal</x-slot>

<div class="p-lg space-y-md">

    {{-- Header --}}
    <div class="flex items-center gap-sm">
        <a href="{{ route('finance.journals.index') }}" class="inline-flex items-center gap-xs text-on-surface-variant hover:text-on-surface">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            <span class="text-sm">Kembali</span>
        </a>
    </div>

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Detail Jurnal</h1>
            <p class="text-sm text-on-surface-variant mt-xs">{{ $journal->reference }}</p>
        </div>



        @if(!$alreadyReversed && !str_starts_with($journal->reference, 'REV-'))
        <form method="POST" action="{{ route('finance.journals.reverse', $journal) }}"
            onsubmit="return confirm('Buat jurnal pembalik untuk entri ini?')">
            @csrf
            <button type="submit" class="btn btn-sm btn-error btn-soft border-none">
                <span class="material-symbols-outlined text-base">undo</span>
                Reverse Jurnal
            </button>
        </form>
        @elseif($alreadyReversed)
        <span class="badge badge-soft badge-warning">Sudah Di-reverse</span>
        @endif
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="alert alert-success alert-soft">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Info Jurnal --}}
    <div class="app-card">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Informasi Jurnal</h2>
        <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
            <div>
                <p class="text-xs text-on-surface-variant">Tanggal</p>
                <p class="text-sm font-medium text-on-surface mt-xs">
                    {{ \Carbon\Carbon::parse($journal->date)->isoFormat('D MMMM YYYY') }}
                </p>
            </div>
            <div>
                <p class="text-xs text-on-surface-variant">Referensi</p>
                <p class="text-sm font-medium text-on-surface mt-xs">{{ $journal->reference }}</p>
            </div>
            <div>
                <p class="text-xs text-on-surface-variant">Deskripsi</p>
                <p class="text-sm font-medium text-on-surface mt-xs">{{ $journal->description }}</p>
            </div>
        </div>
    </div>

    {{-- Entri Jurnal --}}
    <div class="app-card">
        <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide mb-md">Entri Jurnal</h2>

        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Kode</th>
                    <th class="text-left font-semibold py-sm">Nama Akun</th>
                    <th class="text-right font-semibold py-sm">Debit</th>
                    <th class="text-right font-semibold py-sm">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($journal->items as $item)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm font-mono text-on-surface-variant">{{ $item->account->code }}</td>
                    <td class="py-sm text-sm text-on-surface">{{ $item->account->name }}</td>
                    <td class="py-sm text-sm text-right text-on-surface">
                        @if($item->debit > 0)
                            Rp {{ number_format($item->debit, 0, ',', '.') }}
                        @else
                            <span class="text-on-surface-variant">—</span>
                        @endif
                    </td>
                    <td class="py-sm text-sm text-right text-on-surface">
                        @if($item->credit > 0)
                            Rp {{ number_format($item->credit, 0, ',', '.') }}
                        @else
                            <span class="text-on-surface-variant">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border">
                    <td colspan="2" class="py-sm text-sm font-semibold text-on-surface">Total</td>
                    <td class="py-sm text-sm font-semibold text-right text-on-surface">
                        Rp {{ number_format($journal->items->sum('debit'), 0, ',', '.') }}
                    </td>
                    <td class="py-sm text-sm font-semibold text-right text-on-surface">
                        Rp {{ number_format($journal->items->sum('credit'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Meta --}}
    <p class="text-xs text-on-surface-variant text-right">
        Dibuat: {{ $journal->created_at->isoFormat('D MMM YYYY, HH:mm') }}
    </p>

</div>
</x-app-layout>
