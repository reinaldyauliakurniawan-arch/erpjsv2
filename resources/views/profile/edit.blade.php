<x-app-layout>
    <x-slot name="title">Profile</x-slot>

    <div class="p-lg max-w-2xl space-y-lg">

        {{-- Update Profile --}}
        <div class="app-card">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Informasi Profil</h3>

            @if(session('status') === 'profile-updated')
                <div role="alert" class="alert alert-success alert-soft mb-md">
                    <span>Profil berhasil diperbarui.</span>
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-md">
                @csrf
                @method('PATCH')

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                        class="input w-full @error('name') input-error @enderror"
                        required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                        class="input w-full @error('email') input-error @enderror"
                        required />
                    @error('email')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                    Simpan Perubahan
                </button>
            </form>
        </div>

        {{-- Update Password --}}
        <div class="app-card">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Ubah Password</h3>

            @if(session('status') === 'password-updated')
                <div role="alert" class="alert alert-success alert-soft mb-md">
                    <span>Password berhasil diubah.</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-md">
                @csrf
                @method('PUT')

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Password Saat Ini</label>
                    <input type="password" name="current_password"
                        class="input w-full @error('current_password', 'updatePassword') input-error @enderror" />
                    @error('current_password', 'updatePassword')
                        <p class="label text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Password Baru</label>
                    <input type="password" name="password"
                        class="input w-full @error('password', 'updatePassword') input-error @enderror" />
                    @error('password', 'updatePassword')
                        <p class="label text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation"
                        class="input w-full" />
                </div>

                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                    Ubah Password
                </button>
            </form>
        </div>

        {{-- Delete Account --}}
        <div class="bg-surface-container-lowest border border-error rounded-lg p-lg">
            <h3 class="text-headline-md font-semibold text-error mb-sm">Hapus Akun</h3>
            <p class="text-body-md text-on-surface-variant mb-md">Aksi ini permanen dan tidak bisa dibatalkan.</p>

            <button type="button" onclick="document.getElementById('modal-delete').showModal()"
                class="btn btn-error btn-soft">
                Hapus Akun Saya
            </button>

            <dialog id="modal-delete" class="modal">
                <div class="modal-box">
                    <h3 class="text-headline-md font-semibold text-error mb-sm">Yakin ingin menghapus akun?</h3>
                    <p class="text-body-md text-on-surface-variant mb-md">Masukkan password untuk konfirmasi.</p>

                    <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-md">
                        @csrf
                        @method('DELETE')

                        <div class="fieldset">
                            <label class="fieldset-legend text-on-surface">Password</label>
                            <input type="password" name="password"
                                class="input w-full @error('password', 'userDeletion') input-error @enderror" />
                            @error('password', 'userDeletion')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="modal-action">
                                <button type="button" onclick="document.getElementById('modal-delete').close()" class="btn btn-ghost">Batal</button>
                                <button type="submit" class="btn btn-error">Hapus Akun</button>
                            </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop"><button>close</button></form>
            </dialog>
        </div>

    </div>
</x-app-layout>
