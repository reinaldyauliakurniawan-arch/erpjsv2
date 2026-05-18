<x-app-layout>
    <x-slot name="title">Settings</x-slot>

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
        <div>
            <h3 class="text-headline-lg font-semibold text-on-surface">Settings</h3>
            <p class="text-body-md text-on-surface-variant mt-xs">Kelola user dan konfigurasi sistem.</p>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-md">
            @foreach([['admin','Admin','manage_accounts'],['cfo','CFO','account_balance'],['tutor','Tutor','person_search'],['student','Student','school']] as [$role, $label, $icon])
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md">
                <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-secondary">{{ $icon }}</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">{{ $label }}</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $users->where('role', $role)->count() }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- User Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
            <div class="px-lg py-md border-b border-surface-border flex justify-between items-center bg-surface-container-low">
                <h4 class="text-title-sm font-semibold text-on-surface">Manajemen User</h4>
                <button onclick="document.getElementById('modal-create').showModal()"
                    class="btn bg-secondary text-on-secondary border-none hover:opacity-90 btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">person_add</span>
                    Tambah User
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead class="bg-surface-container-high">
                        <tr class="border-b border-surface-border">
                            <th class="px-lg py-md text-label-lg text-on-surface-variant uppercase tracking-widest text-left font-medium">Nama</th>
                            <th class="px-md py-md text-label-lg text-on-surface-variant uppercase tracking-widest text-left font-medium">Email</th>
                            <th class="px-md py-md text-label-lg text-on-surface-variant uppercase tracking-widest text-left font-medium">Phone</th>
                            <th class="px-md py-md text-label-lg text-on-surface-variant uppercase tracking-widest text-left font-medium">Role</th>
                            <th class="px-lg py-md text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($users as $user)
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded-full bg-secondary/10 flex items-center justify-center text-secondary font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <p class="text-body-sm font-semibold text-on-surface">{{ $user->name }}</p>
                                </div>
                            </td>
                            <td class="px-md py-md">
                                <p class="text-body-sm text-on-surface-variant">{{ $user->email }}</p>
                            </td>
                            <td class="px-md py-md">
                                <p class="text-body-sm text-on-surface-variant">{{ $user->phone ?? '—' }}</p>
                            </td>
                            <td class="px-md py-md">
                                @php
                                    $roleStyles = [
                                        'admin'   => 'badge-soft badge-error',
                                        'cfo'     => 'badge-soft badge-warning',
                                        'tutor'   => 'badge-soft badge-success',
                                        'student' => 'badge-soft',
                                    ];
                                @endphp
                                <span class="badge {{ $roleStyles[$user->role] ?? 'badge-soft' }} capitalize">{{ $user->role }}</span>
                            </td>
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-xs">
                                    <button type="button"
                                        onclick="openEditModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}', '{{ $user->phone }}', '{{ $user->role }}')"
                                        class="btn btn-ghost btn-sm text-on-surface-variant hover:text-secondary">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <button type="button"
                                        onclick="openDeleteModal({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                        class="btn btn-ghost btn-sm text-error">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-lg py-md bg-surface-container-low border-t border-surface-border">
                <p class="text-body-sm text-on-surface-variant">{{ $users->count() }} users terdaftar</p>
            </div>
        </div>
    </div>

    {{-- Modal Create --}}
    <dialog id="modal-create" class="modal">
        <div class="modal-box bg-surface-container-lowest border border-surface-border" style="max-width: 28rem">
            <h4 class="text-headline-md font-semibold text-on-surface mb-md">Tambah User</h4>
            <form method="POST" action="{{ route('admin.settings.users.store') }}" class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="input w-full" placeholder="John Doe" required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="input w-full" placeholder="john@example.com" required />
                    @error('email')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Phone <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="input w-full" placeholder="08xxxxxxxxxx" />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Password</label>
                    <input type="password" name="password" class="input w-full" placeholder="Min. 8 karakter" required />
                    @error('password')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Role</label>
                    <select name="role" class="select select-bordered bg-surface text-on-surface w-full" required>
                        <option value="">Pilih role...</option>
                        <option value="admin">Admin</option>
                        <option value="cfo">CFO</option>
                        <option value="tutor">Tutor</option>
                        <option value="student">Student</option>
                    </select>
                    @error('role')<p class="label text-error">{{ $message }}</p>@enderror
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
            <h4 class="text-headline-md font-semibold text-on-surface mb-md">Edit User</h4>
            <form id="form-edit" method="POST" class="space-y-md">
                @csrf
                @method('PATCH')
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama</label>
                    <input type="text" name="name" id="edit-name" class="input w-full" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" id="edit-email" class="input w-full" required />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Phone <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                    <input type="text" name="phone" id="edit-phone" class="input w-full" />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Password baru <span class="text-on-surface-variant font-normal">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password" class="input w-full" placeholder="Min. 8 karakter" />
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Role</label>
                    <select name="role" id="edit-role" class="select select-bordered bg-surface text-on-surface w-full" required>
                        <option value="admin">Admin</option>
                        <option value="cfo">CFO</option>
                        <option value="tutor">Tutor</option>
                        <option value="student">Student</option>
                    </select>
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
            <h4 class="text-headline-md font-semibold text-on-surface mb-xs">Hapus User</h4>
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
    function openEditModal(id, name, email, phone, role) {
        document.getElementById('edit-name').value  = name;
        document.getElementById('edit-email').value = email;
        document.getElementById('edit-phone').value = phone !== 'null' ? phone : '';
        document.getElementById('edit-role').value  = role;
        document.getElementById('form-edit').action = `/admin/settings/users/${id}`;
        document.getElementById('modal-edit').showModal();
    }

    function openDeleteModal(id, name) {
        document.getElementById('delete-subtitle').textContent = `User "${name}" akan dihapus permanen.`;
        document.getElementById('form-delete').action = `/admin/settings/users/${id}`;
        document.getElementById('modal-delete').showModal();
    }
    </script>

</x-app-layout>



