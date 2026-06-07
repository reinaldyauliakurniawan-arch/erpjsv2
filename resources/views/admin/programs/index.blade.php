<x-app-layout>
    <x-slot name="title">Programs</x-slot>

    <div class="p-lg space-y-lg">

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h3 class="text-headline-lg font-semibold text-on-surface">Programs</h3>
            <button onclick="document.getElementById('modal-add-program').showModal()"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Program
            </button>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            @if($programs->isEmpty())
                <p class="text-body-md text-on-surface-variant">Belum ada program.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                <th>Nama</th>
                                <th>Tipe</th>
                                <th>Harga</th>
                                <th>Total Sesi</th>
                                <th>Min. Quota</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($programs as $program)
                            <tr class="border-b border-surface-border last:border-0" x-data="{ editing: false }" x-cloak>

                                {{-- View mode --}}
                                <td x-show="!editing" class="font-semibold text-on-surface">{{ $program->name }}</td>
                                <td x-show="!editing"><span class="badge badge-soft">{{ ucfirst(str_replace('-', ' ', $program->type)) }}</span></td>
                                <td x-show="!editing" class="text-on-surface">IDR {{ number_format($program->price) }}</td>
                                <td x-show="!editing" class="text-on-surface">{{ $program->total_meetings }}x</td>
                                <td x-show="!editing" class="text-on-surface">{{ $program->min_quota ?? '-' }}</td>
                                <td x-show="!editing" class="text-right">
                                    <div class="flex justify-end gap-xs">
                                        <button @click="editing = true" class="btn btn-ghost btn-xs">
                                            <span class="material-symbols-outlined text-[14px]">edit</span>
                                        </button>
                                        <form method="POST" action="{{ route('admin.programs.destroy', $program) }}"
                                            onsubmit="return confirm('Hapus program {{ $program->name }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-xs text-error">
                                                <span class="material-symbols-outlined text-[14px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                {{-- Edit mode --}}
                                <td x-show="editing" colspan="6">
                                    <form method="POST" action="{{ route('admin.programs.update', $program) }}"
                                        class="grid grid-cols-6 gap-sm items-end py-xs">
                                        @csrf @method('PATCH')
                                        <div class="fieldset col-span-2">
                                            <label class="fieldset-legend text-on-surface">Nama</label>
                                            <input type="text" name="name" value="{{ $program->name }}"
                                                class="input w-full" required />
                                        </div>
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Tipe</label>
                                            <select name="type" class="select w-full">
                                            @foreach(['private' => 'Private', 'semi-private' => 'Semi Private', 'group' => 'Group Class'] as $val => $label)
                                                <option value="{{ $val }}" @selected($program->type === $val)>{{ $label }}</option>
                                            @endforeach
                                            </select>
                                        </div>
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Harga</label>
                                            <input type="number" name="price" value="{{ $program->price }}"
                                                min="0" class="input w-full" required />
                                        </div>
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Total Sesi</label>
                                            <input type="number" name="total_meetings" value="{{ $program->total_meetings }}"
                                                min="1" class="input w-full" required />
                                        </div>
                                        <div class="fieldset">
                                            <label class="fieldset-legend text-on-surface">Min. Quota</label>
                                            <input type="number" name="min_quota" value="{{ $program->min_quota }}"
                                                min="1" class="input w-full" />
                                        </div>
                                        <div class="flex gap-xs col-span-6 justify-end">
                                            <button type="submit"
                                                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 btn-sm">
                                                Simpan
                                            </button>
                                            <button type="button" @click="editing = false"
                                                class="btn btn-ghost btn-sm">
                                                Batal
                                            </button>
                                        </div>
                                    </form>
                                </td>

                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    {{-- Modal Tambah Program --}}
    <dialog id="modal-add-program" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Program</h3>
            <form method="POST" action="{{ route('admin.programs.store') }}" class="space-y-md">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Program</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="input w-full @error('name') input-error @enderror" required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Tipe</label>
                    <select name="type" class="select w-full @error('type') select-error @enderror" required>
                        <option value="" disabled selected>Pilih tipe...</option>
                        @foreach(['private' => 'Private', 'semi-private' => 'Semi Private', 'group' => 'Group Class'] as $val => $label)
                            <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Harga (IDR)</label>
                    <input type="number" name="price" value="{{ old('price') }}" min="0"
                        class="input w-full @error('price') input-error @enderror" required />
                    @error('price')<p class="label text-error">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-md">
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Total Sesi</label>
                        <input type="number" name="total_meetings" value="{{ old('total_meetings') }}" min="1"
                            class="input w-full @error('total_meetings') input-error @enderror" required />
                        @error('total_meetings')<p class="label text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Min. Quota</label>
                        <input type="number" name="min_quota" value="{{ old('min_quota') }}" min="1"
                            class="input w-full @error('min_quota') input-error @enderror" />
                        @error('min_quota')<p class="label text-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-add-program').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">Simpan</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

</x-app-layout>
