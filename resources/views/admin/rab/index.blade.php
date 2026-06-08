<x-app-layout>
<x-slot name="title">RAB - Rencana Anggaran Biaya</x-slot>

<div class="p-lg space-y-md">

    {{-- Flash --}}
    <div id="alert-box" class="hidden"></div>

    {{-- Header --}}
    <div class="flex items-center justify-between gap-md">
        <div>
            <h3 class="text-headline-lg font-semibold text-on-surface">Rencana Anggaran Biaya (RAB)</h3>
            <p class="text-sm text-on-surface-variant mt-xs">Kelola anggaran divisi per kuartal dalam satu tampilan</p>
        </div>
        <div class="flex gap-sm items-center">
            <form method="GET" action="{{ route('finance.rab.index') }}" class="flex gap-sm items-center">
                <select name="year" class="select select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <button onclick="addRow()" class="btn btn-ghost btn-sm gap-xs">
                <span class="material-symbols-outlined text-[16px]">add</span>
                Tambah Baris
            </button>
            <button onclick="saveAll()" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Simpan Semua
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-md" style="grid-template-columns: repeat(2, 1fr)">
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Anggaran Tahun Berjalan ({{ $year }})</p>
            <p class="text-xl font-bold text-on-surface mt-xs" id="card-total">Rp {{ number_format($totalBudget, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-xs text-on-surface-variant uppercase tracking-wide">Anggaran Kuartal Berjalan (Q{{ ceil(now()->month / 3) }})</p>
            <p class="text-xl font-bold text-on-surface mt-xs" id="card-quarter">Rp {{ number_format($budgetQuarter, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
        <div id="rab-table"></div>
    </div>

</div>

<script>
const DIVISIONS = @json($divisions);
const ACCOUNTS  = @json($accounts);
const CURRENT_YEAR = {{ $year }};
const STORE_URL  = "{{ route('finance.rab.store') }}";
const CSRF       = "{{ csrf_token() }}";

function fmt(n) {
    if (!n || n == 0) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
}

function recalcTotal(row) {
    const d = row.getData();
    const total = (parseInt(d.q1)||0) + (parseInt(d.q2)||0) + (parseInt(d.q3)||0) + (parseInt(d.q4)||0);
    row.update({ total });
}

const CURRENT_QUARTER = {{ ceil(now()->month / 3) }};

function updateCards() {
    const rows = window.rabTable.getData();
    const grand = rows.reduce((s, r) => s + (parseInt(r.total)||0), 0);
    const qKey = 'q' + CURRENT_QUARTER;
    const grandQ = rows.reduce((s, r) => s + (parseInt(r[qKey])||0), 0);
    document.getElementById('card-total').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(grand);
    document.getElementById('card-quarter').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(grandQ);
}

function showAlert(msg, type) {
    const box = document.getElementById('alert-box');
    box.className = `alert alert-${type} alert-soft text-sm`;
    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined';
    icon.textContent = type === 'success' ? 'check_circle' : 'error';
    const text = document.createElement('span');
    text.textContent = msg;
    box.innerHTML = '';
    box.appendChild(icon);
    box.appendChild(text);
    box.classList.remove('hidden');
    setTimeout(() => box.classList.add('hidden'), 3500);
}

function addRow() {
    window.rabTable.addRow({
        id: null,
        division: DIVISIONS[0],
        account_name: '',
        activity: '',
        q1: 0, q2: 0, q3: 0, q4: 0, total: 0
    });
}

async function saveAll() {
    const rows = window.rabTable.getData().map(r => ({
        division:     r.division,
        account_name: (() => { const a = ACCOUNTS.find(x => x.code === r.account_code); return a ? a.name : (r.account_name || ''); })(),
        activity:     r.activity,
        q1: parseInt(r.q1)||0,
        q2: parseInt(r.q2)||0,
        q3: parseInt(r.q3)||0,
        q4: parseInt(r.q4)||0,
    }));

    const res  = await fetch(STORE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ year: CURRENT_YEAR, rows }),
    });
    const data = await res.json();
    if (data.success) {
        showAlert(data.message, 'success');
        updateCards();
    } else {
        showAlert('Gagal menyimpan. Coba lagi.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    window.rabTable = new Tabulator('#rab-table', {
        data: @json($rows->values()),
        layout: 'fitColumns',
        pagination: 'local',
        paginationSize: 30,
        placeholder: 'Belum ada data anggaran. Klik "Tambah Baris" untuk mulai.',
        columns: [
            {
                title: 'Divisi', field: 'division', minWidth: 150, editor: 'list',
                editorParams: { values: DIVISIONS, autocomplete: true, allowEmpty: false },
                cellEdited: updateCards,
            },
            {
                title: 'Kode Akun', field: 'account_code', width: 160,
                editor: 'list',
                editorParams: {
                    values: Object.fromEntries([['', '— Pilih —'], ...ACCOUNTS.map(a => [a.code, a.code + ' — ' + a.name])]),
                    autocomplete: true,
                    allowEmpty: true,
                    listOnEmpty: true,
                },
                formatter: cell => {
                    const code = cell.getValue();
                    if (!code) return '<span class="text-on-surface-variant text-xs">— pilih —</span>';
                    const acc = ACCOUNTS.find(a => a.code === code);
                    return acc ? `<span class="badge badge-soft badge-ghost text-xs">${code}</span>` : code;
                },
                cellEdited: updateCards,
            },
            {
                title: 'Jenis / Kegiatan', field: 'activity', minWidth: 200, editor: 'input',
                cellEdited: updateCards,
            },
            {
                title: 'Q1 (Rp)', field: 'q1', width: 130, hozAlign: 'right', headerHozAlign: 'right',
                editor: 'number', editorParams: { min: 0 },
                formatter: cell => fmt(cell.getValue()),
                cellEdited: cell => { recalcTotal(cell.getRow()); updateCards(); },
            },
            {
                title: 'Q2 (Rp)', field: 'q2', width: 130, hozAlign: 'right', headerHozAlign: 'right',
                editor: 'number', editorParams: { min: 0 },
                formatter: cell => fmt(cell.getValue()),
                cellEdited: cell => { recalcTotal(cell.getRow()); updateCards(); },
            },
            {
                title: 'Q3 (Rp)', field: 'q3', width: 130, hozAlign: 'right', headerHozAlign: 'right',
                editor: 'number', editorParams: { min: 0 },
                formatter: cell => fmt(cell.getValue()),
                cellEdited: cell => { recalcTotal(cell.getRow()); updateCards(); },
            },
            {
                title: 'Q4 (Rp)', field: 'q4', width: 130, hozAlign: 'right', headerHozAlign: 'right',
                editor: 'number', editorParams: { min: 0 },
                formatter: cell => fmt(cell.getValue()),
                cellEdited: cell => { recalcTotal(cell.getRow()); updateCards(); },
            },
            {
                title: 'Total/Tahun', field: 'total', width: 150, hozAlign: 'right', headerHozAlign: 'right',
                formatter: cell => `<strong>${fmt(cell.getValue())}</strong>`,
            },
            {
                title: '', field: 'id', width: 50, hozAlign: 'center', headerSort: false,
                formatter: () => `<button class="btn btn-ghost btn-xs text-error"><span class="material-symbols-outlined text-[16px]">delete</span></button>`,
                cellClick: (e, cell) => { cell.getRow().delete(); updateCards(); },
            },
        ],
    });

    updateCards();
});
</script>
</x-app-layout>
