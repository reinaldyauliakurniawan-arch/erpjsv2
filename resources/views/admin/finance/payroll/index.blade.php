<x-app-layout>
<x-slot name="title">Payroll</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Payroll</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Generate dan approve pembayaran tutor per bulan. Honor per pertemuan dihitung dari tanggal 26 bulan lalu s.d. 25 bulan ini; gaji tutor tetap per bulan penuh.</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-create').showModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90 flex-shrink-0">
            <span class="material-symbols-outlined text-base">add</span>
            Buat Payroll Run
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="alert alert-success alert-soft">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error') || $errors->has('error'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ session('error') ?? $errors->first('error') }}</span>
    </div>
    @endif
    @if($errors->has('month'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ $errors->first('month') }}</span>
    </div>
    @endif

    {{-- Table --}}
    <div class="app-card">
        @if($payrollRuns->isEmpty())
        <div class="text-center py-xl text-on-surface-variant">
            <span class="material-symbols-outlined text-[48px]">payments</span>
            <p class="mt-sm text-sm">Belum ada payroll run</p>
        </div>
        @else
        <div class="app-table-wrapper">
<table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Bulan</th>
                    <th class="text-left font-semibold py-sm">Status</th>
                    <th class="text-left font-semibold py-sm">Approved By</th>
                    <th class="text-left font-semibold py-sm">Dibuat</th>
                    <th class="text-right font-semibold py-sm">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payrollRuns as $run)
                <tr class="border-b border-surface-border">
                    <td class="py-sm text-sm font-medium text-on-surface">
                        {{ \Carbon\Carbon::parse($run->month)->isoFormat('MMMM YYYY') }}
                        <div class="text-xs font-normal text-on-surface-variant">Pertemuan {{ \App\Support\PayrollPeriod::label($run->month) }}</div>
                    </td>
                    <td class="py-sm">
                        @if($run->status === 'approved')
                            <span class="badge badge-soft badge-success">Approved</span>
                        @elseif($run->status === 'reversed')
                            <span class="badge badge-soft badge-error">Reversed</span>
                        @else
                            <span class="badge badge-soft badge-warning">Pending</span>
                        @endif
                    </td>
                    <td class="py-sm text-sm text-on-surface">
                        {{ $run->approvedBy?->name ?? '—' }}
                    </td>
                    <td class="py-sm text-sm text-on-surface-variant">
                        {{ $run->created_at->isoFormat('D MMM YYYY') }}
                    </td>
                    <td class="py-sm text-right">
                        @if($run->status === 'pending')
                        <form method="POST" action="{{ route('finance.payroll.approve', $run->id) }}"
                            x-data="{ submitting: false }"
                            @submit="if (confirm('Approve payroll {{ \Carbon\Carbon::parse($run->month)->isoFormat('MMMM YYYY') }}? Jurnal akan digenerate otomatis.')) { submitting = true } else { $event.preventDefault() }">
                            @csrf
                            <button type="submit" class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90"
                                :disabled="submitting"
                                :class="{ 'loading': submitting }">
                                <span class="material-symbols-outlined text-base">check_circle</span>
                                <span x-show="!submitting">Approve</span>
                                <span x-show="submitting" x-cloak>Memproses…</span>
                            </button>
                        </form>
                        @elseif($run->status === 'approved')
                        <form method="POST" action="{{ route('finance.payroll.reverse', $run->id) }}"
                            x-data="{ submitting: false }"
                            @submit="if (confirm('Reverse payroll {{ \Carbon\Carbon::parse($run->month)->isoFormat('MMMM YYYY') }}? Jurnal pembalik akan dibuat dan semua attendance tutor akan ditandai unpaid kembali.')) { submitting = true } else { $event.preventDefault() }">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-ghost text-error border border-error/30 hover:bg-error/10"
                                :disabled="submitting"
                                :class="{ 'loading': submitting }">
                                <span class="material-symbols-outlined text-base">undo</span>
                                <span x-show="!submitting">Reverse</span>
                                <span x-show="submitting" x-cloak>Memproses…</span>
                            </button>
                        </form>
                        @else
                            <span class="text-xs text-on-surface-variant">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
</div>

        {{-- Pagination --}}
        @if($payrollRuns->hasPages())
        <div class="flex justify-center mt-md">
            {{ $payrollRuns->links() }}
        </div>
        @endif
        @endif
    </div>

    {{-- Rekap periode yang akan dibayar --}}
    @php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
    <div class="app-card space-y-md">
        <div class="flex flex-wrap items-end justify-between gap-md">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-on-surface">Rekap payroll {{ $month->isoFormat('MMMM YYYY') }}</h2>
                <p class="text-xs text-on-surface-variant mt-xs">Yang belum dibayar: honor pertemuan {{ \App\Support\PayrollPeriod::label($month) }} + penyesuaian + gaji tetap {{ $month->isoFormat('MMMM YYYY') }}. Totalnya sama dengan jumlah yang ditransfer saat payroll bulan ini di-approve.</p>
            </div>
            <form method="GET" action="{{ route('finance.payroll.index') }}" class="flex items-end gap-sm">
                <input type="month" name="period" class="input input-sm" value="{{ $month->format('Y-m') }}">
                <button type="submit" class="btn btn-sm btn-ghost border border-surface-border">Tampilkan</button>
            </form>
        </div>
        @if($preview->isEmpty())
            <p class="text-sm text-on-surface-variant">Tidak ada honor yang belum dibayar di periode ini.</p>
        @else
        <div class="app-table-wrapper">
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Tutor</th>
                    <th class="text-right font-semibold py-sm">Pertemuan</th>
                    <th class="text-right font-semibold py-sm">Honor pertemuan</th>
                    <th class="text-right font-semibold py-sm">Penyesuaian</th>
                    <th class="text-right font-semibold py-sm">Gaji tetap</th>
                    <th class="text-right font-semibold py-sm">Total dibayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview as $r)
                <tr class="border-b border-surface-border text-sm">
                    <td class="py-sm text-on-surface">{{ $r['tutor'] }}</td>
                    <td class="py-sm text-right">{{ $r['meetings'] }}</td>
                    <td class="py-sm text-right">{{ $rp($r['fee']) }}</td>
                    <td class="py-sm text-right {{ $r['adjustments'] < 0 ? 'text-error' : '' }}">{{ $r['adjustments'] ? $rp($r['adjustments']) : '—' }}</td>
                    <td class="py-sm text-right">{{ $r['salary'] ? $rp($r['salary']) : '—' }}</td>
                    <td class="py-sm text-right font-semibold">{{ $rp($r['total']) }}</td>
                </tr>
                @endforeach
                <tr class="text-sm font-semibold">
                    <td class="py-sm">Total</td>
                    <td class="py-sm text-right">{{ $preview->sum('meetings') }}</td>
                    <td class="py-sm text-right">{{ $rp($preview->sum('fee')) }}</td>
                    <td class="py-sm text-right">{{ $rp($preview->sum('adjustments')) }}</td>
                    <td class="py-sm text-right">{{ $rp($preview->sum('salary')) }}</td>
                    <td class="py-sm text-right">{{ $rp($preview->sum('total')) }}</td>
                </tr>
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- Penyesuaian honor --}}
    <div class="app-card space-y-md">
        <div>
            <h2 class="text-base font-semibold text-on-surface">Penyesuaian honor — periode {{ \App\Support\PayrollPeriod::label($month) }}</h2>
            <p class="text-xs text-on-surface-variant mt-xs">Untuk honor di luar absen siswa: kelas B2B, tunjangan prestasi, klaim susulan, atau koreksi (isi minus untuk mengurangi, mis. klaim dobel). Ikut dibayar payroll yang periodenya memuat tanggal penyesuaian.</p>
        </div>
        @if($adjustments->isNotEmpty())
        <div class="app-table-wrapper">
        <table class="table table-sm w-full">
            <thead>
                <tr class="border-b border-surface-border text-on-surface-variant text-xs">
                    <th class="text-left font-semibold py-sm">Tanggal</th>
                    <th class="text-left font-semibold py-sm">Tutor</th>
                    <th class="text-left font-semibold py-sm">Jenis</th>
                    <th class="text-left font-semibold py-sm">Keterangan</th>
                    <th class="text-right font-semibold py-sm">Nominal</th>
                    <th class="text-right font-semibold py-sm">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($adjustments as $a)
                <tr class="border-b border-surface-border text-sm">
                    <td class="py-sm">{{ $a->date->isoFormat('D MMM YYYY') }}</td>
                    <td class="py-sm">{{ $a->tutor->user->name }}</td>
                    <td class="py-sm">{{ \App\Models\TutorFeeAdjustment::KINDS[$a->kind] ?? $a->kind }}</td>
                    <td class="py-sm text-on-surface-variant">{{ $a->description }}</td>
                    <td class="py-sm text-right {{ $a->amount < 0 ? 'text-error' : '' }}">{{ $rp($a->amount) }}</td>
                    <td class="py-sm text-right">
                        @if($a->paid_at)
                            <span class="badge badge-soft badge-success">Dibayar</span>
                        @else
                        <form method="POST" action="{{ route('finance.payroll.adjustments.destroy', $a->id) }}"
                            @submit="if (! confirm('Hapus penyesuaian ini?')) $event.preventDefault()" x-data>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-ghost text-error">Hapus</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
        <form method="POST" action="{{ route('finance.payroll.adjustments.store') }}" class="grid grid-cols-1 md:grid-cols-6 gap-sm items-end"
            x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="fieldset md:col-span-2">
                <label class="fieldset-legend">Tutor</label>
                <select name="tutor_id" class="select select-sm w-full" required>
                    <option value="">Pilih tutor</option>
                    @foreach($tutors as $t)
                        <option value="{{ $t->id }}" @selected(old('tutor_id') == $t->id)>{{ $t->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-legend">Tanggal</label>
                <input type="date" name="date" class="input input-sm w-full" value="{{ old('date', now()->toDateString()) }}" required>
            </div>
            <div class="fieldset">
                <label class="fieldset-legend">Jenis</label>
                <select name="kind" class="select select-sm w-full" required>
                    @foreach(\App\Models\TutorFeeAdjustment::KINDS as $k => $label)
                        <option value="{{ $k }}" @selected(old('kind') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fieldset">
                <label class="fieldset-legend">Nominal (Rp)</label>
                <input type="number" step="1" name="amount" class="input input-sm w-full" value="{{ old('amount') }}" required>
            </div>
            <div class="fieldset md:col-span-5">
                <label class="fieldset-legend">Keterangan</label>
                <input type="text" name="description" maxlength="255" class="input input-sm w-full" value="{{ old('description') }}" placeholder="mis. B2B RU PUTRI 9 pertemuan" required>
            </div>
            <button type="submit" class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90" :disabled="submitting">Tambah</button>
        </form>
    </div>

</div>

{{-- Modal Buat Payroll Run --}}
<dialog id="modal-create" class="modal"
    x-data x-init="{{ $errors->has('month') ? 'document.getElementById(\'modal-create\').showModal()' : '' }}">
    <div class="modal-box" style="max-width: 28rem;">
        <h3 class="text-base font-semibold text-on-surface mb-md">Buat Payroll Run</h3>

        <form method="POST" action="{{ route('finance.payroll.store') }}"
            x-data="{ submitting: false }"
            @submit="submitting = true">
            @csrf
            <div class="space-y-md">
                <div class="fieldset">
                    <label class="fieldset-legend">Bulan <span class="text-error">*</span></label>
                    <input type="month" name="month" class="input w-full"
                        value="{{ old('month', now()->format('Y-m')) }}" required
                        :disabled="submitting">
                    <p class="text-xs text-on-surface-variant mt-xs">Sistem menghitung semua pertemuan tutor yang belum dibayar dari tanggal 26 bulan sebelumnya s.d. tanggal 25 bulan ini (mis. Oktober = 26 Sep – 25 Okt). Pertemuan tanggal 26 ke atas masuk payroll bulan berikutnya.</p>
                </div>
            </div>

            <div class="modal-action mt-lg">
                <button type="button" onclick="document.getElementById('modal-create').close()"
                    class="btn btn-ghost" :disabled="submitting">Batal</button>
                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90"
                    :disabled="submitting"
                    :class="{ 'loading': submitting }">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span x-show="!submitting">Buat Run</span>
                    <span x-show="submitting" x-cloak>Memproses…</span>
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

</x-app-layout>
