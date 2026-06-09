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
        @if(session('warning'))
            <div role="alert" class="alert alert-warning alert-soft">
                <span class="material-symbols-outlined">warning</span>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        <h3 class="text-headline-lg font-semibold text-on-surface">Imports & Exports</h3>

        {{-- Classrooms --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
            <div class="flex items-start justify-between gap-md">
                <div>
                    <h4 class="text-headline-md font-semibold text-on-surface">Classrooms</h4>
                    <p class="text-body-sm text-on-surface-variant">CSV: name, capacity, is_at_just_speak</p>
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
                    <p class="text-body-sm text-on-surface-variant">CSV: name, email, persona, program_name, rate, phone, status</p>
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
                    <p class="text-body-sm text-on-surface-variant">CSV: name, email, notes, phone, education_level</p>
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
        {{-- Enrollments --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Enrollments</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: student_email, program_name, class_session_name, enrollment_date, expiry_date, payment_method (full upfront/installment), payment_channel (cash/bank), total_amount, payment_status (pending/partial/full), status (active/graduate/expired/cancelled/waitlist), remaining_meetings</p>
        </div>
        <a href="{{ route('admin.exports.template', 'enrollments') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.enrollments') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- Installments --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Installments</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: student_email, program_name, amount, due_date, paid_at, payment_channel (cash/bank)</p>
        </div>
        <a href="{{ route('admin.exports.template', 'installments') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.installments') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- Schedules --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Schedules</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: student_email, program_name, classroom_name, day, time_block, class_session_name</p>
        </div>
        <a href="{{ route('admin.exports.template', 'schedules') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.schedules') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- Tutor Availability --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Tutor Availability</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: tutor_email, day, time_block, status (available/not_available/occupied)</p>
        </div>
        <a href="{{ route('admin.exports.template', 'tutor_availability') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.tutor-availability') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- Class Sessions --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Class Sessions</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: name, program_name, class_type (private/group), status (active/inactive)</p>
        </div>
        <a href="{{ route('admin.exports.template', 'class_sessions') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.class-sessions') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- RAB --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">RAB</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: year, division, account_name, account_code, activity, q1, q2, q3, q4</p>
        </div>
        <a href="{{ route('admin.exports.template', 'rabs') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.rabs') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- Fixed Assets --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Fixed Assets</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: name, category, acquired_at, cost, salvage_value, useful_life_in_months, depreciation_method, notes, expense_account_code, accumulated_account_code, is_active</p>
        </div>
        <a href="{{ route('admin.exports.template', 'fixed_assets') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.fixed-assets') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
        </button>
    </form>
</div>

{{-- Tracker Columns --}}
<div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
    <div class="flex items-start justify-between gap-md">
        <div>
            <h4 class="text-headline-md font-semibold text-on-surface">Tracker Columns</h4>
            <p class="text-body-sm text-on-surface-variant">CSV: name, order</p>
        </div>
        <a href="{{ route('admin.exports.template', 'tracker_columns') }}" class="btn btn-ghost btn-sm gap-xs shrink-0">
            <span class="material-symbols-outlined text-[16px]">download</span>Template
        </a>
    </div>
    <form method="POST" action="{{ route('admin.imports.tracker-columns') }}" enctype="multipart/form-data" class="flex gap-md items-end">
        @csrf
        <div class="fieldset flex-1">
            <label class="fieldset-legend text-on-surface">File CSV</label>
            <input type="file" name="file" accept=".csv,.txt" class="file-input w-full" required />
        </div>
        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm mb-xs">
            <span class="material-symbols-outlined text-[18px]">upload</span>Import
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
