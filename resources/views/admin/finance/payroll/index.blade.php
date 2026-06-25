<x-app-layout>
<x-slot name="title">Payroll</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Payroll</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Generate dan approve pembayaran tutor per bulan</p>
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
                    <p class="text-xs text-on-surface-variant mt-xs">Sistem akan menghitung semua sesi tutor yang belum dibayar di bulan ini</p>
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
