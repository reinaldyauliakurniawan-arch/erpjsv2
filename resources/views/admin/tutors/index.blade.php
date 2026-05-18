<x-app-layout>
    <x-slot name="title">Tutors</x-slot>

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
            <div>
                <h3 class="text-headline-md font-semibold text-on-surface">Tutors</h3>
                <p class="text-body-md text-on-surface-variant">{{ $tutors->count() }} tutor terdaftar</p>
            </div>
            <button onclick="document.getElementById('modal-add-tutor').showModal()"
                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                Tambah Tutor
            </button>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm">
            @if($tutors->isEmpty())
                <div class="flex flex-col items-center justify-center py-2xl gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-[48px]">person_search</span>
                    <p class="text-body-md">Belum ada tutor terdaftar.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                <th>#</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Persona</th>
                                <th>Terdaftar</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tutors as $tutor)
                            <tr class="border-b border-surface-border last:border-0 hover:bg-surface">
                                <td class="text-on-surface-variant">{{ $loop->iteration }}</td>
                                <td class="font-semibold text-on-surface">{{ $tutor->user->name }}</td>
                                <td class="text-on-surface-variant">{{ $tutor->user->email }}</td>
                                <td>
                                    <span class="badge badge-soft">{{ $tutor->persona }}</span>
                                </td>
                                <td class="text-on-surface-variant">
                                    {{ $tutor->created_at->format('d M Y') }}
                                </td>
                                <td>
                                    <a href="{{ route('admin.tutors.show', $tutor->id) }}"
                                        class="btn btn-ghost btn-xs gap-xs">
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

    {{-- Modal Tambah Tutor --}}
    <dialog id="modal-add-tutor" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah Tutor Baru</h3>

            <form method="POST" action="{{ route('admin.tutors.store') }}" class="space-y-md">
                @csrf

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Lengkap</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="input w-full @error('name') input-error @enderror"
                        placeholder="Jane Doe" required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="input w-full @error('email') input-error @enderror"
                        placeholder="jane@example.com" required />
                    @error('email')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Password</label>
                    <input type="password" name="password"
                        class="input w-full @error('password') input-error @enderror"
                        placeholder="Min. 8 karakter" required />
                    @error('password')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Persona</label>
                    <select name="persona"
                        class="select w-full @error('persona') select-error @enderror" required>
                        <option value="" disabled {{ old('persona') ? '' : 'selected' }}>Pilih persona...</option>
                        <option value="native" {{ old('persona') === 'native' ? 'selected' : '' }}>Native</option>
                        <option value="non-native" {{ old('persona') === 'non-native' ? 'selected' : '' }}>Non-Native</option>
                        <option value="kids-specialist" {{ old('persona') === 'kids-specialist' ? 'selected' : '' }}>Kids Specialist</option>
                        <option value="business" {{ old('persona') === 'business' ? 'selected' : '' }}>Business</option>
                    </select>
                    @error('persona')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="modal-action mt-lg">
                    <button type="button" onclick="document.getElementById('modal-add-tutor').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

</x-app-layout>



