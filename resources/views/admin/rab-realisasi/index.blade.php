<x-app-layout>
<x-slot name="title">Realisasi RAB</x-slot>

<div class="p-lg space-y-lg" x-data="rabRealisasi()">

    {{-- ═══ Header ═══ --}}
    <div class="flex items-start justify-between gap-md flex-wrap">
        <div>
            <h3 class="text-headline-lg font-semibold text-on-surface">Realisasi RAB {{ $year }}</h3>
            <p class="text-sm text-on-surface-variant mt-xs">
                Anggaran vs realisasi — dipantau terhadap <span class="font-medium">rencana sampai bulan berjalan</span>,
                bukan hanya total tahunan.
                @if($monthsElapsed > 0 && $monthsElapsed < 12)
                    <span class="text-on-surface">Per akhir {{ $monthNames[$monthsElapsed - 1] }} ({{ round($monthsElapsed / 12 * 100) }}% tahun berjalan).</span>
                @endif
            </p>
        </div>
        <div class="flex gap-sm items-center flex-wrap">
            <form method="GET" class="flex gap-sm items-center">
                <select name="year" class="select select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
                </select>
            </form>
            <a href="{{ route('finance.rab.index') }}" class="btn btn-ghost btn-sm gap-xs">
                <span class="material-symbols-outlined text-[16px]">edit</span> Edit Anggaran
            </a>
            <button type="button" class="btn btn-ghost btn-sm gap-xs" @click="syncFromJournals()" x-bind:disabled="busy"
                title="Hapus semua angka yang pernah ditimpa manual, kembali sepenuhnya ke angka pembukuan.">
                <span class="material-symbols-outlined text-[16px]">restart_alt</span> Kembalikan ke angka jurnal
            </button>
            <button type="button" class="btn btn-sm bg-secondary text-on-secondary border-none gap-xs"
                @click="save()" x-bind:disabled="busy || !dirty">
                <span class="material-symbols-outlined text-[16px]">save</span>
                <span x-text="dirty ? 'Simpan' : 'Tersimpan'"></span>
            </button>
        </div>
    </div>

    <p class="text-xs text-on-surface-variant -mt-sm">
        Angka realisasi diambil otomatis dari pembukuan. Anda hanya perlu mengetik ulang sebuah sel
        kalau ingin menimpanya (mis. penyesuaian kas ke akrual yang belum masuk jurnal).
    </p>

    <div x-show="flash" x-transition class="alert alert-success alert-soft" role="alert">
        <span class="material-symbols-outlined">check_circle</span><span x-text="flash"></span>
    </div>

    @if($rows->isEmpty())
        <div class="app-card py-16 text-center text-on-surface-variant">
            <span class="material-symbols-outlined text-5xl opacity-20 mb-sm">monitoring</span>
            <p class="text-sm">Belum ada anggaran untuk {{ $year }}.
                <a href="{{ route('finance.rab.index') }}" class="text-primary underline">Input RAB dulu.</a></p>
        </div>
    @else

    {{-- ═══ KPI utama ═══ --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(4, minmax(0,1fr))">
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Anggaran Tahunan</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs">Rp {{ number_format($totals['budget_total'], 0, ',', '.') }}</p>
            <p class="text-xs text-on-surface-variant mt-xs">RAB {{ $year - 1 }}: Rp {{ number_format($rows->sum('rab_prev'), 0, ',', '.') }}</p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Realisasi s/d Kini</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs" x-text="'Rp ' + fmt(grandRealYtd())"></p>
            <p class="text-xs mt-xs" x-bind:class="grandVariance() > 0 ? 'text-error' : 'text-success'">
                <span x-text="grandVariance() > 0 ? 'Boros ' : 'Hemat '"></span>
                <span x-text="'Rp ' + fmt(Math.abs(grandVariance()))"></span>
                <span class="text-on-surface-variant" x-text="'vs rencana Rp ' + fmt({{ $totals['budget_to_date'] }})"></span>
            </p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Serapan vs Rencana</p>
            <p class="text-headline-md font-bold mt-xs" x-bind:class="pctText(grandPace())"
               x-text="(grandPace() === null ? '—' : grandPace() + '%')"></p>
            <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden mt-sm">
                <div class="h-full rounded-full transition-all" x-bind:class="barClass(grandPace())"
                     x-bind:style="`width:${Math.min(grandPace() || 0, 100)}%`"></div>
            </div>
            <p class="text-xs text-on-surface-variant mt-xs" x-text="'Serapan tahunan: ' + grandAbsorption() + '%'"></p>
        </div>
        <div class="app-card">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Proyeksi Akhir Tahun</p>
            <p class="text-headline-md font-bold text-on-surface mt-xs"
               x-text="grandForecast() === null ? '—' : 'Rp ' + fmt(grandForecast())"></p>
            <p class="text-xs mt-xs" x-show="grandForecast() !== null"
               x-bind:class="grandForecastVar() > 0 ? 'text-error' : 'text-success'"
               x-text="(grandForecastVar() > 0 ? 'Prediksi lewat anggaran Rp ' : 'Prediksi di bawah anggaran Rp ') + fmt(Math.abs(grandForecastVar()))"></p>
        </div>
    </div>

    {{-- ═══ KPI per kuartal ═══ --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(4, minmax(0,1fr))">
        @foreach($quarters as $q)
            <div class="app-card {{ $loop->iteration == $currentQuarter ? 'ring-1 ring-secondary/40' : '' }}">
                <div class="flex items-center justify-between">
                    <p class="text-title-sm font-semibold text-on-surface">{{ $q['label'] }}</p>
                    @if($loop->iteration == $currentQuarter)<span class="badge badge-soft badge-success badge-xs">berjalan</span>@endif
                </div>
                <dl class="mt-sm space-y-xs text-body-sm">
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">Margin laba</dt>
                        <dd class="font-semibold {{ !is_null($q['profit_pct']) && $q['profit_pct'] < 0 ? 'text-error' : 'text-on-surface' }}">
                            {{ is_null($q['profit_pct']) ? '—' : $q['profit_pct'].'%' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">Pencapaian pendapatan</dt>
                        <dd class="font-semibold {{ !is_null($q['revenue_achievement']) && $q['revenue_achievement'] < 100 ? 'text-warning' : 'text-on-surface' }}">
                            {{ is_null($q['revenue_achievement']) ? '—' : $q['revenue_achievement'].'%' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-on-surface-variant">Serapan anggaran</dt>
                        <dd class="font-semibold {{ !is_null($q['budget_realization']) && $q['budget_realization'] > 100 ? 'text-error' : 'text-on-surface' }}">
                            {{ is_null($q['budget_realization']) ? '—' : $q['budget_realization'].'%' }}
                        </dd>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>

    {{-- ═══ Grafik ═══ --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(2, minmax(0,1fr))">
        <div class="app-card">
            <h4 class="text-title-sm font-semibold text-on-surface">Pendapatan · Beban · Laba per Bulan</h4>
            <div class="mt-md" style="height:280px"><canvas id="chartPL"></canvas></div>
        </div>
        <div class="app-card">
            <h4 class="text-title-sm font-semibold text-on-surface">Anggaran vs Realisasi per Kuartal</h4>
            <div class="mt-md" style="height:280px"><canvas id="chartQuarter"></canvas></div>
        </div>
        <div class="app-card">
            <h4 class="text-title-sm font-semibold text-on-surface">Kurva Serapan Anggaran</h4>
            <p class="text-xs text-on-surface-variant">Realisasi kumulatif vs kurva rencana (mengikuti pembagian anggaran per kuartal).</p>
            <div class="mt-md" style="height:280px"><canvas id="chartAbsorption"></canvas></div>
        </div>
        <div class="app-card">
            <h4 class="text-title-sm font-semibold text-on-surface">Beban per Kategori (Top 10)</h4>
            <div class="mt-md" style="height:280px"><canvas id="chartCategory"></canvas></div>
        </div>
    </div>

    {{-- ═══ Tabs: detail ═══ --}}
    <div class="app-card space-y-md">
        <div class="flex items-center gap-xs border-b border-surface-border">
            <button type="button" class="px-md py-sm text-body-md border-b-2 -mb-px transition-colors"
                x-bind:class="tab === 'month' ? 'border-secondary text-on-surface font-semibold' : 'border-transparent text-on-surface-variant'"
                @click="tab = 'month'">Per Bulan</button>
            <button type="button" class="px-md py-sm text-body-md border-b-2 -mb-px transition-colors"
                x-bind:class="tab === 'quarter' ? 'border-secondary text-on-surface font-semibold' : 'border-transparent text-on-surface-variant'"
                @click="tab = 'quarter'">Per Kuartal</button>
        </div>

        {{-- Detail per bulan (editable) --}}
        <div x-show="tab === 'month'" class="overflow-x-auto">
            <table class="table table-xs w-full whitespace-nowrap">
                <thead>
                    <tr class="text-on-surface-variant border-b border-surface-border">
                        <th class="text-left sticky left-0 bg-surface-container-lowest z-10">Akun</th>
                        <th class="text-right">Anggaran</th>
                        @foreach($monthNames as $mn)<th class="text-right">{{ $mn }}</th>@endforeach
                        <th class="text-right">Realisasi</th>
                        <th class="text-right" title="Selisih realisasi s/d bulan berjalan vs rencana s/d bulan berjalan">Selisih</th>
                        <th class="text-right" title="Realisasi s/d kini ÷ rencana s/d kini">vs Rencana</th>
                        <th class="text-right" title="Proyeksi belanja setahun berdasarkan laju realisasi terkini">Proyeksi</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in rows" :key="row.account_code">
                        <tr class="border-b border-surface-border">
                            <td class="text-left sticky left-0 bg-surface-container-lowest z-10">
                                <span class="text-on-surface" x-text="row.account_name"></span>
                                <span class="block text-[10px] text-on-surface-variant" x-text="row.division"></span>
                            </td>
                            <td class="text-right text-on-surface-variant" x-text="fmt(row.budget_total)"></td>
                            <template x-for="m in 12" :key="m">
                                <td class="text-right p-0">
                                    <input type="text" inputmode="numeric"
                                        class="input input-xs w-24 text-right bg-transparent border-transparent hover:border-surface-border focus:border-primary"
                                        x-bind:class="m > MONTHS_ELAPSED ? 'text-on-surface-variant/60' : ''"
                                        x-bind:value="disp(row.months[m])"
                                        x-on:input="setCell(row, m, $event.target.value)"
                                        x-on:focus="$event.target.select()">
                                </td>
                            </template>
                            <td class="text-right font-semibold text-on-surface" x-text="fmt(rowReal(row))"></td>
                            <td class="text-right" x-bind:class="rowVariance(row) > 0 ? 'text-error' : 'text-success'"
                                x-text="(rowVariance(row) > 0 ? '+' : '') + fmt(rowVariance(row))"></td>
                            <td class="text-right" x-bind:class="pctText(rowPace(row))"
                                x-text="rowPace(row) === null ? '—' : rowPace(row) + '%'"></td>
                            <td class="text-right text-on-surface-variant" x-text="rowForecast(row) === null ? '—' : fmt(rowForecast(row))"></td>
                            <td class="text-center">
                                <span class="badge badge-soft badge-xs" x-bind:class="statusClass(row)"
                                      x-text="statusLabel(row)"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-surface-border font-bold text-on-surface">
                        <td class="text-left sticky left-0 bg-surface-container-lowest z-10">Total Beban</td>
                        <td class="text-right">{{ number_format($totals['budget_total'], 0, ',', '.') }}</td>
                        <template x-for="m in 12" :key="m"><td class="text-right" x-text="fmt(colTotal(m))"></td></template>
                        <td class="text-right" x-text="fmt(grandReal())"></td>
                        <td class="text-right" x-bind:class="grandVariance() > 0 ? 'text-error' : 'text-success'"
                            x-text="(grandVariance() > 0 ? '+' : '') + fmt(grandVariance())"></td>
                        <td class="text-right" x-text="grandPace() === null ? '—' : grandPace() + '%'"></td>
                        <td class="text-right" x-text="grandForecast() === null ? '—' : fmt(grandForecast())"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <p class="text-[11px] text-on-surface-variant mt-sm">
                <span class="text-error">Selisih +</span> = realisasi melebihi rencana sampai bulan ini (boros).
                <span class="text-success">Selisih −</span> = di bawah rencana (hemat).
                Kolom bulan yang belum berjalan berwarna redup.
            </p>
        </div>

        {{-- Ringkasan per kuartal --}}
        <div x-show="tab === 'quarter'" x-cloak class="overflow-x-auto">
            <table class="table table-sm w-full whitespace-nowrap">
                <thead>
                    <tr class="text-on-surface-variant border-b border-surface-border">
                        <th class="text-left">Akun</th>
                        @foreach(['Q1','Q2','Q3','Q4'] as $q)
                            <th class="text-right">{{ $q }} Anggaran</th>
                            <th class="text-right">{{ $q }} Realisasi</th>
                            <th class="text-center">Status</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                    <tr class="border-b border-surface-border">
                        <td class="text-left text-on-surface">{{ $r['account_name'] }}
                            <span class="block text-[10px] text-on-surface-variant">{{ $r['division'] }}</span>
                        </td>
                        @foreach([1,2,3,4] as $q)
                            <td class="text-right text-on-surface-variant">{{ $r["budget_q$q"] ? number_format($r["budget_q$q"],0,',','.') : '—' }}</td>
                            <td class="text-right text-on-surface" x-text="fmt(qReal({{ $r['id'] }}, {{ $q }}))"></td>
                            <td class="text-center">
                                @if($monthsElapsed >= $q * 3 - 2)
                                    <span class="badge badge-soft badge-xs" x-bind:class="qStatusClass({{ $r['id'] }}, {{ $q }}, {{ $r["budget_q$q"] }})"
                                          x-text="qStatusLabel({{ $r['id'] }}, {{ $q }}, {{ $r["budget_q$q"] }})"></span>
                                @else
                                    <span class="text-[10px] text-on-surface-variant">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-surface-border font-bold text-on-surface">
                        <td class="text-left">Total</td>
                        @foreach([1,2,3,4] as $q)
                            <td class="text-right">{{ number_format($totals["budget_q$q"],0,',','.') }}</td>
                            <td class="text-right" x-text="fmt(qGrand({{ $q }}))"></td>
                            <td></td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ═══ Laba–Rugi per bulan ═══ --}}
    <div class="app-card overflow-x-auto">
        <h4 class="text-title-sm font-semibold text-on-surface mb-xs">Ringkasan Laba–Rugi per Bulan</h4>
        <p class="text-xs text-on-surface-variant mb-md">
            Pendapatan &amp; target bisa diisi tangan; kalau pendapatan kosong, diambil dari jurnal keuangan.
            "Beban" di sini = total realisasi RAB (beban operasional) — bukan laba bersih setelah pos non-RAB.
        </p>
        <table class="table table-xs w-full whitespace-nowrap">
            <thead>
                <tr class="text-on-surface-variant border-b border-surface-border">
                    <th class="text-left">Bulan</th>
                    <th class="text-right">Target Pendapatan</th>
                    <th class="text-right">Pendapatan</th>
                    <th class="text-right">Capai</th>
                    <th class="text-right">Beban (RAB)</th>
                    <th class="text-right">Laba / Rugi</th>
                    <th class="text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="m in 12" :key="m">
                    <tr class="border-b border-surface-border">
                        <td class="text-left text-on-surface" x-text="monthNames[m-1]"></td>
                        <td class="text-right p-0">
                            <input type="text" inputmode="numeric"
                                class="input input-xs w-32 text-right bg-transparent border-transparent hover:border-surface-border focus:border-primary text-on-surface-variant"
                                x-bind:value="disp(target[m])" x-on:input="setTarget(m, $event.target.value)" x-on:focus="$event.target.select()">
                        </td>
                        <td class="text-right p-0">
                            <input type="text" inputmode="numeric"
                                class="input input-xs w-32 text-right bg-transparent border-transparent hover:border-surface-border focus:border-primary"
                                x-bind:value="disp(revenue[m])" x-on:input="setRevenue(m, $event.target.value)" x-on:focus="$event.target.select()">
                        </td>
                        <td class="text-right text-on-surface-variant" x-text="target[m] > 0 ? Math.round(revenue[m] / target[m] * 100) + '%' : '—'"></td>
                        <td class="text-right" x-text="fmt(colTotal(m))"></td>
                        <td class="text-right font-semibold" x-bind:class="(revenue[m] - colTotal(m)) < 0 ? 'text-error' : 'text-success'"
                            x-text="fmt(revenue[m] - colTotal(m))"></td>
                        <td class="text-right text-on-surface-variant"
                            x-text="revenue[m] > 0 ? Math.round((revenue[m] - colTotal(m)) / revenue[m] * 100) + '%' : '—'"></td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-surface-border font-bold text-on-surface">
                    <td class="text-left">Total</td>
                    <td class="text-right" x-text="fmt(sumTarget())"></td>
                    <td class="text-right" x-text="fmt(sumRevenue())"></td>
                    <td class="text-right" x-text="sumTarget() > 0 ? Math.round(sumRevenue() / sumTarget() * 100) + '%' : '—'"></td>
                    <td class="text-right" x-text="fmt(grandReal())"></td>
                    <td class="text-right" x-bind:class="(sumRevenue() - grandReal()) < 0 ? 'text-error' : 'text-success'"
                        x-text="fmt(sumRevenue() - grandReal())"></td>
                    <td class="text-right" x-text="sumRevenue() > 0 ? Math.round((sumRevenue() - grandReal()) / sumRevenue() * 100) + '%' : '—'"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

@if(!$rows->isEmpty())
<script>
function rabRealisasi() {
    const CHARTS = @json($charts);
    const MONTHS_ELAPSED = {{ $monthsElapsed }};
    const BRAND = {
        green: 'rgba(5,150,105,0.75)', greenLine: 'rgb(4,120,87)',
        red: 'rgba(200,30,30,0.75)', redLine: 'rgb(200,30,30)',
        amber: 'rgba(180,83,9,0.7)', amberLine: 'rgb(180,83,9)',
        neutral: 'rgba(107,114,128,0.35)', neutralLine: 'rgb(107,114,128)',
    };
    return {
        rows: @json($rows),
        revenue: @json($revenueRow),
        target: @json($targetRow),
        monthNames: @json($monthNames),
        MONTHS_ELAPSED,
        tab: 'month',
        dirty: false, busy: false, flash: '',
        year: {{ $year }},
        _charts: [],

        init() { this.$nextTick(() => this.renderCharts()); },

        disp(v) { return v ? new Intl.NumberFormat('id-ID').format(v) : ''; },
        fmt(v) { v = Math.round(v || 0); return (v < 0 ? '-' : '') + 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(v)); },
        parse(raw) { return parseInt(String(raw).replace(/[^\d-]/g, ''), 10) || 0; },

        setCell(row, m, raw) { const n = this.parse(raw); if (row.months[m] !== n) { row.months[m] = n; this.dirty = true; this.refreshCharts(); } },
        setRevenue(m, raw)   { const n = this.parse(raw); if (this.revenue[m] !== n) { this.revenue[m] = n; this.dirty = true; this.refreshCharts(); } },
        setTarget(m, raw)    { const n = this.parse(raw); if (this.target[m] !== n) { this.target[m] = n; this.dirty = true; } },

        // ── per baris ───────────────────────────────────────────────
        rowReal(r)    { let t = 0; for (let m = 1; m <= 12; m++) t += (r.months[m] || 0); return t; },
        rowRealYtd(r) { let t = 0; for (let m = 1; m <= MONTHS_ELAPSED; m++) t += (r.months[m] || 0); return t; },
        rowAbsorption(r) { return r.budget_total > 0 ? Math.round(this.rowReal(r) / r.budget_total * 1000) / 10 : 0; },
        rowPace(r)    { return r.budget_to_date > 0 ? Math.round(this.rowRealYtd(r) / r.budget_to_date * 1000) / 10 : null; },
        rowVariance(r){ return this.rowRealYtd(r) - r.budget_to_date; },
        rowForecast(r){ return MONTHS_ELAPSED > 0 ? Math.round(this.rowReal(r) / MONTHS_ELAPSED * 12) : null; },
        rowSisa(r)    { return r.budget_total - this.rowReal(r); },

        // ── total ──────────────────────────────────────────────────
        colTotal(m)   { return this.rows.reduce((s, r) => s + (r.months[m] || 0), 0); },
        grandReal()   { return this.rows.reduce((s, r) => s + this.rowReal(r), 0); },
        grandRealYtd(){ return this.rows.reduce((s, r) => s + this.rowRealYtd(r), 0); },
        grandBudget() { return this.rows.reduce((s, r) => s + r.budget_total, 0); },
        grandBudgetToDate() { return this.rows.reduce((s, r) => s + r.budget_to_date, 0); },
        grandAbsorption() { const b = this.grandBudget(); return b > 0 ? Math.round(this.grandReal() / b * 1000) / 10 : 0; },
        grandPace()   { const b = this.grandBudgetToDate(); return b > 0 ? Math.round(this.grandRealYtd() / b * 1000) / 10 : null; },
        grandVariance(){ return this.grandRealYtd() - this.grandBudgetToDate(); },
        grandForecast(){ return MONTHS_ELAPSED > 0 ? Math.round(this.grandReal() / MONTHS_ELAPSED * 12) : null; },
        grandForecastVar() { return this.grandForecast() === null ? 0 : this.grandForecast() - this.grandBudget(); },
        grandSisa()   { return this.grandBudget() - this.grandReal(); },
        sumRevenue()  { let t = 0; for (let m = 1; m <= 12; m++) t += (this.revenue[m] || 0); return t; },
        sumTarget()   { let t = 0; for (let m = 1; m <= 12; m++) t += (this.target[m] || 0); return t; },

        // ── per kuartal (tab) ──────────────────────────────────────
        qMonths(q) { return [q * 3 - 2, q * 3 - 1, q * 3]; },
        qReal(rowId, q) { const r = this.rows.find(x => x.id === rowId); return r ? this.qMonths(q).reduce((s, m) => s + (r.months[m] || 0), 0) : 0; },
        qGrand(q) { return this.qMonths(q).reduce((s, m) => s + this.colTotal(m), 0); },
        qPct(rowId, q, budget) { return budget > 0 ? Math.round(this.qReal(rowId, q) / budget * 1000) / 10 : null; },
        qStatusLabel(rowId, q, budget) { const p = this.qPct(rowId, q, budget); return p === null ? '—' : p > 110 ? 'Kritis' : p > 100 ? 'Waspada' : 'Aman'; },
        qStatusClass(rowId, q, budget) { const p = this.qPct(rowId, q, budget); return p === null ? 'badge-ghost' : p > 110 ? 'badge-error' : p > 100 ? 'badge-warning' : 'badge-success'; },

        // ── status selisih anggaran ────────────────────────────────
        statusOf(pace, absorption) {
            if (absorption >= 100) return 'Kritis';
            if (pace === null) return 'Belum mulai';
            if (pace > 110) return 'Kritis';
            if (pace > 100) return 'Waspada';
            return 'Aman';
        },
        statusLabel(r) { return this.statusOf(this.rowPace(r), this.rowAbsorption(r)); },
        statusClass(r) {
            const s = this.statusLabel(r);
            return s === 'Kritis' ? 'badge-error' : s === 'Waspada' ? 'badge-warning' : s === 'Belum mulai' ? 'badge-ghost' : 'badge-success';
        },
        pctText(p)  { return p === null ? 'text-on-surface-variant' : p > 110 ? 'text-error font-semibold' : p > 100 ? 'text-warning' : 'text-on-surface-variant'; },
        barClass(p) { return p === null ? 'bg-surface-container' : p > 110 ? 'bg-error' : p > 100 ? 'bg-warning' : 'bg-success'; },

        // ── grafik ─────────────────────────────────────────────────
        renderCharts() {
            if (typeof Chart === 'undefined') return;
            const money = v => 'Rp ' + Number(v).toLocaleString('id-ID');
            const compact = v => 'Rp ' + Number(v).toLocaleString('id-ID', { notation: 'compact' });
            const opts = (extra = {}) => ({
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: c => `${c.dataset.label}: ${money(c.raw)}` } } },
                scales: { y: { ticks: { callback: compact } } },
                ...extra,
            });
            this._charts.forEach(c => c.destroy());
            this._charts = [];

            this._charts.push(new Chart(document.getElementById('chartPL'), {
                data: { labels: CHARTS.months, datasets: [
                    { type: 'bar', label: 'Pendapatan', data: this.chartRevenue(), backgroundColor: BRAND.green, borderRadius: 6, order: 2 },
                    { type: 'bar', label: 'Beban', data: this.chartExpense(), backgroundColor: BRAND.red, borderRadius: 6, order: 2 },
                    { type: 'line', label: 'Laba / Rugi', data: this.chartProfit(), borderColor: BRAND.amberLine, backgroundColor: BRAND.amber, tension: 0.3, borderWidth: 2, order: 1 },
                ] },
                options: opts(),
            }));

            this._charts.push(new Chart(document.getElementById('chartQuarter'), {
                type: 'bar',
                data: { labels: CHARTS.quarter_labels, datasets: [
                    { label: 'Anggaran', data: CHARTS.quarter_budget, backgroundColor: BRAND.neutral, borderRadius: 6 },
                    { label: 'Realisasi', data: this.chartQuarterReal(), backgroundColor: BRAND.green, borderRadius: 6 },
                ] },
                options: opts(),
            }));

            this._charts.push(new Chart(document.getElementById('chartAbsorption'), {
                type: 'line',
                data: { labels: CHARTS.months, datasets: [
                    { label: 'Realisasi kumulatif', data: this.chartAbsorption(), borderColor: BRAND.greenLine, backgroundColor: BRAND.green, tension: 0.3, borderWidth: 2, fill: true, spanGaps: false },
                    { label: 'Rencana', data: CHARTS.absorption_plan, borderColor: BRAND.neutralLine, borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0 },
                ] },
                options: opts({
                    scales: { y: { ticks: { callback: v => v + '%' }, suggestedMax: 110 } },
                    plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: c => `${c.dataset.label}: ${c.raw}%` } } },
                }),
            }));

            this._charts.push(new Chart(document.getElementById('chartCategory'), {
                type: 'bar',
                data: { labels: CHARTS.category.map(c => c.name), datasets: [
                    { label: 'Anggaran', data: CHARTS.category.map(c => c.budget), backgroundColor: BRAND.neutral, borderRadius: 4 },
                    { label: 'Realisasi', data: this.chartCategoryReal(), backgroundColor: BRAND.green, borderRadius: 4 },
                ] },
                options: opts({ indexAxis: 'y', scales: { x: { ticks: { callback: compact } } } }),
            }));
        },

        refreshCharts() {
            if (!this._charts.length) return;
            this._charts[0].data.datasets[0].data = this.chartRevenue();
            this._charts[0].data.datasets[1].data = this.chartExpense();
            this._charts[0].data.datasets[2].data = this.chartProfit();
            this._charts[1].data.datasets[1].data = this.chartQuarterReal();
            this._charts[2].data.datasets[0].data = this.chartAbsorption();
            this._charts[3].data.datasets[1].data = this.chartCategoryReal();
            this._charts.forEach(c => c.update());
        },

        chartRevenue()  { const a = []; for (let m = 1; m <= 12; m++) a.push(this.revenue[m] || 0); return a; },
        chartExpense()  { const a = []; for (let m = 1; m <= 12; m++) a.push(this.colTotal(m)); return a; },
        chartProfit()   { const a = []; for (let m = 1; m <= 12; m++) a.push((this.revenue[m] || 0) - this.colTotal(m)); return a; },
        chartQuarterReal() { return [1, 2, 3, 4].map(q => this.qGrand(q)); },
        chartAbsorption() {
            const b = this.grandBudget(); let cum = 0; const a = [];
            for (let m = 1; m <= 12; m++) { cum += this.colTotal(m); a.push(m <= MONTHS_ELAPSED && b > 0 ? Math.round(cum / b * 1000) / 10 : null); }
            return a;
        },
        chartCategoryReal() {
            return CHARTS.category.map(c => { const r = this.rows.find(x => x.account_name === c.name); return r ? this.rowReal(r) : c.real; });
        },

        // ── simpan ─────────────────────────────────────────────────
        payload() {
            const out = [];
            this.rows.forEach(r => { for (let m = 1; m <= 12; m++) out.push({ account_code: r.account_code, month: m, amount: r.months[m] || 0 }); });
            for (let m = 1; m <= 12; m++) {
                out.push({ account_code: '__REVENUE__', month: m, amount: this.revenue[m] || 0 });
                out.push({ account_code: '__REVENUE_TARGET__', month: m, amount: this.target[m] || 0 });
            }
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
                this.flash = data.message; setTimeout(() => this.flash = '', 3000);
                return true;
            } catch (e) { alert(e.message); return false; }
            finally { this.busy = false; }
        },
        async save() {
            if (await this.post('{{ route('finance.rab-realisasi.actuals') }}', { year: this.year, actuals: this.payload() })) this.dirty = false;
        },
        async syncFromJournals() {
            if (!confirm('Hapus semua angka realisasi beban yang pernah ditimpa manual? Halaman akan kembali sepenuhnya mengikuti angka pembukuan.')) return;
            if (await this.post('{{ route('finance.rab-realisasi.sync-journals') }}', { year: this.year })) location.reload();
        },
    };
}
</script>
@endif
</x-app-layout>
