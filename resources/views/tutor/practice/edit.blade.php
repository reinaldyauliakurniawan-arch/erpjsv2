<x-app-layout>
    <x-slot name="title">Edit Tugas</x-slot>

    <div class="p-lg space-y-lg">

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

        <div class="space-y-xs">
            <h1 class="text-headline-lg font-bold text-on-surface">Edit Practice</h1>
            <p class="text-body-md text-on-surface-variant">Perbarui detail tugas self-study.</p>
        </div>

        <form method="POST" action="{{ route('tutor.practice.update', $practice->id) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

                {{-- LEFT COLUMN --}}
                <div class="lg:col-span-8 space-y-lg">

                    <section class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                        <div class="flex items-center gap-sm mb-lg pb-md border-b border-surface-border">
                            <span class="material-symbols-outlined text-secondary">edit_note</span>
                            <h4 class="text-headline-md font-semibold text-on-surface uppercase tracking-wider">Detail Materi</h4>
                        </div>
                        <div class="space-y-md">
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Judul</label>
                                <input type="text" name="title" value="{{ old('title', $practice->title) }}"
                                    placeholder="e.g. Advanced Business English Conversation"
                                    class="input w-full @error('title') input-error @enderror" />
                                @error('title')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Deskripsi</label>
                                <textarea name="description" rows="4"
                                    placeholder="Berikan konteks atau instruksi untuk siswa..."
                                    class="textarea w-full resize-none @error('description') textarea-error @enderror">{{ old('description', $practice->description) }}</textarea>
                            </div>
                        </div>
                    </section>

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
                                    <input type="url" name="external_link" value="{{ old('external_link', $practice->external_link) }}"
                                        placeholder="https://youtube.com/..."
                                        class="input w-full pl-10 @error('external_link') input-error @enderror" />
                                </div>
                                @error('external_link')<p class="label text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Estimasi Durasi (menit)</label>
                                <input type="number" name="estimated_duration" value="{{ old('estimated_duration', $practice->estimated_duration) }}"
                                    placeholder="30" min="1"
                                    class="input w-full @error('estimated_duration') input-error @enderror" />
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-legend text-on-surface">Deadline</label>
                                <input type="date" name="deadline" value="{{ old('deadline', $practice->deadline?->format('Y-m-d')) }}"
                                    class="input w-full @error('deadline') input-error @enderror" />
                            </div>
                        </div>
                    </section>

                </div>

                {{-- RIGHT COLUMN --}}
                <div class="lg:col-span-4 space-y-lg">

                    <div class="bg-primary-container rounded-lg p-lg relative overflow-hidden"
                        x-data="{ published: {{ old('status', $practice->status) === 'published' ? 'true' : 'false' }} }">
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
                                Simpan Perubahan
                            </button>
                        </div>
                        <div class="absolute -right-6 -bottom-6 opacity-10">
                            <span class="material-symbols-outlined text-[100px] text-on-primary">edit_note</span>
                        </div>
                    </div>

                    <a href="{{ route('tutor.practice.index') }}" class="btn btn-ghost w-full">Batal</a>

                </div>

            </div>
        </form>
    </div>
</x-app-layout>
