<x-app-layout>
    <x-slot name="title">Classrooms</x-slot>

    <div class="p-lg space-y-lg"
        x-data
        x-init="{{ $errors->any() ? 'document.getElementById(\'modal-create\').showModal()' : '' }}">

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
        <div class="flex items-center justify-between gap-md">
            <div class="min-w-0 shrink">
                <h3 class="text-headline-lg font-semibold text-on-surface">Classrooms</h3>
                <p class="text-body-md text-on-surface-variant mt-xs">Kelola ruang kelas yang tersedia.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create').showModal()"
                class="btn bg-secondary text-on-secondary border-none hover:opacity-90 gap-sm flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Ruangan
            </button>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-md">
            <div class="app-card flex flex-col gap-md">
                <div class="app-icon-badge">
                    <span class="material-symbols-outlined text-secondary">meeting_room</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Ruangan</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $classrooms->count() }}</p>
                </div>
            </div>
            <div class="app-card flex flex-col gap-md">
                <div class="app-icon-badge">
                    <span class="material-symbols-outlined text-secondary">group</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Kapasitas</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $classrooms->sum('capacity') ?: '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="app-card app-card--flush">
            <div class="app-card__header">
                <h4 class="text-title-sm font-semibold text-on-surface">Daftar Ruangan</h4>
                <span class="badge badge-soft">{{ $classrooms->count() }} ruangan</span>
            </div>

            @if($classrooms->isEmpty())
                <p class="text-body-md text-on-surface-variant text-center py-lg">Belum ada ruangan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm w-full">
                        <thead class="bg-surface-container-high">
                            <tr class="border-b border-surface-border">
                                <th class="px-lg py-md text-label-lg text-on-surface-variant uppercase tracking-widest text-left font-medium">Nama Ruangan</th>
                                <th class="px-md py-md text-label-lg text-on-surface-variant uppercase tracking-widest text-left font-medium">Kapasitas</th>
                                <th class="px-lg py-md text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-border">
                            @foreach($classrooms as $classroom)
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-lg py-md">
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-lg bg-primary/5 border border-primary/10 flex items-center justify-center text-primary shrink-0">
                                            <span class="material-symbols-outlined text-[16px]">meeting_room</span>
                                        </div>
                                        <p class="text-body-sm font-semibold text-on-surface">{{ $classroom->name }}</p>
                                    </div>
                                </td>
                                <td class="px-md py-md">
                                    @if($classroom->capacity)
                                        <div class="flex items-center gap-xs">
                                            <span class="material-symbols-outlined text-on-surface-variant text-[16px]">group</span>
                                            <span class="text-body-sm text-on-surface">{{ $classroom->capacity }} orang</span>
                                        </div>
                                    @else
                                        <span class="text-on-surface-variant">—</span>
                                    @endif
                                    <span class="badge badge-soft text-xs mt-xs {{ $classroom->is_at_just_speak ?'badge-success' : 'badge-ghost' }}">
                                        {{ $classroom->is_at_just_speak ? 'Just Speak' : 'Eksternal' }}
                                    </span>
                                </td>
                                <td class="px-lg py-md text-right">
                                    <div class="flex items-center justify-end gap-xs">
                                        <button type="button"
                                            data-id="{{ $classroom->id }}"
                                            data-name="{{ $classroom->name }}"
                                            data-capacity="{{ $classroom->capacity }}"
                                            data-at-just-speak="{{ $classroom->is_at_just_speak ? 1 : 0 }}"
                                            onclick="openEditModal(this.dataset)"
                                            class="btn btn-ghost btn-sm text-on-surface-variant hover:text-secondary"
                                            title="Edit">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>
                                        <button type="button"
                                            data-id="{{ $classroom->id }}"
                                            data-name="{{ $classroom->name }}"
                                            onclick="openDeleteModal(this.dataset)"
                                            class="btn btn-ghost btn-sm text-error"
                                            title="Hapus">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
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

    {{-- Modal Create --}}
    <dialog id="modal-create" class="modal">
        <div class="modal-box bg-surface-container-lowest border border-surface-border" style="max-width: 28rem">
            <h4 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Ruangan</h4>
            <form method="POST" action="{{ route('admin.classrooms.store') }}" class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Ruangan</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="input w-full @error('name') input-error @enderror"
                        placeholder="Ruang A" required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Kapasitas <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                    <input type="number" name="capacity" value="{{ old('capacity') }}"
                        class="input w-full @error('capacity') input-error @enderror"
                        placeholder="10" min="1" />
                    @error('capacity')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Lokasi</label>
                    <label class="flex items-center gap-sm cursor-pointer mt-xs">
                        <input type="checkbox" name="is_at_just_speak" value="1" class="checkbox" checked />
                        <span class="text-body-md text-on-surface">Ruangan di Just Speak</span>
                    </label>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-create').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-secondary text-on-secondary border-none hover:opacity-90 gap-sm">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal Edit --}}
    <dialog id="modal-edit" class="modal">
        <div class="modal-box bg-surface-container-lowest border border-surface-border" style="max-width: 28rem">
            <h4 class="text-headline-md font-semibold text-on-surface mb-md">Edit Ruangan</h4>
            <form id="form-edit" method="POST" class="space-y-md">
                @csrf
                @method('PUT')
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Ruangan</label>
                    <input type="text" name="name" id="edit-name"
                        class="input w-full" placeholder="Ruang A" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Kapasitas <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                    <input type="number" name="capacity" id="edit-capacity"
                        class="input w-full" placeholder="10" min="1" />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Lokasi</label>
                    <label class="flex items-center gap-sm cursor-pointer mt-xs">
                        <input type="checkbox" name="is_at_just_speak" id="edit-is-at-js" value="1" class="checkbox" />
                        <span class="text-body-md text-on-surface">Ruangan di Just Speak</span>
                    </label>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-edit').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn bg-secondary text-on-secondary border-none hover:opacity-90 gap-sm">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal Delete --}}
    <dialog id="modal-delete" class="modal">
        <div class="modal-box bg-surface-container-lowest border border-surface-border">
            <h4 class="text-headline-md font-semibold text-on-surface mb-xs">Hapus Ruangan</h4>
            <p class="text-body-sm text-on-surface-variant mb-md" id="delete-subtitle">—</p>
            <p class="text-body-sm text-error">Tindakan ini tidak bisa dibatalkan.</p>
            <form id="form-delete" method="POST" class="mt-lg">
                @csrf
                @method('DELETE')
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-delete').close()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn btn-error">Hapus</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <script>
    function openEditModal(d) {
        document.getElementById('edit-name').value     = d.name;
        document.getElementById('edit-capacity').value = d.capacity !== 'null' ? d.capacity : '';
        document.getElementById('edit-is-at-js').checked = d.atJustSpeak == 1;
        document.getElementById('form-edit').action    = `/admin/classrooms/${d.id}`;
        document.getElementById('modal-edit').showModal();
    }

    function openDeleteModal(d) {
        document.getElementById('delete-subtitle').textContent = `Ruangan "${d.name}" akan dihapus.`;
        document.getElementById('form-delete').action = `/admin/classrooms/${d.id}`;
        document.getElementById('modal-delete').showModal();
    }
    </script>

</x-app-layout>
