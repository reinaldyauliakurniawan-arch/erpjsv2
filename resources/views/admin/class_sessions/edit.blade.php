<x-app-layout>
    <x-slot name="title">Edit Class Session</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 40rem">
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

        <a href="{{ route('admin.class-sessions.show', $classSession->id) }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Detail Kelas
        </a>

        <h3 class="text-headline-lg font-semibold text-on-surface">Edit Class Session</h3>

        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <form method="POST" action="{{ route('admin.class-sessions.update', $classSession->id) }}" class="space-y-md">
                @csrf
                @method('PUT')

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Kelas</label>
                    <input type="text" name="name" value="{{ old('name', $classSession->name) }}"
                        class="input w-full @error('name') input-error @enderror"
                        required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Program</label>
                    <select name="program_id"
                        class="select w-full @error('program_id') select-error @enderror" required>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}"
                                {{ old('program_id', $classSession->program_id) == $program->id ? 'selected' : '' }}>
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
                        <option value="active" {{ old('status', $classSession->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $classSession->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Tipe Kelas</label>
                    <select name="class_type"
                        class="select w-full @error('class_type') select-error @enderror" required>
                        <option value="private" {{ old('class_type', $classSession->class_type) === 'private' ? 'selected' : '' }}>Private</option>
                        <option value="semi-private" {{ old('class_type', $classSession->class_type) === 'semi-private' ? 'selected' : '' }}>Semi-Private</option>
                        <option value="group" {{ old('class_type', $classSession->class_type) === 'group' ? 'selected' : '' }}>Group</option>
                    </select>
                    @error('class_type')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-sm pt-sm">
                    <a href="{{ route('admin.class-sessions.show', $classSession->id) }}" class="btn btn-ghost">Batal</a>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        Update
                    </button>
                </div>
            </form>
        </div>

    </div>
</x-app-layout>
