<x-app-layout>
    <x-slot name="title">Class Sessions</x-slot>

    <div class="p-lg space-y-lg">

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

        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm">
            @if($classSessions->isEmpty())
                <div class="flex flex-col items-center justify-center py-2xl gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-[48px]">class</span>
                    <p class="text-body-md">Belum ada class session.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                <th>Nama Kelas</th>
                                <th>Program</th>
                                <th>Jumlah Siswa</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($classSessions as $cs)
                            <tr class="border-b border-surface-border last:border-0 hover:bg-surface">
                                <td class="font-semibold text-on-surface">{{ $cs->name }}</td>
                                <td class="text-on-surface-variant">{{ $cs->program->name }}</td>
                                <td class="text-on-surface">{{ $cs->enrollments_count }}</td>
                                <td>
                                    <span class="badge badge-soft {{ $cs->status === 'active' ? 'badge-success' : 'badge-error' }}">
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
            @endif
        </div>

    </div>
</x-app-layout>



