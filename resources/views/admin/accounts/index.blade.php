<x-app-layout>
    <x-slot name="title">Chart of Accounts</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 64rem">

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

        {{-- Header --}}
        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface min-w-0 shrink truncate">Chart of Accounts</h3>
            <div class="flex gap-sm flex-shrink-0">
                <a href="{{ route('finance.exports.coa') }}" class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Export COA
                </a>
                <button type="button" onclick="document.getElementById('modal-add').showModal()"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tambah Akun
                </button>
            </div>
        </div>

        {{-- Import COA --}}
        <div class="app-card space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Import COA</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: code, name, type</p>
                </div>
                <a href="{{ route('finance.exports.finance-template', 'coa') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Template
                </a>
            </div>
            <form method="POST" action="{{ route('finance.imports.coa') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

        {{-- Accounts grouped by type --}}
        @foreach(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $type)
            @if(isset($accounts[$type]) && $accounts[$type]->count())
                <div class="app-card space-y-md">
                    <h4 class="text-headline-md font-semibold text-on-surface">{{ $type }}</h4>
                    <div class="overflow-x-auto">
                        <div class="app-table-wrapper">
<table class="table table-sm">
                            <thead>
                                <tr class="border-b border-surface-border text-on-surface-variant">
                                    <th>Kode</th>
                                    <th>Nama Akun</th>
                                    <th>Cash Flow</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accounts[$type] as $account)
                                    <tr class="border-b border-surface-border" x-data="{ editing: false }" x-cloak>

                                        {{-- View mode --}}
                                        <td x-show="!editing" class="text-on-surface-variant text-body-sm font-mono">{{ $account->code }}</td>
                                        <td x-show="!editing" class="text-on-surface">{{ $account->name }}</td>
                                        <td x-show="!editing">
                                            @if($account->cash_flow_category)
                                                <span class="badge badge-soft text-xs">
                                                    {{ match($account->cash_flow_category) {
                                                        'cash'      => 'Kas & Bank',
                                                        'operating' => 'Operasi',
                                                        'investing' => 'Investasi',
                                                        'financing' => 'Pendanaan',
                                                        default     => $account->cash_flow_category,
                                                    } }}
                                                </span>
                                            @else
                                                <span class="text-on-surface-variant text-xs">—</span>
                                            @endif
                                        </td>
                                        <td x-show="!editing" class="text-right">
                                            <div class="flex justify-end gap-xs">
                                                <button aria-label="Edit" @click="editing = true" class="btn btn-ghost btn-xs gap-xs">
                                                    <span class="material-symbols-outlined text-[14px]">edit</span>
                                                </button>
                                                <form method="POST" action="{{ route('finance.accounts.destroy', $account) }}"
                                                    onsubmit="return confirm('Hapus akun {{ $account->code }}?')">
                                                    @csrf @method('DELETE')
                                                    <button aria-label="Hapus" type="submit" class="btn btn-ghost btn-xs text-error">
                                                        <span class="material-symbols-outlined text-[14px]">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                        {{-- Edit mode --}}
                                        <td x-show="editing" colspan="4">
                                            <form method="POST" action="{{ route('finance.accounts.update', $account) }}"
                                                class="flex gap-sm items-end py-xs flex-wrap">
                                                @csrf @method('PATCH')
                                                <div class="fieldset">
                                                    <label class="fieldset-legend text-on-surface">Kode</label>
                                                    <input type="text" name="code" value="{{ $account->code }}"
                                                        class="input w-28" required />
                                                </div>
                                                <div class="fieldset flex-1">
                                                    <label class="fieldset-legend text-on-surface">Nama</label>
                                                    <input type="text" name="name" value="{{ $account->name }}"
                                                        class="input w-full" required />
                                                </div>
                                                <div class="fieldset">
                                                    <label class="fieldset-legend text-on-surface">Tipe</label>
                                                    <select name="type" class="select">
                                                        @foreach(['Asset','Liability','Equity','Revenue','Expense'] as $t)
                                                            <option value="{{ $t }}" @selected($account->type === $t)>{{ $t }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="fieldset">
                                                    <label class="fieldset-legend text-on-surface">Cash Flow</label>
                                                    <select name="cash_flow_category" class="select">
                                                        <option value="">— Tidak ada —</option>
                                                        <option value="cash"      @selected($account->cash_flow_category === 'cash')>Kas & Bank</option>
                                                        <option value="operating" @selected($account->cash_flow_category === 'operating')>Operasi</option>
                                                        <option value="investing" @selected($account->cash_flow_category === 'investing')>Investasi</option>
                                                        <option value="financing" @selected($account->cash_flow_category === 'financing')>Pendanaan</option>
                                                    </select>
                                                </div>
                                                <div class="flex gap-xs mb-xs">
                                                    <button type="submit"
                                                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90 btn-sm">
                                                        Simpan
                                                    </button>
                                                    <button type="button" @click="editing = false"
                                                        class="btn btn-ghost btn-sm">
                                                        Batal
                                                    </button>
                                                </div>
                                            </form>
                                        </td>

                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
</div>
                    </div>
                </div>
            @endif
        @endforeach

    </div>

    {{-- Modal Tambah Akun --}}
    <dialog id="modal-add" class="modal">
        <div class="modal-box" style="max-width: 28rem">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Akun</h3>
            <form method="POST" action="{{ route('finance.accounts.store') }}" class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Kode Akun</label>
                    <input type="text" name="code" class="input w-full" placeholder="1001" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Akun</label>
                    <input type="text" name="name" class="input w-full" placeholder="Kas" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Tipe</label>
                    <select name="type" class="select w-full">
                        @foreach(['Asset','Liability','Equity','Revenue','Expense'] as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Kategori Cash Flow</label>
                    <select name="cash_flow_category" class="select w-full">
                        <option value="">— Tidak ada —</option>
                        <option value="cash">Kas & Bank</option>
                        <option value="operating">Operasi</option>
                        <option value="investing">Investasi</option>
                        <option value="financing">Pendanaan</option>
                    </select>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-add').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

</x-app-layout>
