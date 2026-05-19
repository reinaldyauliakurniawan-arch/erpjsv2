<x-app-layout>
    <x-slot name="title">Tambah Class Session</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 56rem" x-data="{
        selectedProgram: '{{ old('program_id', '') }}',
        availableEnrollments: [],
        selectedEnrollments: [],
        schedules: [{ classroom_id: '', day: '', time_block: '', custom_time: '', is_custom: false }],
        async fetchEnrollments(programId) {
            if (!programId) { this.availableEnrollments = []; return; }
            const res = await fetch(`/admin/class-sessions/enrollments/${programId}`);
            this.availableEnrollments = await res.json();
        },
        toggleEnrollment(id) {
            const idx = this.selectedEnrollments.indexOf(id);
            if (idx === -1) this.selectedEnrollments.push(id);
            else this.selectedEnrollments.splice(idx, 1);
        },
        addSchedule() {
            this.schedules.push({ classroom_id: '', day: '', time_block: '', custom_time: '', is_custom: false });
        },
        removeSchedule(i) {
            if (this.schedules.length > 1) this.schedules.splice(i, 1);
        },
    }" x-init="if (selectedProgram) fetchEnrollments(selectedProgram)">

        <a href="{{ route('admin.class-sessions.index') }}"
            class="inline-flex items-center gap-xs text-body-md text-on-surface-variant hover:text-primary-container transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Class Sessions
        </a>

        <h3 class="text-headline-lg font-semibold text-on-surface">Tambah Class Session</h3>

        <form method="POST" action="{{ route('admin.class-sessions.store') }}" class="space-y-lg">
            @csrf

            {{-- Info Dasar --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center gap-sm pb-md border-b border-surface-border">
                    <span class="material-symbols-outlined text-secondary">info</span>
                    <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Info Dasar</h4>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
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
                            class="select w-full @error('program_id') select-error @enderror"
                            x-model="selectedProgram"
                            @change="fetchEnrollments($event.target.value)"
                            required>
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
                        <label class="fieldset-legend text-on-surface">Tipe Kelas</label>
                        <select name="class_type"
                            class="select w-full @error('class_type') select-error @enderror" required>
                            @foreach($classTypes as $type)
                                <option value="{{ $type->value }}" {{ old('class_type', 'private') === $type->value ? 'selected' : '' }}>
                                    {{ ucfirst($type->value) }}
                                </option>
                            @endforeach
                        </select>
                        @error('class_type')<p class="label text-error">{{ $message }}</p>@enderror
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
                </div>
            </div>

            {{-- Tutor --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center gap-sm pb-md border-b border-surface-border">
                    <span class="material-symbols-outlined text-secondary">person</span>
                    <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Tutor</h4>
                    <span class="text-body-sm text-on-surface-variant font-normal ml-xs">(opsional, bisa ditambah nanti)</span>
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend text-on-surface">Pilih Tutor</label>
                    <select name="tutor_ids[]" class="select w-full">
                        <option value="">— Tanpa tutor —</option>
                        @foreach($tutors as $tutor)
                            <option value="{{ $tutor->id }}" {{ in_array($tutor->id, old('tutor_ids', [])) ? 'selected' : '' }}>
                                {{ $tutor->user->name }} — {{ $tutor->persona }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-body-sm text-on-surface-variant mt-xs">Tutor tambahan bisa di-assign di halaman detail.</p>
                </div>
            </div>

            {{-- Siswa --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center gap-sm pb-md border-b border-surface-border">
                    <span class="material-symbols-outlined text-secondary">group</span>
                    <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Siswa</h4>
                    <span class="text-body-sm text-on-surface-variant font-normal ml-xs">(opsional, bisa ditambah nanti)</span>
                </div>

                <div x-show="!selectedProgram" class="text-body-md text-on-surface-variant italic">
                    Pilih program dulu untuk melihat daftar siswa yang tersedia.
                </div>

                <div x-show="selectedProgram && availableEnrollments.length === 0" x-cloak
                    class="text-body-md text-on-surface-variant italic">
                    Tidak ada siswa aktif yang belum punya kelas untuk program ini.
                </div>

                <div x-show="availableEnrollments.length > 0" x-cloak class="space-y-xs">
                    <p class="text-body-sm text-on-surface-variant">Centang siswa yang ingin dimasukkan ke kelas ini:</p>
                    <div class="border border-surface-border rounded-lg divide-y divide-surface-border max-h-60 overflow-y-auto">
                        <template x-for="enrollment in availableEnrollments" :key="enrollment.id">
                            <label class="flex items-center gap-sm px-md py-sm cursor-pointer hover:bg-surface transition-colors">
                                <input type="checkbox"
                                    :name="`enrollment_ids[]`"
                                    :value="enrollment.id"
                                    class="checkbox checkbox-sm"
                                    @change="toggleEnrollment(enrollment.id)">
                                <span class="text-body-md text-on-surface" x-text="enrollment.name"></span>
                            </label>
                        </template>
                    </div>
                    <p class="text-body-sm text-on-surface-variant">
                        <span x-text="selectedEnrollments.length"></span> siswa dipilih.
                        <a href="{{ route('admin.enrollments.create') }}" target="_blank"
                            class="text-primary-container underline ml-xs">
                            + Enroll murid baru
                        </a>
                    </p>
                </div>
            </div>

            {{-- Jadwal --}}
            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                <div class="flex items-center justify-between pb-md border-b border-surface-border">
                    <div class="flex items-center gap-sm">
                        <span class="material-symbols-outlined text-secondary">calendar_month</span>
                        <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Jadwal</h4>
                    </div>
                    <button type="button" @click="addSchedule()"
                        class="btn btn-ghost btn-sm gap-xs">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        Tambah Slot
                    </button>
                </div>

                <div class="space-y-sm">
                    <template x-for="(sch, i) in schedules" :key="i">
                        <div class="grid gap-md items-end" style="grid-template-columns: 1fr 1fr 1fr auto">

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Hari</label>
                                <select :name="`schedules[${i}][day]`" class="select w-full" x-model="sch.day" required>
                                    <option value="">Pilih hari...</option>
                                    @foreach($days as $day)
                                        <option value="{{ $day->value }}">{{ $day->value }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Time Block</label>
                                <select :name="sch.is_custom ? null : `schedules[${i}][time_block]`"
                                    class="select w-full"
                                    x-show="!sch.is_custom"
                                    @change="if ($event.target.value === 'Custom') { sch.is_custom = true; sch.time_block = ''; }
                                             else { sch.time_block = $event.target.value; }">
                                    <option value="">Pilih slot...</option>
                                    @foreach($timeBlocks as $block)
                                        <option value="{{ $block->value }}">{{ $block->value }}</option>
                                    @endforeach
                                    <option value="Custom">Custom...</option>
                                </select>
                                <div x-show="sch.is_custom" x-cloak class="flex gap-xs items-center">
                                    <input type="text" :name="`schedules[${i}][time_block]`"
                                        x-model="sch.custom_time"
                                        placeholder="cth: 07.00-08.30"
                                        class="input w-full" />
                                    <button type="button" @click="sch.is_custom = false; sch.time_block = ''"
                                        class="btn btn-ghost btn-sm">
                                        <span class="material-symbols-outlined text-[18px]">undo</span>
                                    </button>
                                </div>
                            </div>

                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Ruangan</label>
                                <select :name="`schedules[${i}][classroom_id]`" class="select w-full" x-model="sch.classroom_id" required>
                                    <option value="">Pilih ruangan...</option>
                                    @foreach($classrooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-end pb-xs">
                                <button type="button" @click="removeSchedule(i)"
                                    class="btn btn-ghost btn-sm text-error"
                                    :disabled="schedules.length === 1">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>

                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end gap-sm">
                <a href="{{ route('admin.class-sessions.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                    Simpan Class Session
                </button>
            </div>

        </form>
    </div>
</x-app-layout>
