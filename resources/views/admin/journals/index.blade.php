<x-app-layout>
    <x-slot name="title">Journals</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 72rem">

        {{-- Flash --}}
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
            <h3 class="text-headline-lg font-semibold text-on-surface">Journals</h3>
            <div class="flex gap-sm">
                <a href="{{ route('finance.exports.finance-template', 'journals') }}"
                    class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Template
                </a>
                <a href="{{ route('finance.exports.journals') }}"
                    class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Export
                </a>
                <button type="button" onclick="document.getElementById('modal-import').showModal()"
                    class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">upload_file</span>
                    Import
                </button>
                <a href="{{ route('finance.journals.create') }}"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    New Journal
                </a>
            </div>
        </div>

        {{-- Table --}}
        <div class="app-card space-y-md">
            {{-- Toolbar --}}
            <div class="flex flex-col sm:flex-row gap-sm">
                <input type="text" id="search-journal" placeholder="Cari deskripsi atau referensi..."
                    class="input input-sm flex-1" />
                <input type="date" id="date-from" class="input input-sm w-40" />
                <input type="date" id="date-to" class="input input-sm w-40" />
                <button type="button" onclick="applyFilter()" class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90">
                    Filter
                </button>
                <button type="button" onclick="clearFilter()" class="btn btn-sm btn-ghost">
                    Reset
                </button>
            </div>

            {{-- Tabulator --}}
            <div id="journals-table"></div>
        </div>

    </div>

    {{-- Modal Import --}}
    <dialog id="modal-import" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Import Journals</h3>
            <p class="text-body-sm text-on-surface-variant mb-md">Format CSV: date, description, reference, account_code, debit, credit</p>
            <form method="POST" action="{{ route('finance.imports.journals') }}" enctype="multipart/form-data"
                class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-import').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                        <span class="material-symbols-outlined text-[18px]">upload</span>
                        Import
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        @if(session('error'))
            document.getElementById('modal-import').showModal();
        @endif

        window.journalTable = new Tabulator('#journals-table', {
            ajaxURL: '{{ route("finance.journals.data") }}',
            ajaxParams: {},
            ajaxRequestFunc: function (url, config, params) {
                const query = new URLSearchParams({
                    page:      params.page || 1,
                    size:      params.size || 20,
                    search:    params.search || '',
                    date_from: params.date_from || '',
                    date_to:   params.date_to || '',
                });
                return fetch(`${url}?${query}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                }).then(r => r.json()).then(data => ({
                    data:      data.data,
                    last_page: data.last_page,
                }));
            },
            pagination: true,
            paginationMode: 'remote',
            paginationSize: 20,
            paginationCounter: 'rows',
            ajaxResponse: function (url, params, response) {
                return { data: response.data, last_page: response.last_page };
            },
            layout: 'fitColumns',
            placeholder: 'Belum ada jurnal.',
            columns: [
                {
                    title: 'Date',
                    field: 'date',
                    width: 120,
                    headerSort: false,
                },
                {
                    title: 'Reference',
                    field: 'reference',
                    width: 150,
                    headerSort: false,
                    formatter: function (cell) {
                        return `<span class="badge badge-soft font-mono">${cell.getValue()}</span>`;
                    },
                },
                {
                    title: 'Description',
                    field: 'description',
                    headerSort: false,
                    widthGrow: 2,
                },
                {
                    title: 'Amount',
                    field: 'total_amount',
                    width: 160,
                    headerSort: false,
                    hozAlign: 'right',
                    headerHozAlign: 'right',
                    formatter: function (cell) {
                        const val = cell.getValue();
                        return 'Rp ' + parseInt(val).toLocaleString('id-ID');
                    },
                },
                {
                    title: 'Action',
                    field: 'show_url',
                    width: 60,
                    headerSort: false,
                    hozAlign: 'center',
                    formatter: function (cell) {
                        return `<a href="${cell.getValue()}" class="btn btn-ghost btn-xs">
                            <span class="material-symbols-outlined text-[14px]">visibility</span>
                        </a>`;
                    },
                },
            ],
        });
    });

    function applyFilter() {
        const search   = document.getElementById('search-journal').value;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo   = document.getElementById('date-to').value;
        window.journalTable.setData('{{ route("finance.journals.data") }}', {
            search, date_from: dateFrom, date_to: dateTo
        });
    }

    function clearFilter() {
        document.getElementById('search-journal').value = '';
        document.getElementById('date-from').value      = '';
        document.getElementById('date-to').value        = '';
        window.journalTable.setData('{{ route("finance.journals.data") }}', {});
    }
    </script>

</x-app-layout>



