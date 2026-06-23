<x-app-layout>
    <x-slot name="title">Finance Dashboard</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 72rem">

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

        {{-- Header + Filter --}}
        <div class="flex items-center justify-between">
            <h3 class="text-headline-lg font-semibold text-on-surface">Finance Dashboard</h3>
            <form method="GET" action="{{ route('finance.index') }}" class="flex items-center gap-sm">
                <select name="month" class="select select-sm" onchange="this.form.submit()">
                    @foreach(range(0, 23) as $i)
                        @php $m = now()->subMonths($i)->format('Y-m'); @endphp
                        <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>
                            {{ now()->subMonths($i)->translatedFormat('F Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Row 1: Posisi Kas --}}
        <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Cash Balance</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">Rp {{ number_format($cashBalance, 0, ',', '.') }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Saldo kas & bank saat ini</p>
            </div>
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Total Revenue</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">Rp {{ number_format($revenue, 0, ',', '.') }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Pendapatan dari sesi yang sudah selesai</p>
            </div>
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Total Expense</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">Rp {{ number_format($expense, 0, ',', '.') }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Biaya yang sudah dikeluarkan bulan ini</p>
            </div>
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Net Profit</p>
                <p class="text-headline-lg font-bold mt-xs {{ $netProfit >= 0 ?'text-success' : 'text-error' }}">
                    Rp {{ number_format($netProfit, 0, ',', '.') }}
                </p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Revenue dikurangi expense bulan ini</p>
            </div>
        </div>

        {{-- Row 2: Kewajiban & Risiko --}}
        <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Deferred Revenue</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">Rp {{ number_format($deferredRevenue, 0, ',', '.') }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Pendapatan diterima di muka</p>
            </div>
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Tutor Payable</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">Rp {{ number_format($tutorPayable, 0, ',', '.') }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Belum dibayar ke tutor</p>
            </div>
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Collection Rate</p>
                <p class="text-headline-lg font-bold mt-xs {{ $collectionRate >= 80 ?'text-success' : ($collectionRate >= 50 ? 'text-warning' : 'text-error') }}">
                    {{ $collectionRate }}%
                </p>
                <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden mt-xs">
                    <div class="h-full rounded-full {{ $collectionRate >= 80 ?'bg-success' : ($collectionRate >= 50 ? 'bg-warning' : 'bg-error') }}"
                         style="width: {{ min($collectionRate, 100) }}%"></div>
                </div>
                <p class="text-body-sm text-on-surface-variant mt-xs">Persentase cicilan siswa yang sudah masuk bulan ini</p>
            </div>
            <div class="app-card">
                <p class="text-body-sm text-on-surface-variant">Burn Rate</p>
                <p class="text-headline-lg font-bold text-on-surface mt-xs">Rp {{ number_format($burnRate, 0, ',', '.') }}</p>
                <p class="text-body-sm text-on-surface-variant mt-xs">Rata-rata pengeluaran per bulan (6 bulan terakhir)</p>
                @if($runwayMonths !== null)
                    <p class="text-body-sm mt-xs">Tanpa pemasukan baru, bertahan: <span class="{{ $runwayMonths <= 3 ?'text-error' : ($runwayMonths <= 6 ? 'text-warning' : 'text-success') }} font-medium">{{ $runwayMonths }} bulan lagi</span></p>
                @endif
            </div>
        </div>

        {{-- Charts Row 1 --}}
        <div class="grid gap-lg" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))">
            <div class="app-card space-y-md">
                <h4 class="text-headline-md font-semibold text-on-surface">Revenue vs Expense (12 Bulan)</h4>
                <canvas id="revenueExpenseChart" height="120"></canvas>
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

        {{-- Charts Row 2 --}}
        <div class="app-card space-y-md">
            <div class="flex items-center justify-between flex-wrap gap-sm">
                <h4 class="text-headline-md font-semibold text-on-surface">Revenue per Program</h4>
                <div class="flex gap-xs items-center flex-wrap">
                    <select id="revenueProgramPeriod" class="select select-sm" onchange="handleRevenueProgramFilter()">
                        <option value="year">Tahun Berjalan</option>
                        <option value="quarter">Kuartal Ini</option>
                        <option value="month">Bulan Ini</option>
                        <option value="custom">Custom</option>
                    </select>
                    <div id="revenueProgramCustomRange" class="hidden flex gap-xs items-center">
                        <input type="date" id="revenueProgramFrom" class="input input-sm" />
                        <span class="text-on-surface-variant text-xs">s/d</span>
                        <input type="date" id="revenueProgramTo" class="input input-sm" />
                        <button type="button" onclick="loadRevenueByProgram()" class="btn btn-sm bg-primary-container text-on-primary border-none">Tampilkan</button>
                    </div>
                </div>
            </div>
            <canvas id="revenueProgramChart" height="80"></canvas>
        </div>

        {{-- Overdue + Pending Rates --}}
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

            <div class="app-card space-y-md"
                x-data="{ open: null }"
                x-init="{{ $errors->any() ? 'open = ' . (old('attendance_tutor_id') ?? 'null') : '' }}">

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
                <p class="text-body-sm text-on-surface-variant">Belum ada jurnal bulan ini.</p>
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
document.addEventListener('DOMContentLoaded', function () {

    // ── Brand-aligned chart palette per BRAND_PERSONALITY_GUIDE.md Section 3 ──
    // Brand rule: "Green is identity, not decoration. Never replace with blue or purple."
    // Previously these charts used Tailwind green-500 (#22c55e), red-500 (#ef4444),
    // indigo-500 (#6366f1), sky-500 (#0ea5e9), purple-500 (#a855f7) — all OFF-BRAND.
    // Replaced with brand palette:
    //   Brand green    rgb(5, 150, 105)  — secondary
    //   Brand green dk rgb(4, 120, 87)   — success (AA-pass on white)
    //   Brand green dk rgb(6, 95, 70)    — primary
    //   Brand amber    rgb(180, 83, 9)   — tertiary (warmth)
    //   Brand amber lt rgb(217, 119, 6)  — warning
    //   Brand red      rgb(200, 30, 30)  — error (AA-pass on white)
    //   Neutral slate  rgb(107, 114, 128) — outline
    const BRAND = {
        green:        'rgba(5, 150, 105, 0.7)',
        greenDark:    'rgba(4, 120, 87, 0.75)',
        greenDarker:  'rgba(6, 95, 70, 0.7)',
        amber:        'rgba(180, 83, 9, 0.7)',
        amberLight:   'rgba(217, 119, 6, 0.65)',
        red:          'rgba(200, 30, 30, 0.7)',
        neutral:      'rgba(107, 114, 128, 0.5)',
    };

    const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
    new Chart(revenueExpenseCtx, {
        type: 'bar',
        data: {
            labels: @json($chartMonths),
            datasets: [
                {
                    label: 'Revenue',
                    data: @json($chartRevenue),
                    backgroundColor: BRAND.green,  // brand green for revenue (good = green)
                    borderRadius: 12,
                },
                {
                    label: 'Expense',
                    data: @json($chartExpense),
                    backgroundColor: BRAND.red,    // brand red for expense (danger = red)
                    borderRadius: 12,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => 'Rp ' + ctx.raw.toLocaleString('id-ID')
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: val => 'Rp ' + val.toLocaleString('id-ID')
                    }
                }
            }
        }
    });

    const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
    new Chart(enrollmentCtx, {
        type: 'doughnut',
        data: {
            labels: @json($chartProgramLabels),
            datasets: [{
                data: @json($chartProgramData),
                backgroundColor: [
                    BRAND.green,        // program 1 — brand green
                    BRAND.greenDark,    // program 2 — darker green
                    BRAND.greenDarker,  // program 3 — primary dark green
                    BRAND.amber,        // program 4 — tertiary amber
                    BRAND.amberLight,   // program 5 — warning amber
                    BRAND.neutral,      // program 6 (or others) — neutral
                ],
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': ' + ctx.raw
                    }
                }
            }
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
            }
        }]
    });

    const revenueProgramCtx = document.getElementById('revenueProgramChart').getContext('2d');
    let revenueProgramChart = new Chart(revenueProgramCtx, {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Revenue', data: [], backgroundColor: BRAND.green, borderRadius: 12 }] },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => 'Rp ' + ctx.raw.toLocaleString('id-ID') } }
            },
            scales: { x: { ticks: { callback: val => 'Rp ' + val.toLocaleString('id-ID') } } }
        }
    });

    window.handleRevenueProgramFilter = function() {
        const period = document.getElementById('revenueProgramPeriod').value;
        document.getElementById('revenueProgramCustomRange').classList.toggle('hidden', period !== 'custom');
        if (period !== 'custom') loadRevenueByProgram();
    }

    window.loadRevenueByProgram = function() {
        const period = document.getElementById('revenueProgramPeriod').value;
        const from   = document.getElementById('revenueProgramFrom')?.value;
        const to     = document.getElementById('revenueProgramTo')?.value;

        let url = `{{ route('finance.chart.revenue-by-program') }}?period=${period}`;
        if (period === 'custom' && from && to) url += `&from=${from}&to=${to}`;

        fetch(url)
            .then(r => r.json())
            .then(d => {
                revenueProgramChart.data.labels = d.labels;
                revenueProgramChart.data.datasets[0].data = d.data;
                revenueProgramChart.update();
            });
    }

    loadRevenueByProgram();

});
</script>

</x-app-layout>
