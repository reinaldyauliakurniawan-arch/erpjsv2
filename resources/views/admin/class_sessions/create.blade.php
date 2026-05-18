<x-app-layout>
    <x-slot name="title">Tambah Class Session</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 40rem">

        <a href="{{ route('admin.class-sessions.index') }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Class Sessions
        </a>

        <h3 class="text-headline-lg font-semibold text-on-surface">Tambah Class Session</h3>

        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <form method="POST" action="{{ route('admin.class-sessions.store') }}" class="space-y-md">
                @csrf

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Kelas</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="input w-full @error('name') input-error @enderror"
                        placeholder="Contoh: B1 - Senin Rabu" required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Program</label>
                    <select name="program_id"
                        class="select w-full @error('program_id') select-error @enderror" required>
                        <option value="" disabled selected>Pilih program...</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('program_id')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Status</label>
                    <select name="status"
                        class="select w-full @error('status') select-error @enderror" required>
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-sm pt-sm">
                    <a href="{{ route('admin.class-sessions.index') }}" class="btn btn-ghost">Batal</a>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        Simpan
                    </button>
                </div>
            </form>
        </div>

    </div>
</x-app-layout>



