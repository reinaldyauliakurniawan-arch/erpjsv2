<x-app-layout>
    <x-slot name="title">Class Sessions</x-slot>

    <div class="p-lg space-y-lg" x-data="{ filter: 'all' }">

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

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-headline-lg font-semibold text-on-surface">Class Sessions</h3>
                <p class="text-body-md text-on-surface-variant">{{ $classSessions->count() }} kelas terdaftar</p>
            </div>
            <a href="{{ route('admin.class-sessions.create') }}"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Kelas
            </a>
        </div>

        {{-- Toggle Filter --}}
        <div class="inline-flex rounded-lg overflow-hidden border border-surface-border">
            @foreach(['all' => 'Semua', 'private' => 'Private', 'semi-private' => 'Semi-Private', 'group' => 'Group'] as $val => $label)
                <button type="button"
                    @click="filter = '{{ $val }}'"
                    :class="filter ==='{{ $val }}'
                        ? 'bg-primary-container text-on-primary'
                        : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface'"
                    class="px-md py-sm text-body-md font-semibold transition-all border-r border-surface-border last:border-r-0">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="app-card">
            @if($classSessions->isEmpty())
                <div class="flex flex-col items-center justify-center py-2xl gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-[48px]">class</span>
                    <p class="text-body-md">Belum ada class session.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <div class="app-table-wrapper">
<table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                <th>Nama Kelas</th>
                                <th>Program</th>
                                <th>Tipe</th>
                                <th>Jumlah Siswa</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($classSessions as $cs)
                            <tr class="border-b border-surface-border last:border-0 hover:bg-surface"
                                x-show="filter === 'all' || filter === '{{ $cs->class_type }}'">
                                <td class="font-semibold text-on-surface">{{ $cs->name }}</td>
                                <td class="text-on-surface-variant">{{ $cs->program->name }}</td>
                                <td><span class="badge badge-soft">{{ ucfirst($cs->class_type) }}</span></td>
                                <td class="text-on-surface">{{ $cs->enrollments_count }}</td>
                                <td>
                                    <span class="badge badge-soft {{ $cs->status ==='active' ? 'badge-success' : 'badge-error' }}">
                                        {{ $cs->status }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.class-sessions.show', $cs->id) }}"
                                        class="btn btn-ghost btn-sm gap-xs">
                                        <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
