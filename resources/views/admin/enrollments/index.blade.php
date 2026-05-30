<x-app-layout>
    <x-slot name="title">Enrollments</x-slot>

    <div class="p-lg space-y-lg">

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if($errors->has('error'))
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <span>{{ $errors->first('error') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h3 class="text-headline-lg font-semibold text-on-surface">Enrollments</h3>
            <a href="{{ route('admin.enrollments.create') }}"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Enrollment
            </a>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            {{-- Toolbar --}}
            <div class="flex flex-col sm:flex-row gap-sm flex-wrap">
                <input type="text" id="search-enrollment" placeholder="Cari student atau program..."
                    class="input input-sm flex-1" />
                <select id="filter-status" class="select select-sm w-40">
                    <option value="">Semua Status</option>
                    <option value="active">Active</option>
                    <option value="waitlist">Waitlist</option>
                    <option value="graduate">Graduate</option>
                    <option value="expired">Expired</option>
                </select>
                <select id="filter-payment" class="select select-sm w-40">
                    <option value="">Semua Pembayaran</option>
                    <option value="pending">Pending</option>
                    <option value="full">Full</option>
                    <option value="partial">Partial</option>
                </select>
                <button onclick="applyFilter()" class="btn btn-sm bg-primary-container text-on-primary border-none hover:opacity-90">
                    Filter
                </button>
                <button onclick="clearFilter()" class="btn btn-sm btn-ghost">
                    Reset
                </button>
            </div>

            {{-- Tabulator --}}
            <div id="enrollments-table"></div>
        </div>

    </div>

    <script>
    const statusColors = {
        active:   'badge-success',
        waitlist: 'badge-warning',
        graduate: 'badge-info',
        expired:  'badge-error',
    };
    const payColors = {
        full:    'badge-success',
        partial: 'badge-warning',
        pending: 'badge-error',
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.enrollmentTable = new Tabulator('#enrollments-table', {
            ajaxURL: '{{ route("admin.enrollments.data") }}',
            ajaxParams: {},
            ajaxRequestFunc: function(url, config, params) {
                const query = new URLSearchParams({
                    page:           params.page || 1,
                    size:           params.size || 20,
                    search:         params.search || '',
                    status:         params.status || '',
                    payment_status: params.payment_status || '',
                });
                return fetch(url + '?' + query, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                }).then(function(r) {
                    return r.json();
                }).then(function(data) {
                    return { data: data.data, last_page: data.last_page };
                });
            },
            pagination: true,
            paginationMode: 'remote',
            paginationSize: 20,
            paginationCounter: 'rows',
            ajaxResponse: function(url, params, response) {
                return { data: response.data, last_page: response.last_page };
            },
            layout: 'fitDataStretch',
            columnDefaults: {
            resizable: true,
            },
            placeholder: 'Belum ada enrollment.',
            columns: [
                {
                    title: 'Student',
                    field: 'student',
                    headerSort: false,
                    minWidth: 150,
                },
                {
                    title: 'Program',
                    field: 'program',
                    headerSort: false,
                    minWidth: 130,
                },
                {
                    title: 'Kelas',
                    field: 'class_session',
                    headerSort: false,
                    minWidth: 120,
                },
                {
                    title: 'Pembayaran',
                    field: 'payment_status',
                    headerSort: false,
                    width: 120,
                    formatter: function(cell) {
                        var val = cell.getValue();
                        var cls = payColors[val] || '';
                        return '<span class="badge badge-soft ' + cls + '">' + val + '</span>';
                    },
                },
                {
                    title: 'Status',
                    field: 'status',
                    headerSort: false,
                    width: 110,
                    formatter: function(cell) {
                        var val = cell.getValue();
                        var cls = statusColors[val] || '';
                        return '<span class="badge badge-soft ' + cls + '">' + val + '</span>';
                    },
                },
                {
                    title: 'Sisa Sesi',
                    field: 'remaining',
                    headerSort: false,
                    width: 90,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        return cell.getValue() + 'x';
                    },
                },
                {
                    title: 'Aksi',
                    field: 'show_url',
                    headerSort: false,
                    width: 100,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        var row = cell.getRow().getData();
        return '<a href="' + cell.getValue() + '" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined text-[16px]">open_in_new</span></a>'
             + '<button onclick="deleteEnrollment(\'' + row.delete_url + '\')" class="btn btn-ghost btn-sm text-error"><span class="material-symbols-outlined text-[16px]">delete</span></button>';
                    },
                },
            ],
        });
    });

    function applyFilter() {
        var search  = document.getElementById('search-enrollment').value;
        var status  = document.getElementById('filter-status').value;
        var payment = document.getElementById('filter-payment').value;
        window.enrollmentTable.setData('{{ route("admin.enrollments.data") }}', {
            search: search,
            status: status,
            payment_status: payment,
        });
    }

    function clearFilter() {
        document.getElementById('search-enrollment').value = '';
        document.getElementById('filter-status').value     = '';
        document.getElementById('filter-payment').value    = '';
        window.enrollmentTable.setData('{{ route("admin.enrollments.data") }}', {});
    }
    function deleteEnrollment(url) {
    if (!confirm('Hapus enrollment ini? Data tidak bisa dikembalikan.')) return;
    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json',
        }
    }).then(r => r.json()).then(data => {
        if (data.success) window.enrollmentTable.replaceData();
        else alert('Gagal menghapus enrollment.');
    }).catch(() => alert('Terjadi kesalahan. Coba lagi.'));
}
    </script>

</x-app-layout>
