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
        <div class="space-y-xs">
            <div class="flex items-center gap-xs text-label-lg text-on-surface-variant">
                <span>Finance</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface font-medium">Dashboard</span>
            </div>
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
                        <option value="month">Bulan Ini</option>
                        <option value="last_month">Bulan Lalu</option>
                        <option value="quarter">Kuartal Ini</option>
                        <option value="year">Tahun Ini</option>
                        <option value="all">Semua Waktu</option>
                        <option value="custom">Custom Range</option>
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
        </div>

        {{-- RINGKASAN BESAR — posisi laba-rugi "per detik ini" (akumulatif, live) --}}
        <div class="app-card">
            <div class="flex items-center justify-between mb-md">
                <p class="text-body-md font-semibold text-on-surface">Ringkasan Laba–Rugi</p>
                <span class="text-label-lg text-on-surface-variant">Akumulatif sejak awal pembukuan · dihitung real-time</span>
            </div>
            <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
                <div>
                    <p class="text-body-sm text-on-surface-variant">Pendapatan Total</p>
                    <p class="font-bold text-on-surface leading-tight text-headline-lg break-all">Rp {{ number_format($revenueTotal, 0, ',', '.') }}</p>
                    @if(abs($revenueTotal - $revenueYtd) >= 0.5)
                        <p class="text-label-lg text-on-surface-variant mt-xs">Tahun ini: Rp {{ number_format($revenueYtd, 0, ',', '.') }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Beban Operasional</p>
                    <p class="font-bold text-on-surface leading-tight text-headline-lg break-all">Rp {{ number_format($expenseTotal, 0, ',', '.') }}</p>
                    @if(abs($expenseTotal - $expenseYtd) >= 0.5)
                        <p class="text-label-lg text-on-surface-variant mt-xs">Tahun ini: Rp {{ number_format($expenseYtd, 0, ',', '.') }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-body-sm text-on-surface-variant">Laba/Rugi Bersih</p>
                    {{-- Nominal & badge margin dipisah ke <span> sendiri: break-all
                         hanya membungkus nominal rupiah, badge "(78.8%)" dapat
                         whitespace-nowrap supaya tidak pernah kepotong di tengah. --}}
                    <p class="font-bold leading-tight text-headline-lg flex flex-wrap items-baseline gap-xs {{ $profitTotal >= 0 ? 'text-success' : 'text-error' }}">
                        <span class="break-all">Rp {{ number_format($profitTotal, 0, ',', '.') }}</span>
                        @if(!is_null($profitMarginTotal))
                            <span class="text-body-md font-semibold whitespace-nowrap">({{ number_format($profitMarginTotal, 1) }}%)</span>
                        @endif
                    </p>
                    @if(abs($profitTotal - $profitYtd) >= 0.5 || $profitMarginTotal !== $profitMarginYtd)
                        <p class="text-label-lg text-on-surface-variant mt-xs">
                            Tahun ini: Rp {{ number_format($profitYtd, 0, ',', '.') }}@if(!is_null($profitMarginYtd)) · margin {{ number_format($profitMarginYtd, 1) }}%@endif
                        </p>
                    @endif
                    <p class="text-label-lg text-on-surface-variant">Margin = laba ÷ pendapatan</p>
                </div>
            </div>
        </div>

        {{-- Baris 2: Posisi Kas + Posisi Keuangan (2 kolom — lebih menonjol dari
             "Detail lainnya" di bawah, tapi di bawah kartu besar Laba–Rugi). --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg">
            <div class="lg:col-span-5 app-card flex flex-col justify-center min-h-[120px]">
                <p class="text-body-sm text-on-surface-variant">Kas &amp; Setara Kas</p>
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

            <div class="lg:col-span-7 app-card flex flex-col justify-center min-h-[120px]">
                <p class="text-body-sm text-on-surface-variant mb-sm">Posisi Keuangan (Neraca)</p>
                <div class="grid gap-md" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr))">
                    <div>
                        <p class="text-body-sm text-on-surface-variant">Total Aset</p>
                        <p class="font-semibold text-on-surface text-title-lg whitespace-nowrap" x-text="'Rp ' + fmt(totalAsset)"></p>
                    </div>
                    <div>
                        <p class="text-body-sm text-on-surface-variant">Total Kewajiban</p>
                        <p class="font-semibold text-on-surface text-title-lg whitespace-nowrap" x-text="'Rp ' + fmt(totalLiability)"></p>
                    </div>
                    <div>
                        <p class="text-body-sm text-on-surface-variant">Total Ekuitas</p>
                        <p class="font-semibold text-on-surface text-title-lg whitespace-nowrap" x-text="'Rp ' + fmt(totalEquity)"></p>
                    </div>
                </div>
                <p class="text-label-lg text-on-surface-variant mt-sm">Per akhir periode terpilih</p>
            </div>
        </div>

        {{-- Baris 3: Efisiensi Tenaga Kerja (LER) — mengukur setiap Rp yang
             dikeluarkan untuk gaji/honor menghasilkan margin kotor berapa
             kali lipat. Terdiri dari 2 komponen: tenaga pengajar (DLER, sudah
             bisa dihitung) dan tim admin/manajemen (MLER, belum tersedia). --}}
        <div class="space-y-md">
            <div class="flex items-center justify-between flex-wrap gap-xs">
                <div class="flex items-center gap-xs">
                    <p class="text-body-md font-semibold text-on-surface">Efisiensi Tenaga Kerja (LER)</p>
                    <div class="tooltip tooltip-right" data-tip="DLER = efisiensi tenaga pengajar langsung (tutor). MLER = efisiensi tim pendukung/manajemen (admin, dsb). Keduanya digabung jadi angka efisiensi tenaga kerja secara keseluruhan.">
                        <span class="material-symbols-outlined text-on-surface-variant cursor-help" style="font-size: 18px">info</span>
                    </div>
                </div>
                <span class="text-label-lg text-on-surface-variant">Periode: <span x-text="periodLabel"></span></span>
            </div>

            <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr))">
                {{-- Komponen 1: tenaga pengajar (DLER) — sudah bisa dihitung. --}}
                <div class="app-card">
                    <p class="text-body-sm font-medium text-on-surface-variant mb-sm">Tenaga pengajar (DLER)</p>
                    <div class="flex flex-wrap items-end gap-lg">
                        <div>
                            <p class="font-bold leading-tight text-headline-lg"
                                :class="dler === null ? 'text-on-surface-variant' : (dler >= 2 ? 'text-success' : (dler >= 1.5 ? 'text-warning' : 'text-error'))"
                                x-text="dler === null ? 'N/A' : dler.toFixed(1) + 'x'"></p>
                            <p class="text-label-lg mt-xs font-medium"
                                :class="dler === null ? 'text-on-surface-variant' : (dler >= 2 ? 'text-success' : (dler >= 1.5 ? 'text-warning' : 'text-error'))"
                                x-text="dler === null ? 'Belum ada honor tutor tercatat di periode ini' : (dler >= 2 ? 'Sehat' : (dler >= 1.5 ? 'Perlu Perhatian' : 'Bahaya'))"></p>
                        </div>
                        <div class="flex-1 min-w-[220px] space-y-xs">
                            <div class="flex items-baseline justify-between gap-sm">
                                <span class="text-body-sm text-on-surface-variant">Margin Kotor (Pendapatan − Honor Tutor)</span>
                                <span class="font-semibold text-on-surface text-body-md whitespace-nowrap" x-text="'Rp ' + fmt(grossMargin)"></span>
                            </div>
                            <div class="flex items-baseline justify-between gap-sm">
                                <span class="text-body-sm text-on-surface-variant">Biaya Tutor (honor + gaji tutor tetap)</span>
                                <span class="font-semibold text-on-surface text-body-md whitespace-nowrap" x-text="'Rp ' + fmt(directLaborCost)"></span>
                            </div>
                        </div>
                    </div>
                    <p class="text-label-lg text-on-surface-variant mt-md">
                        Artinya: setiap Rp 1 yang dikeluarkan untuk honor/gaji tutor, menghasilkan
                        <span class="font-medium" x-text="dler === null ? '(belum ada data)' : ('Rp ' + dler.toFixed(1))"></span> margin kotor.
                        Target sehat: 2x ke atas. Di bawah 1,5x berarti biaya tutor membakar kas lebih cepat daripada yang dihasilkan.
                    </p>
                </div>

                {{-- Komponen 2: tim admin/manajemen (MLER) — belum tersedia. --}}
                <div class="app-card">
                    <div class="flex items-center gap-xs mb-xs">
                        <p class="text-body-sm font-medium text-on-surface-variant">Tim admin/manajemen (MLER)</p>
                        <span class="badge badge-ghost text-label-lg">Belum tersedia</span>
                    </div>
                    <p class="text-label-lg text-on-surface-variant">
                        Efisiensi tim admin/manajemen (MLER) belum bisa dihitung karena gaji staff admin
                        belum pernah dicatat sebagai jurnal terpisah dari honor tutor. Mulai catat gaji
                        staff admin sebagai jurnal bulanan untuk mengaktifkan metrik ini.
                    </p>
                </div>
            </div>

            {{-- Kesimpulan: Total LER = DLER + MLER. Ikut hilang kalau salah
                 satu komponen belum ada — TIDAK diam-diam menampilkan
                 DLER-saja sebagai kesimpulan akhir yang menyesatkan. --}}
            <div class="app-card flex items-center justify-between flex-wrap gap-sm">
                <p class="text-body-sm font-semibold text-on-surface">Total LER (efisiensi tenaga kerja keseluruhan)</p>
                <p class="font-bold text-body-md" x-show="totalLer !== null"
                    :class="totalLer >= 2 ? 'text-success' : (totalLer >= 1.5 ? 'text-warning' : 'text-error')"
                    x-text="totalLer !== null ? totalLer.toFixed(1) + 'x' : ''"></p>
                <p class="text-label-lg text-on-surface-variant" x-show="totalLer === null" x-cloak>
                    Belum bisa ditampilkan — menunggu data MLER di atas.
                </p>
            </div>
        </div>

        {{-- Metrik pendukung / risiko — kartu kecil, grid 5 kolom (collapse ke
             2 kolom di tablet, 1 di mobile). --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-md">
            <div class="app-card flex flex-col min-h-[92px]">
                <p class="text-body-sm text-on-surface-variant">Piutang Siswa</p>
                <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($accountsReceivable, 0, ',', '.') }}</p>
                <p class="text-label-lg text-on-surface-variant mt-auto pt-xs">Revenue diakui, belum dibayar</p>
            </div>
            <div class="app-card flex flex-col min-h-[92px]">
                <p class="text-body-sm text-on-surface-variant">Pendapatan Tangguhan</p>
                <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($deferredRevenue, 0, ',', '.') }}</p>
                <p class="text-label-lg text-on-surface-variant mt-auto pt-xs">Pendapatan diterima di muka</p>
            </div>
            <div class="app-card flex flex-col min-h-[92px]">
                <p class="text-body-sm text-on-surface-variant">Utang Tutor</p>
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
                <p class="text-body-sm text-on-surface-variant">Burn Rate Bulanan</p>
                <p class="font-bold text-on-surface mt-xs leading-tight text-title-lg whitespace-nowrap">Rp {{ number_format($burnRate, 0, ',', '.') }}</p>
                <p class="text-label-lg text-on-surface-variant mt-xs">Rata-rata pengeluaran per bulan (6 bulan terakhir)</p>
                @if($runwayMonths !== null)
                    <p class="text-label-lg mt-auto pt-xs">Tanpa pemasukan baru, bertahan: <span class="{{ $runwayMonths <= 3 ?'text-error' : ($runwayMonths <= 6 ? 'text-warning' : 'text-success') }} font-medium">{{ $runwayMonths }} bulan lagi</span></p>
                @endif
            </div>
        </div>

        {{-- Charts Row 1: Tren keuangan (toggle) + Enrollment per Program --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg">
            <div class="lg:col-span-7 app-card space-y-md">
                <div class="flex items-center justify-between flex-wrap gap-sm">
                    <h4 class="text-headline-md font-semibold text-on-surface">Tren Keuangan</h4>
                    <div class="join">
                        <button type="button" class="btn btn-sm join-item"
                            :class="chartTab === 'trend' ? 'bg-primary-container text-on-primary border-none' : 'btn-ghost'"
                            @click="chartTab = 'trend'; renderTrendChart()">Pendapatan &amp; Beban</button>
                        <button type="button" class="btn btn-sm join-item"
                            :class="chartTab === 'cashflow' ? 'bg-primary-container text-on-primary border-none' : 'btn-ghost'"
                            @click="chartTab = 'cashflow'; renderTrendChart()">Arus Kas</button>
                    </div>
                </div>
                <div style="position:relative;height:300px">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="lg:col-span-5 app-card space-y-md">
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
                    <h4 class="text-headline-md font-semibold text-on-surface">Cicilan Jatuh Tempo</h4>
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
                                    <th>Siswa</th>
                                    <th>Program</th>
                                    <th class="w-28">Jatuh Tempo</th>
                                    <th class="text-right">Nominal</th>
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
                    <h4 class="text-headline-md font-semibold text-on-surface">Privat Belum Bayar</h4>
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
                                        <th>Siswa</th>
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
                <h4 class="text-headline-md font-semibold text-on-surface">Jurnal Transaksi Terkini</h4>
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
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th class="w-40">No. Referensi</th>
                                <th>Deskripsi</th>
                                <th class="text-right">Nominal</th>
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
            dler: @json($figures['ler']['dler']),
            grossMargin: @json($figures['ler']['gross_margin']),
            directLaborCost: @json($figures['ler']['direct_labor_cost']),
            mler: @json($figures['ler']['mler']),
            totalLer: @json($figures['ler']['total_ler']),

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
                        this.dler = d.ler.dler;
                        this.grossMargin = d.ler.gross_margin;
                        this.directLaborCost = d.ler.direct_labor_cost;
                        this.mler = d.ler.mler;
                        this.totalLer = d.ler.total_ler;
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
                                { type: 'bar', label: 'Pendapatan', data: this.trend.revenue, backgroundColor: BRAND.green, borderRadius: 8, order: 2 },
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
