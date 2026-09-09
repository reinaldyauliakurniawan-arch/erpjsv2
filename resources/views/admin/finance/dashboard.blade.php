<x-app-layout>
    <x-slot name="title">Finance Dashboard</x-slot>

    {{-- Alpine memanggil method init() otomatis saat komponen dibuat — JANGAN
         tambah x-init="init()" lagi, itu bikin semua chart di-render 2x
         ("Canvas is already in use"). --}}
    <div class="p-lg space-y-lg" style="max-width: 72rem" x-data="financeDashboard()">

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

        {{-- Header + Filter Periode (satu filter, mempengaruhi SEMUA angka & grafik) --}}
        <div class="flex items-start justify-between flex-wrap gap-sm">
            <div>
                <h3 class="text-headline-lg font-semibold text-on-surface">Finance Dashboard</h3>
                <p class="text-label-lg text-on-surface-variant mt-xs">
                    Angka periode: <span class="font-medium text-on-surface" x-text="periodLabel"></span>
                    <span x-show="loading" class="loading loading-spinner loading-xs align-middle ml-xs"></span>
                </p>
            </div>
            <div class="flex items-center gap-sm flex-wrap">
                <select class="select select-sm" x-model="period" @change="onPeriodChange()">
                    <option value="today">Hari Ini</option>
                    <option value="week">Minggu Ini</option>
                    <option value="month">Bulan Ini</option>
                    <option value="quarter">Kuartal Ini</option>
                    <option value="year">Tahun Ini</option>
                    <option value="custom">Custom</option>
                </select>
                <div x-show="period === 'custom'" x-cloak class="flex items-center gap-xs">
                    <input type="date" class="input input-sm" x-model="from" />
                    <span class="text-on-surface-variant text-xs">s/d</span>
                    <input type="date" class="input input-sm" x-model="to" />
                    <button type="button" class="btn btn-sm bg-primary-container text-on-primary border-none"
                        @click="applyPeriod()" :disabled="!from || !to">Tampilkan</button>
                </div>
            </div>
        </div>

        {{-- RINGKASAN BESAR — posisi laba-rugi "per detik ini" (akumulatif, live) --}}
        <div class="app-card">
            <div class="flex items-center justify-between mb-md">
                <p class="text-body-md font-semibold text-on-surface">Ringkasan Laba–Rugi</p>
                <span class="text-label-lg text-on-surface-variant">Akumulatif sejak awal pembukuan · dihitung real-time</span>
            </div>
            <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
                <div>
                    <p class="text-body-sm text-on-surface-variant">Pendapatan</p>
                    <p class="font-bold text-on-surface leading-tight text-headline-lg break-all">Rp {{ number_format($revenueTotal, 0, ',', '.') }}</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Tahun ini: Rp {{ number_format($revenueYtd, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Beban</p>
                    <p class="font-bold text-on-surface leading-tight text-headline-lg break-all">Rp {{ number_format($expenseTotal, 0, ',', '.') }}</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Tahun ini: Rp {{ number_format($expenseYtd, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">{{ $profitTotal >= 0 ? 'Laba' : 'Rugi' }}</p>
                    <p class="font-bold leading-tight text-headline-lg break-all {{ $profitTotal >= 0 ? 'text-success' : 'text-error' }}">
                        Rp {{ number_format($profitTotal, 0, ',', '.') }}
                        @if(!is_null($profitMarginTotal))
                            <span class="text-body-md font-semibold">({{ number_format($profitMarginTotal, 1) }}%)</span>
                        @endif
                    </p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">
                        Tahun ini: Rp {{ number_format($profitYtd, 0, ',', '.') }}@if(!is_null($profitMarginYtd)) · margin {{ number_format($profitMarginYtd, 1) }}%@endif
                    </p>
                    <p class="text-label-lg text-on-surface-variant">Margin = laba ÷ pendapatan</p>
                </div>
            </div>
        </div>

        {{-- Baris 2: Posisi Kas + Posisi Keuangan (2 kolom — lebih menonjol dari
             "Detail lainnya" di bawah, tapi di bawah kartu besar Laba–Rugi). --}}
        <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))">
            <div class="app-card flex flex-col justify-center min-h-[120px]">
                <p class="text-body-sm text-on-surface-variant">Cash Balance</p>
                <p class="font-bold text-on-surface mt-xs leading-tight text-headline-md whitespace-nowrap" x-text="'Rp ' + fmt(cashBalance)"></p>
                <p class="text-body-sm mt-xs flex items-center gap-xs flex-wrap">
                    <span class="material-symbols-outlined text-[16px]"
                        :class="netCashFlow >= 0 ? 'text-success' : 'text-error'"
                        x-text="netCashFlow >= 0 ? 'trending_up' : 'trending_down'"></span>
                    <span :class="netCashFlow >= 0 ? 'text-success' : 'text-error'" class="font-medium whitespace-nowrap"
                        x-text="(netCashFlow >= 0 ? '+' : '−') + ' Rp ' + fmt(Math.abs(netCashFlow))"></span>
                    <span class="text-on-surface-variant">arus kas bersih <span x-text="periodLabel"></span></span>
                </p>
            </div>

            <div class="app-card flex flex-col justify-center min-h-[120px]">
                <p class="text-body-sm text-on-surface-variant mb-xs">Posisi Keuangan</p>
                <div class="space-y-xs">
                    <div class="flex items-baseline justify-between gap-sm">
                        <span class="text-body-sm text-on-surface-variant">Total Aset</span>
                        <span class="font-semibold text-on-surface text-body-md whitespace-nowrap" x-text="'Rp ' + fmt(totalAsset)"></span>
                    </div>
                    <div class="flex items-baseline justify-between gap-sm">
                        <span class="text-body-sm text-on-surface-variant">Total Kewajiban</span>
                        <span class="font-semibold text-on-surface text-body-md whitespace-nowrap" x-text="'Rp ' + fmt(totalLiability)"></span>
                    </div>
                    <div class="flex items-baseline justify-between gap-sm">
                        <span class="text-body-sm text-on-surface-variant">Total Ekuitas</span>
                        <span class="font-semibold text-on-surface text-body-md whitespace-nowrap" x-text="'Rp ' + fmt(totalEquity)"></span>
                    </div>
                </div>
                <p class="text-label-lg text-on-surface-variant mt-xs">Per akhir periode terpilih</p>
            </div>
        </div>

        {{-- Detail lainnya — kartu kecil (angka pendukung / risiko). Sengaja
             lebih kecil dari 2 kartu di atas supaya hierarki visual jelas:
             grid 3 kolom, tipografi & tinggi minimum lebih ringkas. --}}
        <div class="space-y-sm">
            <p class="text-body-md font-semibold text-on-surface">Detail lainnya</p>
            <div class="grid gap-md" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
                <div class="app-card flex flex-col min-h-[92px]">
                    <p class="text-body-sm text-on-surface-variant">Piutang Customer</p>
                    <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($accountsReceivable, 0, ',', '.') }}</p>
                    <p class="text-label-lg text-on-surface-variant mt-auto pt-xs">Revenue diakui, belum dibayar</p>
                </div>
                <div class="app-card flex flex-col min-h-[92px]">
                    <p class="text-body-sm text-on-surface-variant">Deferred Revenue</p>
                    <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($deferredRevenue, 0, ',', '.') }}</p>
                    <p class="text-label-lg text-on-surface-variant mt-auto pt-xs">Pendapatan diterima di muka</p>
                </div>
                <div class="app-card flex flex-col min-h-[92px]">
                    <p class="text-body-sm text-on-surface-variant">Tutor Payable</p>
                    <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($tutorPayable, 0, ',', '.') }}</p>
                    <p class="text-label-lg text-on-surface-variant mt-auto pt-xs">Belum dibayar ke tutor</p>
                </div>
                <div class="app-card flex flex-col min-h-[92px]">
                    <p class="text-body-sm text-on-surface-variant">Collection Rate</p>
                    <p class="font-bold mt-xs leading-tight text-title-lg"
                        :class="collectionRate >= 80 ? 'text-success' : (collectionRate >= 50 ? 'text-warning' : 'text-error')"
                        x-text="collectionRate.toFixed(1) + '%'"></p>
                    <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden mt-xs">
                        <div class="h-full rounded-full"
                            :class="collectionRate >= 80 ? 'bg-success' : (collectionRate >= 50 ? 'bg-warning' : 'bg-error')"
                            :style="'width:' + Math.min(collectionRate, 100) + '%'"></div>
                    </div>
                    <p class="text-label-lg text-on-surface-variant mt-auto pt-xs">Cicilan siswa jatuh tempo di periode ini yang sudah masuk</p>
                </div>
                <div class="app-card flex flex-col min-h-[92px]">
                    <p class="text-body-sm text-on-surface-variant">Burn Rate</p>
                    <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($burnRate, 0, ',', '.') }}</p>
                    <p class="text-label-lg text-on-surface-variant mt-xs">Rata-rata pengeluaran per bulan (6 bulan terakhir)</p>
                    @if($runwayMonths !== null)
                        <p class="text-label-lg mt-auto pt-xs">Tanpa pemasukan baru, bertahan: <span class="{{ $runwayMonths <= 3 ?'text-error' : ($runwayMonths <= 6 ? 'text-warning' : 'text-success') }} font-medium">{{ $runwayMonths }} bulan lagi</span></p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Charts Row 1: Tren keuangan (toggle) + Enrollment per Program --}}
        <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))">
            <div class="app-card space-y-md">
                <div class="flex items-center justify-between flex-wrap gap-sm">
                    <h4 class="text-headline-md font-semibold text-on-surface">Tren Keuangan</h4>
                    <div class="join">
                        <button type="button" class="btn btn-sm join-item"
                            :class="chartTab === 'trend' ? 'bg-primary-container text-on-primary border-none' : 'btn-ghost'"
                            @click="chartTab = 'trend'; renderTrendChart()">Revenue &amp; Beban</button>
                        <button type="button" class="btn btn-sm join-item"
                            :class="chartTab === 'cashflow' ? 'bg-primary-container text-on-primary border-none' : 'btn-ghost'"
                            @click="chartTab = 'cashflow'; renderTrendChart()">Arus Kas</button>
                    </div>
                </div>
                <div style="position:relative;height:300px">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="app-card space-y-md">
                <h4 class="text-headline-md font-semibold text-on-surface">Enrollment per Program</h4>
                <div class="flex items-center gap-md">
                    <div style="position: relative; height: 260px; width: 260px; flex-shrink: 0;">
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                    <div id="enrollmentLegend" class="flex flex-col gap-xs text-sm text-on-surface overflow-y-auto" style="max-height: 260px;"></div>
                </div>
            </div>
        </div>

        {{-- Charts Row 2: Revenue per Program (ikut filter periode global) --}}
        <div class="app-card space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Revenue per Program</h4>
            <div style="position:relative;height:300px">
                <canvas id="revenueProgramChart"></canvas>
            </div>
        </div>

        {{-- Overdue + Private Unpaid + Pending Rates --}}
        <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr))">

            <div class="app-card space-y-md flex flex-col" style="max-height: 400px;">
                <div class="flex items-center justify-between flex-shrink-0">
                    <h4 class="text-headline-md font-semibold text-on-surface">Overdue Installments</h4>
                    @if($overdueInstallments->count())
                        <span class="badge badge-soft badge-error whitespace-nowrap">Rp {{ number_format($overdueTotalAmount, 0, ',', '.') }}</span>
                    @endif
                </div>
                @if($overdueInstallments->isEmpty())
                    <p class="text-body-sm text-on-surface-variant">Tidak ada tagihan jatuh tempo.</p>
                @else
                    <div class="overflow-y-auto flex-1">
                        <div class="app-table-wrapper">
<table class="table table-sm">
                            <thead>
                                <tr class="border-b border-surface-border text-on-surface-variant">
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th class="w-28">Due</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($overdueInstallments as $inst)
                                    <tr class="border-b border-surface-border">
                                        <td class="text-on-surface">{{ $inst->student_name }}</td>
                                        <td class="text-on-surface-variant text-body-sm">{{ $inst->program_name }}</td>
                                        <td>
                                            <span class="badge badge-soft badge-error text-body-sm whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($inst->due_date)->format('d M Y') }}
                                            </span>
                                        </td>
                                        <td class="text-right text-on-surface">Rp {{ number_format($inst->amount, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
</div>
                    </div>
                @endif
            </div>

            <div class="app-card space-y-md flex flex-col" style="max-height: 400px;">
                <div class="flex items-center justify-between flex-shrink-0">
                    <h4 class="text-headline-md font-semibold text-on-surface">Private Class - Belum Bayar</h4>
                    @if($privateUnpaidWarnings->count())
                        <span class="badge badge-soft badge-error whitespace-nowrap">Rp {{ number_format($privateUnpaidWarningsTotal, 0, ',', '.') }}</span>
                    @endif
                </div>
                @if($privateUnpaidWarnings->isEmpty())
                    <p class="text-body-sm text-on-surface-variant">Tidak ada siswa private class yang menunggak.</p>
                @else
                    <p class="text-body-sm text-on-surface-variant flex-shrink-0">Siswa private class yang sudah diabsen tapi pembayarannya belum menutupi revenue yang diakui.</p>
                    <div class="overflow-y-auto flex-1">
                        <div class="app-table-wrapper">
                            <table class="table table-sm">
                                <thead>
                                    <tr class="border-b border-surface-border text-on-surface-variant">
                                        <th>Student</th>
                                        <th>Program</th>
                                        <th class="text-right">Piutang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($privateUnpaidWarnings as $row)
                                        <tr class="border-b border-surface-border">
                                            <td class="text-on-surface">{{ $row['student_name'] }}</td>
                                            <td class="text-on-surface-variant text-body-sm">{{ $row['program_name'] }}</td>
                                            <td class="text-right text-on-surface">Rp {{ number_format($row['outstanding'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="app-card space-y-md"
                x-data="{ open: null }"
                x-init="{{ $errors->any() ? 'open = ' . (old('rate_id') ?? 'null') : '' }}">

                <div class="flex items-center justify-between">
                    <h4 class="text-headline-md font-semibold text-on-surface">Pending Tutor Rates</h4>
                    @if($pendingRates->count())
                        <span class="badge badge-soft badge-warning">{{ $pendingRates->count() }}</span>
                    @endif
                </div>

                @if($pendingRates->isEmpty())
                    <p class="text-body-sm text-on-surface-variant">Semua rate sudah di-assign.</p>
                @else
                    <div class="space-y-sm">
                        @foreach($pendingRates as $rate)
                            <div class="border border-surface-border rounded-lg p-md space-y-xs">
                                <div class="flex items-start justify-between gap-sm">
                                    <div>
                                        <p class="text-body-md font-medium text-on-surface">{{ $rate->tutor_name }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ $rate->program_name }} · {{ $rate->session_name }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ \Carbon\Carbon::parse($rate->date)->format('d M Y') }}</p>
                                    </div>
                                    <button type="button"
                                        class="btn btn-ghost btn-sm gap-xs shrink-0"
                                        @click="open = open === {{ $rate->id }} ? null : {{ $rate->id }}">
                                        <span class="material-symbols-outlined text-[16px]">payments</span>
                                        Assign
                                    </button>
                                </div>
                                <div x-show="open === {{ $rate->id }}" x-cloak>
                                    <form method="POST" action="{{ route('finance.rate.assign', $rate->id) }}"
                                        class="flex gap-sm items-end mt-xs">
                                        @csrf
                                        <input type="hidden" name="rate_id" value="{{ $rate->id }}">
                                        <div class="fieldset flex-1">
                                            <label class="fieldset-legend text-on-surface">Payable Amount (Rp)</label>
                                            <input type="number" name="payable_amount" class="input w-full" placeholder="0" min="1" required />
                                        </div>
                                        <button type="submit"
                                            class="btn bg-primary-container text-on-primary border-none hover:opacity-90 mb-xs">
                                            Simpan
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Journals --}}
        <div class="app-card space-y-md flex flex-col" style="max-height: 400px;">
            <div class="flex items-center justify-between flex-shrink-0">
                <h4 class="text-headline-md font-semibold text-on-surface">Recent Journals</h4>
                <a href="{{ route('finance.journals.index') }}" class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                    Lihat semua
                </a>
            </div>
            @if($journals->isEmpty())
                <p class="text-body-sm text-on-surface-variant">Belum ada jurnal di periode ini.</p>
            @else
                <div class="overflow-y-auto flex-1">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead>
                            <tr class="border-b border-surface-border text-on-surface-variant">
                                <th>Date</th>
                                <th>Type</th>
                                <th class="w-40">Reference</th>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($journals as $journal)
                                <tr class="border-b border-surface-border">
                                    <td class="text-on-surface-variant text-body-sm">
                                        {{ \Carbon\Carbon::parse($journal->date)->format('d M Y') }}
                                    </td>
                                    <td>
                                        <span class="badge badge-soft text-body-sm">{{ $journal->type ?? 'general' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft whitespace-nowrap">{{ $journal->reference }}</span>
                                    </td>
                                    <td class="text-on-surface">{{ $journal->description }}</td>
                                    <td class="text-right text-on-surface">Rp {{ number_format($journal->total_amount, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            @endif
        </div>

    </div>

    <script>
    // ── Brand-aligned chart palette per BRAND_PERSONALITY_GUIDE.md Section 3 ──
    // "Green is identity, not decoration. Never replace with blue or purple."
    const BRAND = {
        green:        'rgba(5, 150, 105, 0.7)',
        greenSolid:   'rgb(5, 150, 105)',
        greenDark:    'rgba(4, 120, 87, 0.75)',
        greenDarker:  'rgba(6, 95, 70, 0.7)',
        primary:      'rgb(6, 95, 70)',
        amber:        'rgba(180, 83, 9, 0.7)',
        amberLight:   'rgba(217, 119, 6, 0.65)',
        red:          'rgba(200, 30, 30, 0.7)',
        redSolid:     'rgb(200, 30, 30)',
        neutral:      'rgba(107, 114, 128, 0.5)',
    };

    const rp = v => 'Rp ' + Number(v || 0).toLocaleString('id-ID');

    function financeDashboard() {
        return {
            // ── state (di-seed dari server, dihitung LIVE dari journal_items) ──
            period: @json($figures['period']),
            from: @json($figures['from']),
            to: @json($figures['to']),
            periodLabel: @json($figures['period_label']),
            revenue: @json($figures['revenue']),
            expense: @json($figures['expense']),
            netProfit: @json($figures['net_profit']),
            netProfitMargin: @json($figures['net_profit_margin']),
            netCashFlow: @json($figures['net_cash_flow']),
            cashBalance: @json($figures['cash_balance']),
            totalAsset: @json($figures['total_asset']),
            totalLiability: @json($figures['total_liability']),
            totalEquity: @json($figures['total_equity']),
            collectionRate: @json($figures['collection_rate']),
            trend: @json($figures['trend']),
            cashFlowSeries: @json($figures['cash_flow_series']),
            revenueByProgram: @json($figures['revenue_by_program']),

            loading: false,
            chartTab: 'trend',
            _trendChart: null,
            _revProgramChart: null,
            _enrollmentChart: null,

            fmt(v) { return Number(v || 0).toLocaleString('id-ID'); },

            // Buang chart lama yang menempel di sebuah canvas SEBELUM bikin baru.
            // Pakai Chart.getChart() supaya juga aman kalau komponen Alpine
            // di-recreate (mis. navigasi bfcache) — bukan cuma double-call biasa.
            _freshCanvas(id) {
                const el = document.getElementById(id);
                if (!el) return null;
                const existing = (window.Chart && Chart.getChart) ? Chart.getChart(el) : null;
                if (existing) existing.destroy();
                return el.getContext('2d');
            },

            init() {
                this.renderTrendChart();
                this.renderRevenueProgramChart();
                this.renderEnrollmentChart();
            },

            onPeriodChange() {
                if (this.period !== 'custom') this.applyPeriod();
            },

            applyPeriod() {
                this.loading = true;
                const params = new URLSearchParams({ period: this.period });
                if (this.period === 'custom') { params.set('from', this.from); params.set('to', this.to); }

                fetch(`{{ route('finance.dashboard-data') }}?` + params.toString())
                    .then(r => r.json())
                    .then(d => {
                        this.periodLabel = d.period_label;
                        this.from = d.from; this.to = d.to;
                        this.revenue = d.revenue;
                        this.expense = d.expense;
                        this.netProfit = d.net_profit;
                        this.netProfitMargin = d.net_profit_margin;
                        this.netCashFlow = d.net_cash_flow;
                        this.cashBalance = d.cash_balance;
                        this.totalAsset = d.total_asset;
                        this.totalLiability = d.total_liability;
                        this.totalEquity = d.total_equity;
                        this.collectionRate = d.collection_rate;
                        this.trend = d.trend;
                        this.cashFlowSeries = d.cash_flow_series;
                        this.revenueByProgram = d.revenue_by_program;
                        this.renderTrendChart();
                        this.renderRevenueProgramChart();
                    })
                    .finally(() => { this.loading = false; });
            },

            renderTrendChart() {
                if (this._trendChart) { this._trendChart.destroy(); this._trendChart = null; }
                const ctx = this._freshCanvas('trendChart');
                if (!ctx) return;

                if (this.chartTab === 'trend') {
                    this._trendChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.trend.labels,
                            datasets: [
                                { type: 'bar', label: 'Revenue', data: this.trend.revenue, backgroundColor: BRAND.green, borderRadius: 8, order: 2 },
                                { type: 'bar', label: 'Beban', data: this.trend.expense, backgroundColor: BRAND.red, borderRadius: 8, order: 2 },
                                { type: 'line', label: 'Laba Bersih', data: this.trend.netProfit, borderColor: BRAND.primary, backgroundColor: BRAND.primary, tension: 0.3, borderWidth: 2, pointRadius: 2, order: 1 },
                            ],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: { callbacks: { label: c => c.dataset.label + ': ' + rp(c.raw) } },
                            },
                            scales: { y: { ticks: { callback: v => rp(v) } } },
                        },
                    });
                } else {
                    const colors = this.cashFlowSeries.net.map(v => v >= 0 ? BRAND.green : BRAND.red);
                    this._trendChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.cashFlowSeries.labels,
                            datasets: [{ label: 'Arus Kas Bersih', data: this.cashFlowSeries.net, backgroundColor: colors, borderRadius: 8 }],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: c => (c.raw >= 0 ? 'Masuk ' : 'Keluar ') + rp(Math.abs(c.raw)) } },
                            },
                            scales: { y: { ticks: { callback: v => rp(v) } } },
                        },
                    });
                }
            },

            renderRevenueProgramChart() {
                if (this._revProgramChart) { this._revProgramChart.destroy(); this._revProgramChart = null; }
                const ctx = this._freshCanvas('revenueProgramChart');
                if (!ctx) return;
                this._revProgramChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: this.revenueByProgram.labels,
                        datasets: [{ label: 'Revenue', data: this.revenueByProgram.data, backgroundColor: BRAND.green, borderRadius: 10 }],
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: c => rp(c.raw) } },
                        },
                        scales: { x: { ticks: { callback: v => rp(v) } } },
                    },
                });
            },

            renderEnrollmentChart() {
                if (this._enrollmentChart) { this._enrollmentChart.destroy(); this._enrollmentChart = null; }
                const ctx = this._freshCanvas('enrollmentChart');
                if (!ctx) return;
                this._enrollmentChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($chartProgramLabels),
                        datasets: [{
                            data: @json($chartProgramData),
                            backgroundColor: [BRAND.green, BRAND.greenDark, BRAND.greenDarker, BRAND.amber, BRAND.amberLight, BRAND.neutral],
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': ' + c.raw } } },
                    },
                    plugins: [{
                        id: 'customLegend',
                        afterUpdate(chart) {
                            const container = document.getElementById('enrollmentLegend');
                            if (!container) return;
                            container.innerHTML = '';
                            chart.data.labels.forEach((label, i) => {
                                const color = chart.data.datasets[0].backgroundColor[i];
                                const value = chart.data.datasets[0].data[i];
                                const meta = chart.getDatasetMeta(0);
                                const hidden = meta.data[i] && meta.data[i].hidden;
                                const div = document.createElement('div');
                                div.className = 'flex items-center gap-xs cursor-pointer';
                                div.style.opacity = hidden ? '0.4' : '1';
                                div.innerHTML = `
                                    <span style="width:10px;height:10px;border-radius:2px;background:${color};flex-shrink:0;display:inline-block;"></span>
                                    <span class="text-on-surface-variant" style="${hidden ? 'text-decoration:line-through' : ''}">${label}</span>
                                    <span class="font-medium ml-auto pl-sm">${value}</span>`;
                                div.addEventListener('click', () => {
                                    const m = chart.getDatasetMeta(0);
                                    m.data[i].hidden = !m.data[i].hidden;
                                    chart.update();
                                });
                                container.appendChild(div);
                            });
                        },
                    }],
                });
            },
        };
    }
    </script>

</x-app-layout>
