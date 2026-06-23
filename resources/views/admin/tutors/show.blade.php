<x-app-layout>
    <x-slot name="title">{{ $tutor->user->name }}</x-slot>

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


        {{-- Back --}}
        <a href="{{ route('admin.tutors.index') }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Tutors
        </a>

        {{-- Header --}}
        <div class="app-card flex items-start justify-between gap-md">
            <div class="flex items-center gap-md">
                <div class="w-12 h-12 rounded-full bg-secondary-container flex items-center justify-center text-on-secondary-container font-bold text-lg">
                    {{ strtoupper(substr($tutor->user->name, 0, 1)) }}
                </div>
                <div>
                    <h3 class="text-headline-lg font-semibold text-on-surface">{{ $tutor->user->name }}</h3>
                    <p class="text-body-md text-on-surface-variant">{{ $tutor->user->email }}</p>
                    <span class="badge badge-soft mt-xs">{{ $tutor->persona }}</span>
                </div>
            </div>
            <div class="flex gap-sm">
                <button type="button" onclick="document.getElementById('modal-edit-tutor').showModal()"
                    class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">edit</span>
                    Edit
                </button>
                <button type="button" onclick="document.getElementById('modal-delete-tutor').showModal()"
                    class="btn btn-ghost btn-sm text-error gap-xs">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    Hapus
                </button>
            </div>
        </div>

        {{-- Rates --}}
        <div class="app-card">
            <div class="flex items-center justify-between mb-md">
                <h4 class="text-headline-md font-semibold text-on-surface">Rate per Program</h4>
                <button type="button" onclick="document.getElementById('modal-add-rate').showModal()"
                    class="btn btn-ghost btn-sm gap-xs">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Tambah Rate
                </button>
            </div>

            @if($tutor->rates->isEmpty())
                <p class="text-body-md text-on-surface-variant">Belum ada rate yang ditambahkan.</p>
            @else
                <div class="overflow-x-auto">
                    <div class="overflow-y-auto" style="max-height: 280px;">
                        <div class="app-table-wrapper">
<table class="table table-sm">
                            <thead class="sticky top-0 bg-surface-container-lowest z-10">
                                <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                    <th>Program</th>
                                    <th>Rate / Sesi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tutor->rates as $rate)
                                <tr class="border-b border-surface-border last:border-0">
                                    <td class="text-on-surface">{{ $rate->program->name }}</td>
                                    <td class="font-semibold text-on-surface">IDR {{ number_format($rate->rate) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Availability --}}
        <div class="app-card">
            <h4 class="text-headline-md font-semibold text-on-surface mb-md">Availability</h4>

            <form method="POST" action="{{ route('admin.tutors.availability.store', $tutor->id) }}">
                @csrf

                @php
                    $days = array_map(fn($d) => $d->value, \App\Enums\DayOfWeek::cases());
                    $timeBlocks = array_filter(array_map(fn($t) => $t->value, \App\Enums\TimeBlock::cases()), fn($v) => $v !== 'Custom');
                    $availMap = $tutor->availability->keyBy(fn($a) => $a->day . '|' . $a->time_block);
                @endphp

                <div class="overflow-x-auto">
                    <div class="app-table-wrapper">
<table class="table table-sm text-center">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                <th class="text-left">Time Block</th>
                                @foreach($days as $day)
                                    <th>{{ $day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($timeBlocks as $i => $block)
                            <tr class="border-b border-surface-border last:border-0">
                                <td class="text-body-md text-on-surface-variant text-left whitespace-nowrap">{{ $block }}</td>
                                @foreach($days as $j => $day)
                                @php
                                    $key = $day . '|' . $block;
                                    $current = $availMap->get($key)?->status ?? 'not_available';
                                @endphp
                                <td>
                                    <input type="hidden" name="availability[{{ $i * count($days) + $j }}][day]" value="{{ $day }}">
                                    <input type="hidden" name="availability[{{ $i * count($days) + $j }}][time_block]" value="{{ $block }}">
                                    <select name="availability[{{ $i * count($days) + $j }}][status]"
                                        class="select select-xs w-full min-w-[110px] text-label-lg">
                                        <option value="available" {{ $current === 'available' ? 'selected' : '' }}>✓ Available</option>
                                        <option value="occupied" {{ $current === 'occupied' ? 'selected' : '' }}>🔒 Occupied</option>
                                        <option value="not_available" {{ $current === 'not_available' ? 'selected' : '' }}>✗ N/A</option>
                                    </select>
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>

                <div class="mt-md">
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan Availability
                    </button>
                </div>
            </form>

            {{-- Custom Slots --}}
            <div class="mt-lg border-t border-surface-border pt-lg">
                <h5 class="text-headline-sm font-semibold text-on-surface mb-md">Custom Slots</h5>

                <form method="POST" action="{{ route('admin.tutors.availability.custom', $tutor->id) }}" class="flex flex-wrap gap-md items-end mb-md">
                    @csrf
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Hari</label>
                        <select name="day" class="select w-full" required>
                            <option value="" disabled selected>Pilih hari...</option>
                            @foreach($days as $day)
                                <option value="{{ $day }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Time Block</label>
                        <input type="text" name="time_block" placeholder="e.g. 08:00-09:00"
                            class="input w-full" required />
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-legend text-on-surface">Status</label>
                        <select name="status" class="select w-full" required>
                            <option value="available">✓ Available</option>
                            <option value="occupied">🔒 Occupied</option>
                            <option value="not_available">✗ N/A</option>
                        </select>
                    </div>
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                        <span class="material-symbols-outlined text-[18px]">add</span>
                        Tambah
                    </button>
                </form>

                @php
                    $customSlots = $tutor->availability->filter(fn($a) => !in_array($a->time_block, array_values($timeBlocks)));
                @endphp

                @if($customSlots->isEmpty())
                    <p class="text-body-md text-on-surface-variant">Belum ada custom slot.</p>
                @else
                    <div class="overflow-x-auto">
                        <div class="overflow-y-auto" style="max-height: 200px;">
                            <div class="app-table-wrapper">
<table class="table table-sm">
                                <thead class="sticky top-0 bg-surface-container-lowest z-10">
                                    <tr class="text-label-lg text-on-surface-variant border-b border-surface-border">
                                        <th>Hari</th>
                                        <th>Time Block</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customSlots as $slot)
                                    <tr class="border-b border-surface-border last:border-0">
                                        <td class="text-on-surface">{{ $slot->day }}</td>
                                        <td class="text-on-surface">{{ $slot->time_block }}</td>
                                        <td>
                                            <span class="badge badge-soft">{{ $slot->status }}</span>
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.tutors.availability.destroy', [$tutor->id, $slot->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button aria-label="Hapus" type="submit" class="btn btn-ghost btn-xs text-error">
                                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Modal Edit Tutor --}}
    <dialog id="modal-edit-tutor" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Edit Tutor</h3>
            <form method="POST" action="{{ route('admin.tutors.update', $tutor->id) }}" class="space-y-md">
                @csrf
                @method('PATCH')

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Nama Lengkap</label>
                    <input type="text" name="name" value="{{ old('name', $tutor->user->name) }}"
                        class="input w-full @error('name') input-error @enderror" required />
                    @error('name')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Email</label>
                    <input type="email" name="email" value="{{ old('email', $tutor->user->email) }}"
                        class="input w-full @error('email') input-error @enderror" required />
                    @error('email')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Persona</label>
                    <input type="text" name="persona" value="{{ old('persona', $tutor->persona) }}"
                        class="input w-full @error('persona') input-error @enderror"
                        placeholder="e.g. S1, S2, Native" required />
                    @error('persona')<p class="label text-error">{{ $message }}</p>@enderror
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Status</label>
                    <select name="status" class="select w-full" required>
                        <option value="active" {{ $tutor->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $tutor->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-edit-tutor').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">Simpan</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal Tambah Rate --}}
    <dialog id="modal-add-rate" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-on-surface mb-md">Tambah / Update Rate</h3>
            <form method="POST" action="{{ route('admin.tutors.rates.store', $tutor->id) }}" class="space-y-md">
                @csrf

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Program</label>
                    <select name="program_id" class="select w-full" required>
                        <option value="" disabled selected>Pilih program...</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fieldset w-full">
                    <label class="fieldset-legend text-on-surface">Rate per Sesi (IDR)</label>
                    <input type="number" name="rate" min="0"
                        class="input w-full" placeholder="150000" required />
                </div>

                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-add-rate').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit"
                        class="btn bg-primary-container text-on-primary border-none hover:opacity-90">Simpan</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Modal Hapus Tutor --}}
    <dialog id="modal-delete-tutor" class="modal">
        <div class="modal-box">
            <h3 class="text-headline-md font-semibold text-error mb-sm">Hapus Tutor?</h3>
            <p class="text-body-md text-on-surface-variant mb-md">
                Aksi ini permanen. Data tutor <strong>{{ $tutor->user->name }}</strong> akan dihapus.
            </p>
            <form method="POST" action="{{ route('admin.tutors.destroy', $tutor->id) }}">
                @csrf
                @method('DELETE')
                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('modal-delete-tutor').close()"
                        class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn btn-error">Hapus</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

</x-app-layout>
