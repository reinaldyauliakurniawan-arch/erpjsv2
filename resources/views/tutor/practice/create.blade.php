<x-app-layout>
    <x-slot name="title">Buat Tugas</x-slot>

    <div class="p-lg space-y-lg">

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if($errors->any())
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <span>Periksa kembali form di bawah.</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="space-y-xs">
            <h1 class="text-headline-lg font-bold text-on-surface">Buat Self-Study Practice</h1>
            <p class="text-body-md text-on-surface-variant">Rancang materi latihan mandiri untuk membantu siswa mencapai target bahasa mereka.</p>
        </div>

        <form method="POST" action="{{ route('tutor.practice.store') }}">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

                {{-- LEFT COLUMN --}}
                <div class="lg:col-span-8 space-y-lg">
                    {{--  DISTRIBUSI --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg relative"
                        x-data="{
                            open: false,
                            selected: [],
                            classes: {{ collect($classes)->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()->toJson() }},
                            allStudents: {{ collect($classes)->flatMap(fn($c) => collect($c->students)->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'class_id' => $c->id]))->values()->toJson() }},
                            checkedStudentIds: {{ collect($classes)->flatMap(fn($c) => collect($c->students)->pluck('id'))->values()->toJson() }},
                            toggle(id) {
                                this.selected.includes(id)
                                    ? this.selected = this.selected.filter(i => i !== id)
                                    : this.selected.push(id);
                                this.open = false;
                            },
                            label() {
                                if (!this.selected.length) return 'Pilih kelas...'
                                return this.classes.filter(c => this.selected.includes(c.id)).map(c => c.name).join(', ')
                            }
                        }" @click.away="open = false">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">group</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Distribusi</h4>
                        </div>

                        {{-- Class multi-select --}}
                        <div class="fieldset mb-md">
                            <label class="fieldset-legend text-on-surface">Assign ke Kelas</label>
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="class_ids[]" :value="id">
                            </template>
                            <template x-for="id in checkedStudentIds.filter(id => allStudents.filter(s => selected.includes(s.class_id)).map(s => s.id).includes(id))" :key="id">
                                <input type="hidden" name="student_ids[]" :value="id">
                            </template>
                            <button type="button" @click="open = !open"
                                class="input w-full flex items-center justify-between text-left">
                                <span class="text-body-md" :class="selected.length ? 'text-on-surface' : 'text-on-surface-variant'" x-text="label()"></span>
                                <span class="material-symbols-outlined text-[18px] text-on-surface-variant"
                                    :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .2s">expand_more</span>
                            </button>
                            <div x-show="open" x-transition
                                class="absolute z-50 mt-xs bg-surface-container-lowest border border-surface-border rounded-lg shadow-lg w-full overflow-hidden">
                                <template x-for="c in classes" :key="c.id">
                                    <button type="button" @click="toggle(c.id)"
                                        class="w-full flex items-center justify-between px-md py-sm hover:bg-surface-container transition-colors">
                                        <span class="text-body-md text-on-surface" x-text="c.name"></span>
                                        <span class="material-symbols-outlined text-[18px] text-primary"
                                            x-show="selected.includes(c.id)">check</span>
                                    </button>
                                </template>
                                <div x-show="classes.length === 0"
                                    class="px-md py-sm text-body-sm text-on-surface-variant">Belum ada kelas tersedia.</div>
                            </div>
                        </div>

                        {{-- Reactive student list --}}
                        <div x-show="selected.length > 0" x-transition>
                            <p class="text-label-sm font-bold text-on-surface-variant uppercase tracking-wider mb-sm">Siswa</p>
                            <div class="border border-surface-border rounded-lg overflow-hidden bg-white">
                                <div class="p-md space-y-md">
                                    <template x-for="c in classes.filter(c => selected.includes(c.id))" :key="c.id">
                                        <div>
                                            <p class="text-label-sm font-bold text-on-surface-variant uppercase tracking-wider mb-sm" x-text="c.name"></p>
                                            <div class="flex flex-wrap gap-sm">
                                                <template x-for="s in allStudents.filter(s => s.class_id === c.id)" :key="s.id">
                                                    <label class="flex items-center gap-xs px-sm py-xs rounded-full bg-surface-container border border-surface-border cursor-pointer hover:bg-surface-container-high transition-colors">
                                                        <input type="checkbox"
                                                            :value="s.id"
                                                            class="checkbox checkbox-xs"
                                                            x-model="checkedStudentIds">
                                                        <span class="text-body-sm" x-text="s.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div x-show="selected.length === 0" class="text-body-sm text-on-surface-variant italic">
                            Pilih kelas dulu untuk melihat daftar siswa.
                        </div>
                    </section>
                    {{--  PRACTICE DETAILS --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">edit_note</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Detail Materi</h4>
                        </div>
                        <div class="space-y-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Judul</label>
                                <input type="text" name="title" value="{{ old('title') }}"
                                    placeholder="e.g. Advanced Business English Conversation"
                                    class="input w-full @error('title') input-error @enderror" />
                                @error('title')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Deskripsi</label>
                                <textarea name="description" rows="4"
                                    placeholder="Berikan konteks atau instruksi untuk siswa..."
                                    class="textarea w-full resize-none @error('description') textarea-error @enderror">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </section>

                    {{--  RESOURCES & WAKTU --}}
                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">link</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Resources & Waktu</h4>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                            <div class="fieldset sm:col-span-2">
                                <label class="fieldset-legend text-on-surface">Link Eksternal</label>
                                <div class="relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">link</span>
                                    <input type="url" name="external_link" value="{{ old('external_link') }}"
                                        placeholder="https://youtube.com/..."
                                        class="input w-full pl-10 @error('external_link') input-error @enderror" />
                                </div>
                                @error('external_link')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Estimasi Durasi (menit)</label>
                                <input type="number" name="estimated_duration" value="{{ old('estimated_duration') }}"
                                    placeholder="30" min="1"
                                    class="input w-full @error('estimated_duration') input-error @enderror" />
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Deadline</label>
                                <input type="date" name="deadline" value="{{ old('deadline') }}"
                                    class="input w-full @error('deadline') input-error @enderror" />
                            </div>
                        </div>
                    </section>



                </div>

                {{-- RIGHT COLUMN --}}
                <div class="lg:col-span-4 space-y-lg">

                    {{-- Status & Submit --}}
                    <div class="bg-primary-container rounded-lg p-lg relative overflow-hidden"
                        x-data="{ published: {{ old('status') === 'published' ? 'true' : 'false' }} }">
                        <div class="relative z-10">
                            <h5 class="text-headline-md font-semibold text-on-primary mb-lg">Publikasi</h5>
                            <input type="hidden" name="status" :value="published ? 'published' : 'draft'">
                            <div class="flex items-center justify-between p-sm bg-white/10 rounded-lg mb-lg">
                                <div>
                                    <p class="text-body-md font-semibold text-on-primary" x-text="published ? 'Siap Publish' : 'Draft'"></p>
                                    <p class="text-body-sm text-on-primary/70">Draft tidak terlihat siswa.</p>
                                </div>
                                <input type="checkbox" class="toggle toggle-md" x-model="published">
                            </div>
                            <button type="submit"
                                class="w-full py-md bg-secondary-container text-on-secondary-container rounded-lg font-bold hover:opacity-90 transition-all active:scale-95">
                                Simpan Materi
                            </button>
                        </div>
                        <div class="absolute -right-6 -bottom-6 opacity-10">
                            <span class="material-symbols-outlined text-[100px] text-on-primary">edit_note</span>
                        </div>
                    </div>

                    {{-- Tips --}}
                    <div class="bg-surface-container-low border border-surface-border rounded-lg p-lg">
                        <h5 class="text-label-lg text-on-surface-variant uppercase tracking-widest mb-md">Pro Tips</h5>
                        <div class="space-y-md">
                            <div class="flex gap-md">
                                <div class="w-2 h-2 rounded-full bg-secondary mt-sm shrink-0"></div>
                                <p class="text-body-md text-on-surface-variant italic">Buat tugas singkat dan fokus agar siswa tetap konsisten belajar.</p>
                            </div>
                            <div class="flex gap-md">
                                <div class="w-2 h-2 rounded-full bg-secondary mt-sm shrink-0"></div>
                                <p class="text-body-md text-on-surface-variant italic">Tambahkan instruksi dan contoh yang jelas untuk mengurangi kebingungan.</p>
                            </div>
                            <div class="flex gap-md">
                                <div class="w-2 h-2 rounded-full bg-secondary mt-sm shrink-0"></div>
                                <p class="text-body-md text-on-surface-variant italic">Follow up di awal kelas berikutnya dan berikan feedback konstruktif.</p>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('tutor.dashboard') }}" class="btn btn-ghost w-full">Batal</a>

                </div>

            </div>
        </form>
    </div>
</x-app-layout>
