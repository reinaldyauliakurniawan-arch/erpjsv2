<x-app-layout>
    <x-slot name="title">Edit Student</x-slot>

    <div class="p-lg max-w-2xl space-y-lg">

        {{-- Breadcrumb + back link --}}
        <div class="flex items-center gap-sm">
            <a href="{{ route('admin.students.show', $student->id) }}" class="btn btn-ghost btn-sm gap-xs flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span class="hidden sm:inline">Back</span>
            </a>
            <span class="text-on-surface-variant text-body-md">/</span>
            <span class="text-body-md font-semibold text-on-surface truncate">Edit {{ $student->user?->name ?? '—' }}</span>
        </div>

        <div class="app-card">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Informasi Student</h3>

            <form method="POST" action="{{ route('admin.students.update', $student->id) }}" class="space-y-md">
                @csrf
                @method('PATCH')

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama</label>
                    <input type="text" name="name" value="{{ old('name', $student->user?->name) }}"
                        class="input w-full @error('name') input-error @enderror"
                        required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" value="{{ old('email', $student->user?->email) }}"
                        class="input w-full @error('email') input-error @enderror"
                        required />
                    @error('email')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Education Level</label>
                    <select name="education_level" class="select select-bordered bg-surface text-on-surface w-full">
                        @php
                            $levels = ['SD' => 'SD', 'SMP' => 'SMP', 'SMA' => 'SMA', 'Kuliah' => 'Kuliah', 'Umum' => 'Umum'];
                            $current = old('education_level', $student->education_level);
                        @endphp
                        @foreach($levels as $key => $label)
                            <option value="{{ $key }}" {{ $current === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Notes <span class="text-on-surface-variant font-normal">(opsional)</span></label>
                    <textarea name="notes" class="textarea w-full" rows="3">{{ old('notes', $student->notes) }}</textarea>
                </div>

                <div class="flex items-center gap-sm pt-md">
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 flex-shrink-0">
                        Simpan Perubahan
                    </button>
                    <a href="{{ route('admin.students.show', $student->id) }}" class="btn btn-ghost flex-shrink-0">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
