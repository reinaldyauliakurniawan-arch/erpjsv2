<x-app-layout>
    <x-slot name="title">Akumulasi Jurnal Penyesuaian</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 72rem">

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if($errors->any())
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface min-w-0 shrink truncate">Akumulasi Jurnal Penyesuaian</h3>
            <div class="flex gap-sm flex-shrink-0">
                <button type="button" onclick="document.getElementById('modal-generate').showModal()"
                    class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">autorenew</span>
                    Generate Otomatis
                </button>
                <button type="button" onclick="document.getElementById('modal-manual').showModal()"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm btn-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tambah Manual
                </button>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-md" style="max-width: 28rem">
            <div class="app-card p-md flex items-center gap-md">
                <div class="p-xs bg-warning/10 rounded-lg">
                    <span class="material-symbols-outlined text-warning text-[20px]">pending_actions</span>
                </div>
                <div>
                    <p class="text-body-xs text-on-surface-variant">Draft</p>
                    <p class="text-headline-sm font-semibold text-on-surface">{{ $stats['draft'] }}</p>
                </div>
            </div>
            <div class="app-card p-md flex items-center gap-md">
                <div class="p-xs bg-success/10 rounded-lg">
                    <span class="material-symbols-outlined text-success text-[20px]">done_all</span>
                </div>
                <div>
                    <p class="text-body-xs text-on-surface-variant">Terposting</p>
                    <p class="text-headline-sm font-semibold text-on-surface">{{ $stats['posted'] }}</p>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="app-card space-y-md">
            {{-- Toolbar --}}
            <div class="flex flex-col sm:flex-row gap-sm flex-wrap">
                <select id="filter-type" class="select select-sm w-40">
                    <option value="">Semua Tipe</option>
                    <option value="depreciation">Depresiasi</option>
                    <option value="amortization">Amortisasi</option>
                    <option value="deferred_revenue">Deferred Revenue</option>
                    <option value="manual">Manual</option>
                </select>
                <select id="filter-status" class="select select-sm w-36">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="posted">Posted</option>
                </select>
                <input type="month" id="filter-period-from" class="input input-sm w-40" placeholder="Dari">
                <input type="month" id="filter-period-to" class="input input-sm w-40" placeholder="Sampai">
                <button type="button" onclick="applyFilter()" class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90">Filter</button>
                <button type="button" onclick="clearFilter()" class="btn btn-sm btn-ghost">Reset</button>
            </div>

            <div id="ajp-table"></div>
        </div>
    </div>

    {{-- Modal: Generate Otomatis --}}
    <dialog id="modal-generate" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Generate AJP Otomatis</h3>
            <p class="text-body-sm text-on-surface-variant mb-md">
                Sistem akan otomatis menghitung depresiasi aset tetap, amortisasi prepaid, dan pengakuan deferred revenue untuk periode yang dipilih.
            </p>
            <form method="POST" action="{{ route('finance.adjusting-journals.generate') }}">
                @csrf
                <div class="fieldset mb-md">
                    <label class="fieldset-legend text-on-surface">Periode</label>
                    <input type="month" name="period" class="input w-full" required
                        value="{{ now()->format('Y-m') }}">
                    <p class="text-body-xs text-on-surface-variant mt-1">Jurnal akan diposting ke akhir bulan periode yang dipilih.</p>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-generate').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                        <span class="material-symbols-outlined text-[18px]">autorenew</span>
                        Generate
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal: Tambah Manual --}}
    <dialog id="modal-manual" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Jurnal Penyesuaian Manual</h3>
            <form method="POST" action="{{ route('finance.adjusting-journals.store') }}" id="form-manual">
                @csrf
                <div class="space-y-md">
                    <div class="grid grid-cols-2 gap-md">
                        <div class="fieldset">
                            <label class="fieldset-legend text-on-surface">Periode</label>
                            <input type="month" name="period" class="input w-full" required value="{{ now()->format('Y-m') }}">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-legend text-on-surface">Deskripsi</label>
                            <input type="text" name="description" class="input w-full" required placeholder="cth: Penyesuaian sewa gedung">
                        </div>
                    </div>

                    {{-- Journal Items --}}
                    <div>
                        <div class="flex items-center justify-between mb-sm">
                            <label class="text-body-sm font-semibold text-on-surface">Entri Akun</label>
                            <button type="button" onclick="addRow()" class="btn btn-xs btn-ghost gap-xs">
                                <span class="material-symbols-outlined text-[14px]">add</span> Tambah Baris
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <div class="app-table-wrapper">
<table class="table table-xs w-full">
                                <thead>
                                    <tr class="text-on-surface-variant">
                                        <th>Akun</th>
                                        <th class="text-right">Debit (Rp)</th>
                                        <th class="text-right">Kredit (Rp)</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="items-body">
                                    <tr class="item-row">
                                        <td>
                                            <select name="items[0][account_id]" class="select select-xs w-full" required>
                                                <option value="">Pilih akun...</option>
                                                @foreach($accounts as $acc)
                                                    <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="number" name="items[0][debit]" class="input input-xs w-full text-right" value="0" min="0" step="0.01" oninput="updateTotals()"></td>
                                        <td><input type="number" name="items[0][credit]" class="input input-xs w-full text-right" value="0" min="0" step="0.01" oninput="updateTotals()"></td>
                                        <td><button aria-label="Hapus" type="button" onclick="removeRow(this)" class="btn btn-ghost btn-xs text-error"><span class="material-symbols-outlined text-[14px]">delete</span></button></td>
                                    </tr>
                                    <tr class="item-row">
                                        <td>
                                            <select name="items[1][account_id]" class="select select-xs w-full" required>
                                                <option value="">Pilih akun...</option>
                                                @foreach($accounts as $acc)
                                                    <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="number" name="items[1][debit]" class="input input-xs w-full text-right" value="0" min="0" step="0.01" oninput="updateTotals()"></td>
                                        <td><input type="number" name="items[1][credit]" class="input input-xs w-full text-right" value="0" min="0" step="0.01" oninput="updateTotals()"></td>
                                        <td><button aria-label="Hapus" type="button" onclick="removeRow(this)" class="btn btn-ghost btn-xs text-error"><span class="material-symbols-outlined text-[14px]">delete</span></button></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="font-semibold text-on-surface">
                                        <td class="text-right text-body-xs text-on-surface-variant">Total</td>
                                        <td class="text-right text-body-sm" id="total-debit">0</td>
                                        <td class="text-right text-body-sm" id="total-credit">0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
</div>
                        </div>
                        <p id="balance-warning" class="text-body-xs text-error mt-1 hidden">⚠ Total debit dan kredit harus sama.</p>
                    </div>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-manual').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" id="btn-submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan & Post
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        @if($errors->any())
            document.getElementById('modal-manual').showModal();
        @endif

        window.ajpTable = new Tabulator('#ajp-table', {
            ajaxURL: '{{ route("finance.adjusting-journals.data") }}',
            ajaxParams: {},
            ajaxRequestFunc: function (url, config, params) {
                const query = new URLSearchParams({
                    page:        params.page || 1,
                    size:        params.size || 20,
                    type:        params.type || '',
                    status:      params.status || '',
                    period_from: params.period_from || '',
                    period_to:   params.period_to || '',
                });
                return fetch(`${url}?${query}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(r => r.json()).then(data => ({ data: data.data, last_page: data.last_page }));
            },
            pagination: true,
            paginationMode: 'remote',
            paginationSize: 20,
            paginationCounter: 'rows',
            ajaxResponse: function (url, params, response) {
                return { data: response.data, last_page: response.last_page };
            },
            layout: 'fitColumns',
            placeholder: 'Belum ada jurnal penyesuaian.',
            rowFormatter: function(row) {
                const data = row.getData();
                if (data.status === 'draft') {
                    row.getElement().style.opacity = '0.65';
                }
            },
            columns: [
                {
                    title: 'Periode',
                    field: 'period',
                    width: 130,
                    headerSort: false,
                },
                {
                    title: 'Referensi',
                    field: 'reference',
                    width: 160,
                    headerSort: false,
                    formatter: function (cell) {
                        return `<span class="badge badge-soft font-mono">${cell.getValue()}</span>`;
                    },
                },
                {
                    title: 'Deskripsi',
                    field: 'description',
                    headerSort: false,
                    widthGrow: 2,
                },
                {
                    title: 'Tipe',
                    field: 'type_label',
                    width: 150,
                    headerSort: false,
                    formatter: function (cell) {
                        const type = cell.getRow().getData().type;
                        const colors = {
                            depreciation:     'badge-warning',
                            amortization:     'badge-neutral',
                            deferred_revenue: 'badge-secondary',
                            manual:           'badge-ghost',
                        };
                        return `<span class="badge badge-soft ${colors[type] ||''} badge-sm">${cell.getValue()}</span>`;
                    },
                },
                {
                    title: 'Debit (Rp)',
                    field: 'debit',
                    width: 150,
                    headerSort: false,
                    hozAlign: 'right',
                    headerHozAlign: 'right',
                    formatter: function (cell) {
                        return 'Rp ' + parseFloat(cell.getValue()).toLocaleString('id-ID');
                    },
                },
                {
                    title: 'Kredit (Rp)',
                    field: 'credit',
                    width: 150,
                    headerSort: false,
                    hozAlign: 'right',
                    headerHozAlign: 'right',
                    formatter: function (cell) {
                        return 'Rp ' + parseFloat(cell.getValue()).toLocaleString('id-ID');
                    },
                },
                {
                    title: 'Status',
                    field: 'status',
                    width: 110,
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: function (cell) {
                        const val = cell.getValue();
                        const cls = val === 'posted' ? 'badge-success' : 'badge-warning';
                        const label = val === 'posted' ? 'Posted' : 'Draft';
                        return `<span class="badge badge-soft ${cls} badge-sm">${label}</span>`;
                    },
                },
                {
                    title: 'Aksi',
                    field: 'id',
                    width: 100,
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: function (cell) {
                        const data = cell.getRow().getData();
                        let btns = '';
                        if (data.show_url) {
                            btns += `<a href="${data.show_url}" class="btn btn-ghost btn-xs" title="Lihat Jurnal">
                                <span class="material-symbols-outlined text-[14px]">visibility</span>
                            </a>`;
                        }
                        if (data.status === 'draft') {
                            btns += `<button type="button" onclick="deleteAjp(${data.id})" class="btn btn-ghost btn-xs text-error" title="Hapus">
                                <span class="material-symbols-outlined text-[14px]">delete</span>
                            </button>`;
                        }
                        return `<div class="flex items-center justify-center gap-1">${btns}</div>`;
                    },
                },
            ],
        });
    });

    function applyFilter() {
        const type       = document.getElementById('filter-type').value;
        const status     = document.getElementById('filter-status').value;
        const periodFrom = document.getElementById('filter-period-from').value;
        const periodTo   = document.getElementById('filter-period-to').value;
        window.ajpTable.setData('{{ route("finance.adjusting-journals.data") }}', {
            type, status,
            period_from: periodFrom ? periodFrom + '-01' : '',
            period_to:   periodTo   ? periodTo   + '-31' : '',
        });
    }

    function clearFilter() {
        ['filter-type','filter-status','filter-period-from','filter-period-to']
            .forEach(id => document.getElementById(id).value = '');
        window.ajpTable.setData('{{ route("finance.adjusting-journals.data") }}', {});
    }

    // ── Dynamic rows untuk modal manual ──────────────────────────────────────
    let rowIndex = 2;
    const accountOptions = `@foreach($accounts as $acc)<option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>@endforeach`;

    function addRow() {
        const tbody = document.getElementById('items-body');
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][account_id]" class="select select-xs w-full" required>
                    <option value="">Pilih akun...</option>
                    ${accountOptions}
                </select>
            </td>
            <td><input type="number" name="items[${rowIndex}][debit]" class="input input-xs w-full text-right" value="0" min="0" step="0.01" oninput="updateTotals()"></td>
            <td><input type="number" name="items[${rowIndex}][credit]" class="input input-xs w-full text-right" value="0" min="0" step="0.01" oninput="updateTotals()"></td>
            <td><button aria-label="Hapus" type="button" onclick="removeRow(this)" class="btn btn-ghost btn-xs text-error"><span class="material-symbols-outlined text-[14px]">delete</span></button></td>
        `;
        tbody.appendChild(tr);
        rowIndex++;
    }

    function removeRow(btn) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length <= 2) return;
        btn.closest('tr').remove();
        updateTotals();
    }

    function updateTotals() {
        let totalDebit = 0, totalCredit = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            totalDebit  += parseFloat(row.querySelector('input[name*="debit"]').value)  || 0;
            totalCredit += parseFloat(row.querySelector('input[name*="credit"]').value) || 0;
        });
        document.getElementById('total-debit').textContent  = 'Rp ' + totalDebit.toLocaleString('id-ID');
        document.getElementById('total-credit').textContent = 'Rp ' + totalCredit.toLocaleString('id-ID');
        const balanced = Math.abs(totalDebit - totalCredit) < 0.01;
        document.getElementById('balance-warning').classList.toggle('hidden', balanced);
        document.getElementById('btn-submit').disabled = !balanced;
    }

    function deleteAjp(id) {
        if (!confirm('Hapus jurnal penyesuaian ini?')) return;
        fetch(`/finance/adjusting-journals/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        }).then(r => r.json()).then(() => {
            window.ajpTable.replaceData();
        });
    }
    </script>

</x-app-layout>
