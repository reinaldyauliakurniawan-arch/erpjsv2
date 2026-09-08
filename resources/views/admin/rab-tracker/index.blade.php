<x-app-layout>
<x-slot name="title">Tracker RAB</x-slot>

<div class="p-lg space-y-md" x-data="rabTracker()">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-md flex-wrap">
        <div>
            <h3 class="text-headline-lg font-semibold text-on-surface">Tracker RAB {{ $year }}</h3>
            <p class="text-sm text-on-surface-variant mt-xs">Anggaran vs realisasi per bulan, per akun beban. Isi realisasi bulanan langsung di tabel.</p>
        </div>
        <div class="flex gap-sm items-center flex-wrap">
            <form method="GET" class="flex gap-sm items-center">
                <select name="year" class="select select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('finance.rab.index') }}" class="btn btn-ghost btn-sm gap-xs">
                <span class="material-symbols-outlined text-[16px]">edit</span> Edit Anggaran
            </a>
            <button type="button" class="btn btn-ghost btn-sm gap-xs" @click="syncFromJournals()" x-bind:disabled="busy">
                <span class="material-symbols-outlined text-[16px]">sync</span> Tarik dari Jurnal
            </button>
            <button type="button" class="btn btn-sm bg-secondary text-on-secondary border-none gap-xs"
                @click="save()" x-bind:disabled="busy || !dirty">
                <span class="material-symbols-outlined text-[16px]">save</span>
                <span x-text="dirty ? 'Simpan Perubahan' : 'Tersimpan'"></span>
            </button>
        </div>
    </div>

    <div x-show="flash" x-transition class="alert alert-success alert-soft" role="alert">
        <span class="material-symbols-outlined">check_circle</span><span x-text="flash"></span>
    </div>

    {{-- Summary --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(4, 1fr)">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Anggaran Tahunan</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totals['budget_total'], 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Terpakai</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs" x-text="'Rp ' + fmt(grandReal())"></p>
            <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden mt-sm">
                <div class="h-full rounded-full" x-bind:class="pctClass(grandPct())" x-bind:style="`width:${Math.min(grandPct(),100)}%`"></div>
            </div>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">% Terpakai</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs" x-text="grandPct() + '%'"></p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Sisa Anggaran</p>
            <p class="text-headline-md font-bold mt-xs" x-bind:class="grandSisa() < 0 ? 'text-error' : 'text-success'"
               x-text="'Rp ' + fmt(grandSisa())"></p>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="app-card overflow-x-auto">
        @if($rows->isEmpty())
            <div class="py-12 text-center text-on-surface-variant text-sm">
                Belum ada anggaran untuk {{ $year }}.
                <a href="{{ route('finance.rab.index') }}" class="text-primary underline">Input RAB dulu.</a>
            </div>
        @else
        <table class="table table-xs w-full whitespace-nowrap">
            <thead>
                <tr class="text-on-surface-variant border-b border-surface-border">
                    <th class="text-left sticky left-0 bg-surface-container-lowest z-10">Akun</th>
                    <th class="text-right">Anggaran</th>
                    @foreach($monthNames as $mn)
                        <th class="text-right">{{ $mn }}</th>
                    @endforeach
                    <th class="text-right">Realisasi</th>
                    <th class="text-right">%</th>
                    <th class="text-right">Sisa</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in rows" :key="row.account_code">
                    <tr class="border-b border-surface-border">
                        <td class="text-left sticky left-0 bg-surface-container-lowest z-10">
                            <span class="text-on-surface" x-text="row.account_name"></span>
                            <span class="block text-[10px] text-on-surface-variant" x-text="row.division + ' · ' + row.account_code"></span>
                        </td>
                        <td class="text-right text-on-surface-variant" x-text="fmt(row.budget_total)"></td>
                        <template x-for="m in 12" :key="m">
                            <td class="text-right p-0">
                                <input type="text" inputmode="numeric"
                                    class="input input-xs w-24 text-right bg-transparent border-transparent hover:border-surface-border focus:border-primary"
                                    x-bind:value="display(row.months[m])"
                                    x-on:input="setCell(row, m, $event.target.value)"
                                    x-on:focus="$event.target.select()">
                            </td>
                        </template>
                        <td class="text-right font-semibold text-on-surface" x-text="fmt(rowReal(row))"></td>
                        <td class="text-right" x-bind:class="pctText(rowPct(row))" x-text="rowPct(row) + '%'"></td>
                        <td class="text-right" x-bind:class="rowSisa(row) < 0 ? 'text-error' : 'text-on-surface-variant'" x-text="fmt(rowSisa(row))"></td>
                        <td class="text-center"><span class="badge badge-soft badge-xs" x-bind:class="statusClass(rowPct(row))" x-text="statusLabel(rowPct(row))"></span></td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border font-bold text-on-surface">
                    <td class="text-left sticky left-0 bg-surface-container-lowest z-10">Total Beban</td>
                    <td class="text-right" x-text="fmt({{ $totals['budget_total'] }})"></td>
                    <template x-for="m in 12" :key="m">
                        <td class="text-right" x-text="fmt(colTotal(m))"></td>
                    </template>
                    <td class="text-right" x-text="fmt(grandReal())"></td>
                    <td class="text-right" x-text="grandPct() + '%'"></td>
                    <td class="text-right" x-bind:class="grandSisa() < 0 ? 'text-error' : ''" x-text="fmt(grandSisa())"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

    {{-- Ringkasan Laba-Rugi per bulan --}}
    <div class="app-card overflow-x-auto">
        <h4 class="text-title-sm font-semibold text-on-surface mb-sm">Ringkasan Laba–Rugi per Bulan</h4>
        <p class="text-xs text-on-surface-variant mb-md">
            Pendapatan bisa diisi tangan (mengikuti tracker CFO); kalau kosong, diambil dari jurnal keuangan.
            Beban dari tracker di atas.
        </p>
        <table class="table table-xs w-full whitespace-nowrap">
            <thead>
                <tr class="text-on-surface-variant border-b border-surface-border">
                    <th class="text-left">Bulan</th>
                    <th class="text-right">Pendapatan</th>
                    <th class="text-right">Beban (RAB)</th>
                    <th class="text-right">Laba / Rugi</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="m in 12" :key="m">
                    <tr class="border-b border-surface-border">
                        <td class="text-left text-on-surface" x-text="monthName(m)"></td>
                        <td class="text-right p-0">
                            <input type="text" inputmode="numeric"
                                class="input input-xs w-32 text-right bg-transparent border-transparent hover:border-surface-border focus:border-primary"
                                x-bind:value="display(revenue[m])"
                                x-on:input="setRevenue(m, $event.target.value)"
                                x-on:focus="$event.target.select()">
                        </td>
                        <td class="text-right" x-text="fmt(colTotal(m))"></td>
                        <td class="text-right font-semibold"
                            x-bind:class="(revenue[m] - colTotal(m)) < 0 ? 'text-error' : 'text-success'"
                            x-text="fmt(revenue[m] - colTotal(m))"></td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border font-bold text-on-surface">
                    <td class="text-left">Total</td>
                    <td class="text-right" x-text="fmt(sumRevenue())"></td>
                    <td class="text-right" x-text="fmt(grandReal())"></td>
                    <td class="text-right" x-bind:class="(sumRevenue() - grandReal()) < 0 ? 'text-error' : 'text-success'"
                        x-text="fmt(sumRevenue() - grandReal())"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
function rabTracker() {
    return {
        rows: @json($rows),
        revenue: @json($revenueRow),
        monthNames: @json($monthNames),
        dirty: false,
        busy: false,
        flash: '',
        year: {{ $year }},

        monthName(m) { return this.monthNames[m - 1]; },
        display(v) { return v ? new Intl.NumberFormat('id-ID').format(v) : ''; },
        fmt(v) { v = Math.round(v || 0); return (v < 0 ? '-' : '') + 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(v)); },

        setCell(row, m, raw) {
            const n = parseInt(String(raw).replace(/[^\d]/g, ''), 10) || 0;
            if (row.months[m] === n) return;
            row.months[m] = n;
            this.dirty = true;
        },

        setRevenue(m, raw) {
            const n = parseInt(String(raw).replace(/[^\d]/g, ''), 10) || 0;
            if (this.revenue[m] === n) return;
            this.revenue[m] = n;
            this.dirty = true;
        },
        sumRevenue() { let t = 0; for (let m = 1; m <= 12; m++) t += (this.revenue[m] || 0); return t; },

        rowReal(row) { let t = 0; for (let m = 1; m <= 12; m++) t += (row.months[m] || 0); return t; },
        rowPct(row)  { return row.budget_total > 0 ? Math.round(this.rowReal(row) / row.budget_total * 1000) / 10 : 0; },
        rowSisa(row) { return row.budget_total - this.rowReal(row); },
        colTotal(m)  { return this.rows.reduce((s, r) => s + (r.months[m] || 0), 0); },
        grandReal()  { return this.rows.reduce((s, r) => s + this.rowReal(r), 0); },
        grandBudget(){ return this.rows.reduce((s, r) => s + r.budget_total, 0); },
        grandPct()   { const b = this.grandBudget(); return b > 0 ? Math.round(this.grandReal() / b * 1000) / 10 : 0; },
        grandSisa()  { return this.grandBudget() - this.grandReal(); },

        statusLabel(p) { return p >= 100 ? 'Lewat' : p >= 95 ? 'Kritis' : p >= 80 ? 'Waspada' : 'Aman'; },
        statusClass(p) { return p >= 95 ? 'badge-error' : p >= 80 ? 'badge-warning' : 'badge-success'; },
        pctText(p)     { return p >= 100 ? 'text-error font-semibold' : p >= 80 ? 'text-warning' : 'text-on-surface-variant'; },
        pctClass(p)    { return p >= 95 ? 'bg-error' : p >= 80 ? 'bg-warning' : 'bg-success'; },

        payload() {
            const out = [];
            this.rows.forEach(r => {
                for (let m = 1; m <= 12; m++) out.push({ account_code: r.account_code, month: m, amount: r.months[m] || 0 });
            });
            for (let m = 1; m <= 12; m++) out.push({ account_code: '__REVENUE__', month: m, amount: this.revenue[m] || 0 });
            return out;
        },

        async post(url, body) {
            this.busy = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan.');
                this.flash = data.message;
                setTimeout(() => this.flash = '', 3000);
                return true;
            } catch (e) {
                alert(e.message);
                return false;
            } finally {
                this.busy = false;
            }
        },

        async save() {
            if (await this.post('{{ route('finance.rab-tracker.actuals') }}', { year: this.year, actuals: this.payload() })) {
                this.dirty = false;
            }
        },

        async syncFromJournals() {
            if (!confirm('Tarik realisasi dari jurnal keuangan? Angka bulanan yang sudah diisi tangan akan ditimpa untuk akun yang ada jurnalnya.')) return;
            if (await this.post('{{ route('finance.rab-tracker.sync-journals') }}', { year: this.year })) {
                location.reload();
            }
        },
    };
}
</script>
</x-app-layout>
