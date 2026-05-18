<x-app-layout>
<x-slot name="title">Buat Jurnal</x-slot>

<div class="p-lg space-y-md">

    {{-- Header --}}
    <div class="flex items-center gap-sm">
        <a href="{{ route('finance.journals.index') }}" class="inline-flex items-center gap-xs text-on-surface-variant hover:text-on-surface">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            <span class="text-sm">Kembali</span>
        </a>
    </div>

    <div>
        <h1 class="text-xl font-semibold text-on-surface">Buat Jurnal</h1>
        <p class="text-sm text-on-surface-variant mt-xs">Entri jurnal umum — debit harus sama dengan kredit</p>
    </div>

    {{-- Flash Error --}}
    @if(session('error'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('finance.journals.store') }}"
        x-data="{
            rows: [
                { account_id: '', debit: '', credit: '' },
                { account_id: '', debit: '', credit: '' },
            ],
            get totalDebit() {
                return this.rows.reduce((s, r) => s + (parseFloat(r.debit) || 0), 0);
            },
            get totalCredit() {
                return this.rows.reduce((s, r) => s + (parseFloat(r.credit) || 0), 0);
            },
            get balanced() {
                return Math.abs(this.totalDebit - this.totalCredit) < 0.001 && this.totalDebit > 0;
            },
            addRow() { this.rows.push({ account_id: '', debit: '', credit: '' }); },
            removeRow(i) { if (this.rows.length > 2) this.rows.splice(i, 1); },
            fmt(n) {
                return n > 0 ? new Intl.NumberFormat('id-ID').format(n) : '-';
            }
        }">
        @csrf

        <div class="space-y-md">

            {{-- Info Jurnal --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide">Informasi Jurnal</h2>

                <div class="grid gap-md" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="fieldset">
                        <label class="fieldset-legend">Tanggal <span class="text-error">*</span></label>
                        <input type="date" name="date" class="input w-full"
                            value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-legend">Nomor Referensi <span class="text-error">*</span></label>
                        <input type="text" name="reference" class="input w-full"
                            placeholder="cth: JRN-2025-001"
                            value="{{ old('reference') }}" required>
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-legend">Deskripsi <span class="text-error">*</span></label>
                        <input type="text" name="description" class="input w-full"
                            placeholder="Keterangan jurnal"
                            value="{{ old('description') }}" required>
                    </div>
                </div>
            </div>

            {{-- Entri Jurnal --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-wide">Entri Jurnal</h2>
                    <button type="button" @click="addRow()"
                        class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90">
                        <span class="material-symbols-outlined text-base">add</span>
                        Tambah Baris
                    </button>
                </div>

                {{-- Table Header --}}
                <div class="grid text-xs font-semibold text-on-surface-variant border-b border-surface-border pb-xs"
                    style="grid-template-columns: 2fr 1fr 1fr 2rem;">
                    <span>Akun</span>
                    <span class="text-right">Debit (Rp)</span>
                    <span class="text-right">Kredit (Rp)</span>
                    <span></span>
                </div>

                {{-- Rows --}}
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid items-center gap-xs border-b border-surface-border py-xs"
                        style="grid-template-columns: 2fr 1fr 1fr 2rem;">

                        <div>
                            <select :name="`items[${i}][account_id]`" x-model="row.account_id"
                                class="select select-sm w-full" required>
                                <option value="">— Pilih Akun —</option>
                                @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <input type="number" :name="`items[${i}][debit]`" x-model="row.debit"
                                class="input input-sm w-full text-right"
                                placeholder="0" min="0" step="0.01">
                        </div>

                        <div>
                            <input type="number" :name="`items[${i}][credit]`" x-model="row.credit"
                                class="input input-sm w-full text-right"
                                placeholder="0" min="0" step="0.01">
                        </div>

                        <div class="flex justify-center">
                            <button type="button" @click="removeRow(i)"
                                :disabled="rows.length <= 2"
                                class="btn btn-ghost btn-xs text-error disabled:opacity-30">
                                <span class="material-symbols-outlined text-base">remove</span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Totals --}}
                <div class="grid items-center gap-xs pt-xs"
                    style="grid-template-columns: 2fr 1fr 1fr 2rem;">
                    <div class="text-sm font-semibold text-on-surface">Total</div>
                    <div class="text-right text-sm font-semibold"
                        :class="balanced ? 'text-success' : 'text-error'"
                        x-text="'Rp ' + fmt(totalDebit)"></div>
                    <div class="text-right text-sm font-semibold"
                        :class="balanced ? 'text-success' : 'text-error'"
                        x-text="'Rp ' + fmt(totalCredit)"></div>
                    <div></div>
                </div>

                {{-- Balance indicator --}}
                <div x-show="totalDebit > 0 || totalCredit > 0" class="flex items-center gap-xs text-sm mt-xs">
                    <template x-if="balanced">
                        <span class="flex items-center gap-xs text-success">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            Debit dan kredit seimbang
                        </span>
                    </template>
                    <template x-if="!balanced">
                        <span class="flex items-center gap-xs text-error">
                            <span class="material-symbols-outlined text-base">error</span>
                            Selisih: Rp <span x-text="fmt(Math.abs(totalDebit - totalCredit))"></span>
                        </span>
                    </template>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-sm">
                <a href="{{ route('finance.journals.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90"
                    :disabled="!balanced">
                    <span class="material-symbols-outlined text-base">save</span>
                    Simpan Jurnal
                </button>
            </div>

        </div>
    </form>

</div>
</x-app-layout>



