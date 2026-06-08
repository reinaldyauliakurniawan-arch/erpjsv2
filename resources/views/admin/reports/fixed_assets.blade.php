<x-app-layout>
<x-slot name="title">Daftar Aset Tetap</x-slot>

<div class="p-lg space-y-lg" style="max-width:72rem">

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
    <div class="flex items-center justify-between gap-md flex-wrap">
        <div>
            <h1 class="text-headline-lg font-semibold text-on-surface">Daftar Aset Tetap</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Kelola aset dan hitung penyusutan otomatis (metode garis lurus)</p>
        </div>
        <div class="flex gap-sm">
            <form method="POST" action="{{ route('finance.assets.generate-depreciation') }}">
                @csrf
                <input type="hidden" name="period" value="{{ now()->format('Y-m-d') }}">
                <button type="submit" class="btn btn-ghost border border-surface-border gap-sm"
                    onclick="return confirm('Generate jurnal penyusutan bulan ' + new Date().toLocaleDateString('id-ID', {month:'long', year:'numeric'}) + '?')">
                    <span class="material-symbols-outlined text-[18px]">autorenew</span>
                    Generate Penyusutan Bulan Ini
                </button>
            </form>
            <button onclick="document.getElementById('modal-add').showModal()"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Aset
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns:repeat(3,1fr)">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Harga Perolehan</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalCost,0,',','.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Akumulasi Penyusutan</p>
            <p class="text-xl font-bold text-error mt-xs">Rp {{ number_format($totalAccumulated,0,',','.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Total Nilai Buku</p>
            <p class="text-xl font-bold text-on-surface mt-xs">Rp {{ number_format($totalBookValue,0,',','.') }}</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
        <div class="flex gap-sm flex-wrap items-center justify-between">
            <input type="text" id="search-asset" placeholder="Cari nama atau kategori..."
                class="input input-sm flex-1" oninput="applySearch(this.value)" />
            <label class="flex items-center gap-sm text-sm text-on-surface-variant cursor-pointer">
                <input type="checkbox" id="show-inactive" class="checkbox checkbox-sm" onchange="toggleInactive(this.checked)" />
                Tampilkan aset nonaktif
            </label>
        </div>
        <div id="assets-table"></div>
    </div>

</div>

{{-- Modal Tambah --}}
<dialog id="modal-add" class="modal">
    <div class="modal-box" style="max-width:36rem">
        <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Aset Tetap</h3>
        <form method="POST" action="{{ route('finance.assets.store') }}" class="space-y-md">
            @csrf
            <div class="grid grid-cols-2 gap-md">
                <div class="fieldset col-span-2">
                    <label class="fieldset-legend text-on-surface">Nama Aset</label>
                    <input type="text" name="name" class="input w-full" placeholder="Laptop Dell XPS 13" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Kategori</label>
                    <select name="category" class="select w-full">
                        <option>Peralatan</option>
                        <option>Kendaraan</option>
                        <option>Bangunan</option>
                        <option>Furnitur</option>
                        <option>Lainnya</option>
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Tanggal Perolehan</label>
                    <input type="date" name="acquired_at" class="input w-full" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Harga Perolehan (Rp)</label>
                    <input type="number" name="cost" class="input w-full" placeholder="10000000" min="0" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nilai Sisa (Rp)</label>
                    <input type="number" name="salvage_value" class="input w-full" placeholder="0" min="0" value="0" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Umur Ekonomis (bulan)</label>
                    <input type="number" name="useful_life" class="input w-full" placeholder="36" min="1" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Metode Penyusutan</label>
                    <select name="depreciation_method" class="select w-full">
                        <option value="straight_line">Garis Lurus</option>
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Akun Beban Penyusutan</label>
                    <select name="expense_account_id" class="select w-full">
                        <option value="">— Pilih akun —</option>
                        @foreach($accounts->where('type', 'Expense') as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Akun Akum. Penyusutan</label>
                    <select name="accumulated_account_id" class="select w-full">
                        <option value="">— Pilih akun —</option>
                        @foreach($accounts->where('type', 'Asset') as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset col-span-2">
                    <label class="fieldset-legend text-on-surface">Catatan</label>
                    <textarea name="notes" class="textarea w-full" rows="2" placeholder="Opsional..."></textarea>
                </div>
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

{{-- Modal Edit --}}
<dialog id="modal-edit" class="modal">
    <div class="modal-box" style="max-width:36rem">
        <h3 class="text-headline-md font-semibold text-on-surface mb-md">Edit Aset Tetap</h3>
        <form id="form-edit" method="POST" class="space-y-md">
            @csrf @method('PATCH')
            <div class="grid grid-cols-2 gap-md">
                <div class="fieldset col-span-2">
                    <label class="fieldset-legend text-on-surface">Nama Aset</label>
                    <input type="text" name="name" id="edit-name" class="input w-full" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Kategori</label>
                    <select name="category" id="edit-category" class="select w-full">
                        <option>Peralatan</option>
                        <option>Kendaraan</option>
                        <option>Bangunan</option>
                        <option>Furnitur</option>
                        <option>Lainnya</option>
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Tanggal Perolehan</label>
                    <input type="date" name="acquired_at" id="edit-acquired-at" class="input w-full" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Harga Perolehan (Rp)</label>
                    <input type="number" name="cost" id="edit-cost" class="input w-full" min="0" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nilai Sisa (Rp)</label>
                    <input type="number" name="salvage_value" id="edit-salvage" class="input w-full" min="0" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Umur Ekonomis (bulan)</label>
                    <input type="number" name="useful_life" id="edit-useful-life" class="input w-full" min="1" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Metode Penyusutan</label>
                    <select name="depreciation_method" class="select w-full">
                        <option value="straight_line">Garis Lurus</option>
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Akun Beban Penyusutan</label>
                    <select name="expense_account_id" id="edit-expense-account" class="select w-full">
                        <option value="">— Pilih akun —</option>
                        @foreach($accounts->where('type', 'Expense') as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Akun Akum. Penyusutan</label>
                    <select name="accumulated_account_id" id="edit-accumulated-account" class="select w-full">
                        <option value="">— Pilih akun —</option>
                        @foreach($accounts->where('type', 'Asset') as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset col-span-2">
                    <label class="fieldset-legend text-on-surface">Catatan</label>
                    <textarea name="notes" id="edit-notes" class="textarea w-full" rows="2"></textarea>
                </div>
                <div class="fieldset col-span-2">
                    <label class="flex items-center gap-sm cursor-pointer">
                        <input type="checkbox" name="is_active" id="edit-is-active" value="1" class="checkbox checkbox-sm" />
                        <span class="text-on-surface text-sm">Aset masih aktif</span>
                    </label>
                </div>
            </div>
            <div class="modal-action">
                <button type="button" onclick="document.getElementById('modal-edit').close()"
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

{{-- Delete form (hidden) --}}
<form id="form-delete" method="POST" style="display:none">
    @csrf @method('DELETE')
</form>

<script>
const assets = @json($assets->values());

function fmt(n) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));
}

function pct(accumulated, cost) {
    if (!cost) return '0%';
    return Math.min(100, Math.round(accumulated / cost * 100)) + '%';
}

document.addEventListener('DOMContentLoaded', function () {
    window.assetTable = new Tabulator('#assets-table', {
        data: assets,
        layout: 'fitColumns',
        pagination: 'local',
        paginationSize: 15,
        placeholder: 'Belum ada aset.',
        initialSort: [{ column: 'acquired_at', dir: 'desc' }],
        initialFilter: [{ field: 'is_active', type: '=', value: true }],
        columns: [
            {
                title: 'Nama Aset',
                field: 'name',
                minWidth: 180,
                widthGrow: 2,
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    const badges = [];
                    if (row.status === 'fully_depreciated') badges.push(`<span class="badge badge-soft text-xs">Lunas</span>`);
                    if (!row.is_active) badges.push(`<span class="badge badge-soft text-xs text-error">Nonaktif</span>`);
                    return `<span class="font-medium">${cell.getValue()}</span> ${badges.join(' ')}`;
                }
            },
            {
                title: 'Kategori',
                field: 'category',
                width: 110,
                formatter: cell => `<span class="badge badge-soft text-xs">${cell.getValue()}</span>`
            },
            {
                title: 'Tgl Perolehan',
                field: 'acquired_at',
                width: 120,
                formatter: function(cell) {
                    const d = new Date(cell.getValue());
                    return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
                }
            },
            {
                title: 'Harga Perolehan',
                field: 'cost',
                width: 150,
                hozAlign: 'right',
                headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: 'Akum. Penyusutan',
                field: 'accumulated_depreciation',
                width: 190,
                hozAlign: 'right',
                headerHozAlign: 'right',
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    const p = pct(cell.getValue(), row.cost);
                    return `
                        <div class="flex flex-col items-end gap-xs">
                            <span class="text-error text-xs font-mono">${fmt(cell.getValue())}</span>
                            <div class="w-full bg-surface-border rounded-full" style="height:4px">
                                <div class="bg-error rounded-full" style="height:4px;width:${p}"></div>
                            </div>
                        </div>`;
                }
            },
            {
                title: 'Nilai Buku',
                field: 'book_value',
                width: 140,
                hozAlign: 'right',
                headerHozAlign: 'right',
                formatter: cell => `<span class="font-semibold">${fmt(cell.getValue())}</span>`
            },
            {
                title: 'Susut/Bln',
                field: 'monthly_depreciation',
                width: 120,
                hozAlign: 'right',
                headerHozAlign: 'right',
                formatter: cell => fmt(cell.getValue()),
            },
            {
                title: '',
                field: 'id',
                width: 80,
                headerSort: false,
                hozAlign: 'center',
                formatter: function(cell) {
                    const id = cell.getValue();
                    return `
                        <div class="flex gap-xs justify-center">
                            <button onclick="openEdit(${id})" class="btn btn-ghost btn-xs">
                                <span class="material-symbols-outlined text-[14px]">edit</span>
                            </button>
                            <button onclick="confirmDelete(${id})" class="btn btn-ghost btn-xs text-error">
                                <span class="material-symbols-outlined text-[14px]">delete</span>
                            </button>
                        </div>`;
                }
            },
        ],
    });
});

function buildFilters() {
    const showInactive = document.getElementById('show-inactive').checked;
    const searchVal    = document.getElementById('search-asset').value;
    const filters = [];
    if (!showInactive) filters.push({ field: 'is_active', type: '=', value: true });
    if (searchVal)     filters.push([
        { field: 'name',     type: 'like', value: searchVal },
        { field: 'category', type: 'like', value: searchVal },
    ]);
    window.assetTable.setFilter(filters);
}

function applySearch(val) {
    buildFilters();
}

function toggleInactive(show) {
    buildFilters();
}

function openEdit(id) {
    const a = assets.find(x => x.id === id);
    if (!a) return;
    document.getElementById('form-edit').action = `/finance/assets/${id}`;
    document.getElementById('edit-name').value         = a.name;
    document.getElementById('edit-acquired-at').value  = a.acquired_at;
    document.getElementById('edit-cost').value         = a.cost;
    document.getElementById('edit-salvage').value      = a.salvage_value;
    document.getElementById('edit-useful-life').value  = a.useful_life;
    document.getElementById('edit-notes').value        = a.notes ?? '';
    document.getElementById('edit-is-active').checked = a.is_active;

    const catSel = document.getElementById('edit-category');
    [...catSel.options].forEach(o => o.selected = o.value === a.category);

    const expSel = document.getElementById('edit-expense-account');
    [...expSel.options].forEach(o => o.selected = parseInt(o.value) === a.expense_account_id);

    const accSel = document.getElementById('edit-accumulated-account');
    [...accSel.options].forEach(o => o.selected = parseInt(o.value) === a.accumulated_account_id);

    document.getElementById('modal-edit').showModal();
}

function confirmDelete(id) {
    if (!confirm('Hapus aset ini?')) return;
    const form = document.getElementById('form-delete');
    form.action = `/finance/assets/${id}`;
    form.submit();
}
</script>
</x-app-layout>
