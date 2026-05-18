<x-app-layout>
    <x-slot name="title">Imports & Exports</x-slot>

    <div class="p-lg space-y-lg" style="max-width: 56rem">

        {{-- Flash --}}
        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div role="alert" class="alert alert-error alert-soft">
                <span class="material-symbols-outlined">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <h3 class="text-headline-lg font-semibold text-on-surface">Imports & Exports</h3>

        {{-- Classrooms --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Classrooms</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: name, capacity</p>
                </div>
                <a href="{{ route('admin.exports.template', 'classrooms') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Template
                </a>
            </div>
            <form method="POST" action="{{ route('admin.imports.classrooms') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt"
                        class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

        {{-- Programs --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Programs</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: name, type, price, sessions, min_quota</p>
                </div>
                <a href="{{ route('admin.exports.template', 'programs') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Template
                </a>
            </div>
            <form method="POST" action="{{ route('admin.imports.programs') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt"
                        class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

        {{-- Tutors --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Tutors</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: name, email, persona</p>
                </div>
                <a href="{{ route('admin.exports.template', 'tutors') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Template
                </a>
            </div>
            <form method="POST" action="{{ route('admin.imports.tutors') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt"
                        class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

        {{-- Students --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Students</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: name, email, notes</p>
                </div>
                <a href="{{ route('admin.exports.template', 'students') }}"
                    class="btn btn-ghost btn-sm gap-xs shrink-0">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Template
                </a>
            </div>
            <form method="POST" action="{{ route('admin.imports.students') }}" enctype="multipart/form-data"
                class="flex gap-md items-end">
                @csrf
                <div class="fieldset flex-1">
                    <label class="fieldset-legend text-on-surface">File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt"
                        class="file-input w-full" required />
                </div>
                <button type="submit"
                    class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Import
                </button>
            </form>
        </div>

        {{-- Exports --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <h4 class="text-headline-md font-semibold text-on-surface">Exports</h4>
            <div class="flex flex-wrap gap-sm">
                <a href="{{ route('admin.exports.attendance') }}"
                    class="btn btn-ghost gap-sm">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Export Attendance
                </a>
            </div>
        </div>

    </div>
</x-app-layout>



