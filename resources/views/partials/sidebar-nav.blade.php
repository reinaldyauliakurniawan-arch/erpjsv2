{{--
    Sidebar navigation — shared between desktop sidebar and mobile drawer.
    Reads the authenticated user's role and renders the right links.
    Single source of truth — update once, applies to both desktop & mobile.
--}}
@php
    $role = auth()->user()->role;
    $homeRoute = match ($role) {
        'cfo'     => route('finance.index'),
        'tutor'   => route('tutor.dashboard'),
        'student' => route('student.dashboard'),
        'admin'   => route('admin.dashboard'),
        default   => '/',
    };
    $portalLabel = match ($role) {
        'admin'   => 'Admin Portal',
        'cfo'     => 'CFO Portal',
        'tutor'   => 'Tutor Portal',
        'student' => 'Student Portal',
        default   => 'Portal',
    };
@endphp

<div class="app-sidebar__header">
    <a href="{{ $homeRoute }}">
        <img src="{{ asset('images/logo.png') }}" alt="Just Speak" style="height:40px;display:block;">
    </a>
    <p class="text-body-md mt-xs sidebar-portal-label">{{ $portalLabel }}</p>
</div>

<nav class="app-sidebar__nav space-y-xs">
    @if($role === 'admin')
        <x-sidebar-link href="{{ route('admin.dashboard') }}" :active="request()->routeIs('admin.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.students.index') }}" :active="request()->routeIs('admin.students.*')" icon="school">Students</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.tracker.index') }}" :active="request()->routeIs('admin.tracker.*')" icon="checklist">Tracker</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.tutors.index') }}" :active="request()->routeIs('admin.tutors.*')" icon="person_search">Tutors</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.programs.index') }}" :active="request()->routeIs('admin.programs.*')" icon="menu_book">Programs</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.enrollments.index') }}" :active="request()->routeIs('admin.enrollments.*')" icon="how_to_reg">Enrollments</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.classrooms.index') }}" :active="request()->routeIs('admin.classrooms.*')" icon="meeting_room">Classrooms</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.class-sessions.index') }}" :active="request()->routeIs('admin.class-sessions.*')" icon="groups">Class Sessions</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.schedule.index') }}" :active="request()->routeIs('admin.schedule.*')" icon="calendar_month">Jadwal</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.attendance.index') }}" :active="request()->routeIs('admin.attendance.*')" icon="assignment_turned_in">Absensi</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.settings.index') }}" :active="request()->routeIs('admin.settings.*')" icon="settings">Settings</x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.imports.index') }}" :active="request()->routeIs('admin.imports.*')" icon="upload_file">Import/Export</x-sidebar-link>

    @elseif($role === 'cfo')
        <x-sidebar-link href="{{ route('finance.index') }}" :active="request()->routeIs('finance.index')" icon="account_balance">Dashboard</x-sidebar-link>
        <div class="pt-sm">
            <p class="app-sidebar__section-label">Laporan</p>
            <x-sidebar-link href="{{ route('finance.journals.index') }}" :active="request()->routeIs('finance.journals.*')" icon="receipt_long">Journals</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.general-ledger') }}" :active="request()->routeIs('finance.reports.general-ledger')" icon="menu_book">General Ledger</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.trial-balance') }}" :active="request()->routeIs('finance.reports.trial-balance')" icon="balance">Trial Balance</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.adjusted-trial-balance') }}" :active="request()->routeIs('finance.reports.adjusted-trial-balance')" icon="rule">Adjusted Trial Balance</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.adjusting-journals.index') }}" :active="request()->routeIs('finance.adjusting-journals.*')" icon="auto_fix_high">Jurnal Penyesuaian</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.profit-loss') }}" :active="request()->routeIs('finance.reports.profit-loss')" icon="trending_up">Profit &amp; Loss</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.balance-sheet') }}" :active="request()->routeIs('finance.reports.balance-sheet')" icon="summarize">Balance Sheet</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.equity-statement') }}" :active="request()->routeIs('finance.reports.equity-statement')" icon="change_history">Perubahan Ekuitas</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.cash-flow') }}" :active="request()->routeIs('finance.reports.cash-flow')" icon="water">Cash Flow</x-sidebar-link>
        </div>
        <div class="pt-sm">
            <p class="app-sidebar__section-label">Perencanaan</p>
            <x-sidebar-link href="{{ route('finance.rab.index') }}" :active="request()->routeIs('finance.rab.*')" icon="event_note">RAB</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.rab-realisasi.index') }}" :active="request()->routeIs('finance.rab-realisasi.*')" icon="monitoring">Realisasi RAB</x-sidebar-link>
        </div>
        <div class="pt-sm">
            <p class="app-sidebar__section-label">Master &amp; Operasional</p>
            <x-sidebar-link href="{{ route('finance.accounts.index') }}" :active="request()->routeIs('finance.accounts.*')" icon="account_tree">Accounts</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.assets.index') }}" :active="request()->routeIs('finance.assets.*')" icon="inventory_2">Aset Tetap</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.payroll.index') }}" :active="request()->routeIs('finance.payroll.*')" icon="payments">Payroll</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.reports.deferred-revenue') }}" :active="request()->routeIs('finance.reports.deferred-revenue')" icon="savings">Deferred Revenue</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.imports') }}" :active="request()->routeIs('finance.imports')" icon="upload_file">Import Data</x-sidebar-link>
        </div>

    @elseif($role === 'tutor')
        <x-sidebar-link href="{{ route('tutor.dashboard') }}" :active="request()->routeIs('tutor.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
        <x-sidebar-link href="{{ route('tutor.schedule.index') }}" :active="request()->routeIs('tutor.schedule.*')" icon="calendar_month">Jadwal</x-sidebar-link>
        <x-sidebar-link href="{{ route('tutor.attendance.index') }}" :active="request()->routeIs('tutor.attendance.*')" icon="assignment_turned_in">Absensi</x-sidebar-link>
        <x-sidebar-link href="{{ route('tutor.availability.index') }}" :active="request()->routeIs('tutor.availability.*')" icon="event_available">Availability</x-sidebar-link>
        <x-sidebar-link href="{{ route('tutor.practice.create') }}" :active="request()->routeIs('tutor.practice.*')" icon="edit_note">Buat Tugas</x-sidebar-link>
        <x-sidebar-link href="{{ route('tutor.tracker.index') }}" :active="request()->routeIs('tutor.tracker.*')" icon="query_stats">Self-Study Tracker</x-sidebar-link>

    @elseif($role === 'student')
        <x-sidebar-link href="{{ route('student.dashboard') }}" :active="request()->routeIs('student.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
        <x-sidebar-link href="{{ route('student.practice.index') }}" :active="request()->routeIs('student.practice.*')" icon="edit_note">Practice</x-sidebar-link>
        <x-sidebar-link href="{{ route('student.tracker.index') }}" :active="request()->routeIs('student.tracker.*')" icon="query_stats">Self-Study Tracker</x-sidebar-link>
    @endif
</nav>

<div class="app-sidebar__footer space-y-xs">
    <a href="{{ route('profile.edit') }}" class="sidebar-link">
        <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
        <span>Profile</span>
    </a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="sidebar-link sidebar-link--danger w-full">
            <span class="material-symbols-outlined text-[20px]">logout</span>
            <span>Sign Out</span>
        </button>
    </form>
</div>
