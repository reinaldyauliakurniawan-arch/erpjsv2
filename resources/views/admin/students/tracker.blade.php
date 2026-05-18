<x-app-layout>
    <x-slot name="title">Student Tracker</x-slot>

    <div class="p-lg space-y-lg" x-data="{ filter: 'all' }">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-headline-md font-semibold text-on-surface">Student Tracker</h3>
                <p class="text-body-md text-on-surface-variant">{{ $students->count() }} siswa terdaftar</p>
            </div>
            <button onclick="document.getElementById('modal-add-column').showModal()"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add_column_right</span>
                Tambah Kolom
            </button>
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Filter --}}
        <div class="flex items-center gap-sm">
            <span class="text-body-md text-on-surface-variant">Filter:</span>
            <button @click="filter = 'all'" :class="filter === 'all' ? 'btn-primary' : 'btn-ghost'" class="btn btn-sm">Semua</button>
            <button @click="filter = 'complete'" :class="filter === 'complete' ? 'btn-primary' : 'btn-ghost'" class="btn btn-sm">Complete</button>
            <button @click="filter = 'incomplete'" :class="filter === 'incomplete' ? 'btn-primary' : 'btn-ghost'" class="btn btn-sm">Incomplete</button>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
            @if($columns->isEmpty())
                <div class="flex flex-col items-center justify-center py-2xl gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-[48px]">checklist</span>
                    <p class="text-body-md">Belum ada kolom tracker. Tambah kolom dulu.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                <th class="min-w-[200px]">Siswa</th>
                                @foreach($columns as $column)
                                    <th class="text-center min-w-[120px]">
                                        <div class="flex items-center justify-center gap-xs">
                                            <span>{{ $column->name }}</span>
                                            <form method="POST" action="{{ route('admin.tracker.columns.destroy', $column) }}"
                                                onsubmit="return confirm('Hapus kolom {{ addslashes($column->name) }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-error opacity-40 hover:opacity-100 transition-opacity">
                                                    <span class="material-symbols-outlined text-[14px]">close</span>
                                                </button>
                                            </form>
                                        </div>
                                    </th>
                                @endforeach
                                <th class="text-center min-w-[100px]">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($students as $student)
                                @php
                                    $total = $columns->count();
                                    $done  = $student->trackerEntries->where('is_done', true)->count();
                                    $pct   = $total > 0 ? round($done / $total * 100) : 0;
                                @endphp
                                <tr class="border-b border-surface-border last:border-0 hover:bg-surface transition-colors"
                                    x-data="{
                                        total: {{ $total }},
                                        done: {{ $done }},
                                        get pct() { return this.total > 0 ? Math.round(this.done / this.total * 100) : 0 },
                                        get isComplete() { return this.pct === 100 }
                                    }"
                                    x-show="filter === 'all' || (filter === 'complete' && isComplete) || (filter === 'incomplete' && !isComplete)">

                                    {{-- Nama --}}
                                    <td>
                                        <div class="flex items-center gap-sm">
                                            <div class="w-8 h-8 rounded-full bg-secondary-container flex items-center justify-center font-bold text-on-secondary-container text-xs">
                                                {{ strtoupper(substr($student->user->name, 0, 2)) }}
                                            </div>
                                            <span class="font-semibold text-body-md text-on-surface">{{ $student->user->name }}</span>
                                        </div>
                                    </td>

                                    {{-- Checklist per kolom --}}
                                    @foreach($columns as $column)
                                        @php
                                            $entry = $student->trackerEntries->where('tracker_column_id', $column->id)->first();
                                        @endphp
                                        <td class="text-center">
                                            <button
                                                onclick="toggleEntry({{ $student->id }}, {{ $column->id }}, this)"
                                                data-done="{{ $entry && $entry->is_done ? '1' : '0' }}"
                                                class="transition-all">
                                                @if($entry && $entry->is_done)
                                                    <span class="material-symbols-outlined text-secondary text-xl" style="font-variation-settings:'FILL' 1">check_circle</span>
                                                @else
                                                    <span class="material-symbols-outlined text-on-surface-variant text-xl opacity-30">circle</span>
                                                @endif
                                            </button>
                                        </td>
                                    @endforeach

                                    {{-- Progress --}}
                                    <td>
                                        <div class="flex flex-col gap-1 min-w-[80px]">
                                            <div class="flex justify-between text-[10px] font-bold text-on-surface-variant">
                                                <span x-text="done + '/' + total"></span>
                                                <span x-text="pct + '%'"></span>
                                            </div>
                                            <div class="w-full bg-surface-container h-1.5 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full transition-all"
                                                    :class="pct === 100 ? 'bg-secondary' : pct >= 50 ? 'bg-warning' : 'bg-error'"
                                                    :style="'width: ' + pct + '%'"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Tambah Kolom --}}
    <dialog id="modal-add-column" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Kolom Tracker</h3>
            <form method="POST" action="{{ route('admin.tracker.columns.store') }}" class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Kolom</label>
                    <input type="text" name="name" class="input w-full" placeholder="cth: Placement Test, Pembayaran..." required />
                </div>
                <div class="modal-action mt-lg">
                    <button type="button" onclick="document.getElementById('modal-add-column').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">Simpan</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
        function toggleEntry(studentId, columnId, btn) {
            fetch('{{ route('admin.tracker.toggle') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ student_id: studentId, tracker_column_id: columnId })
            })
            .then(r => r.json())
            .then(data => {
                const wasDone = btn.dataset.done === '1';

                // Update icon & data-done
                btn.dataset.done = data.is_done ? '1' : '0';
                btn.innerHTML = data.is_done
                    ? '<span class="material-symbols-outlined text-secondary text-xl" style="font-variation-settings:\'FILL\' 1">check_circle</span>'
                    : '<span class="material-symbols-outlined text-on-surface-variant text-xl opacity-30">circle</span>';

                // Update Alpine state di row
                const row = btn.closest('tr');
                const alpineData = Alpine.$data(row);
                alpineData.done += data.is_done ? 1 : -1;
            });
        }
    </script>

</x-app-layout>



