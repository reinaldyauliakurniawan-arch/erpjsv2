<x-app-layout>
<x-slot name="title">Laporan Perubahan Ekuitas</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Laporan Perubahan Ekuitas</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Modal Awal + Laba Bersih − Prive = Modal Akhir</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <form method="GET" action="{{ route('finance.reports.equity-statement') }}" class="flex items-end gap-md">
            <div class="fieldset">
                <label class="fieldset-legend">Tahun</label>
                <select name="year" class="select select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm bg-primary-container text-on-primary border-none">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filter
            </button>
        </form>
    </div>

    {{-- Cards --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(4, 1fr)">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Modal Awal</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($modalAwal, 0, ',', '.') }}</p>
            <p class="text-xs text-on-surface-variant mt-xs">Per 31 Des {{ $year - 1 }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Laba Bersih</p>
            <p class="text-xl font-bold mt-xs {{ $labaBersih >= 0 ? 'text-success' : 'text-error' }}">
                Rp {{ number_format($labaBersih, 0, ',', '.') }}
            </p>
            <p class="text-xs text-on-surface-variant mt-xs">Tahun {{ $year }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Prive / Drawing</p>
            <p class="text-xl font-bold text-warning mt-xs">Rp {{ number_format($prive, 0, ',', '.') }}</p>
            <p class="text-xs text-on-surface-variant mt-xs">Tahun {{ $year }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg border-2 border-primary">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Modal Akhir</p>
            <p class="text-xl font-bold text-primary mt-xs">Rp {{ number_format($modalAkhir, 0, ',', '.') }}</p>
            <p class="text-xs text-on-surface-variant mt-xs">Per 31 Des {{ $year }}</p>
        </div>
    </div>

    {{-- Detail --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
        <h2 class="text-sm font-semibold text-on-surface mb-md">Rincian Perubahan Ekuitas Tahun {{ $year }}</h2>
        <table class="w-full text-sm">
            <tbody>
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-on-surface">Modal Awal (Per 31 Des {{ $year - 1 }})</td>
                    <td class="py-sm text-right font-mono text-on-surface">Rp {{ number_format($modalAwal, 0, ',', '.') }}</td>
                </tr>
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-on-surface pl-md">+ Laba Bersih Tahun {{ $year }}</td>
                    <td class="py-sm text-right font-mono {{ $labaBersih >= 0 ? 'text-success' : 'text-error' }}">
                        {{ $labaBersih >= 0 ? '+' : '' }}Rp {{ number_format($labaBersih, 0, ',', '.') }}
                    </td>
                </tr>
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-on-surface pl-md">− Prive / Drawing</td>
                    <td class="py-sm text-right font-mono text-warning">
                        {{ $prive > 0 ? '−' : '' }}Rp {{ number_format($prive, 0, ',', '.') }}
                    </td>
                </tr>
                <tr class="bg-surface-container">
                    <td class="py-sm font-semibold text-on-surface">Modal Akhir (Per 31 Des {{ $year }})</td>
                    <td class="py-sm text-right font-mono font-bold text-primary">Rp {{ number_format($modalAkhir, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
</x-app-layout>
