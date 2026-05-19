<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Just Speak') }} — {{ $title ?? 'Dashboard' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $color_primary   = App\Models\Setting::get('color_primary', '#065f46');
        $color_secondary = App\Models\Setting::get('color_secondary', '#059669');
        $color_sidebar   = App\Models\Setting::get('color_sidebar', '#111827');
    @endphp
    <style>
        :root {
            --color-primary: {{ $color_primary }};
            --color-secondary: {{ $color_secondary }};
            --color-sidebar: {{ $color_sidebar }};
            --sidebar-text: #9CA3AF;
            --sidebar-text-active: #ffffff;
            --sidebar-accent: #3B82F6;
        }

        .sidebar-link {
            color: var(--sidebar-text);
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,.05);
            color: var(--sidebar-text-active);
        }
        .sidebar-link-active {
            background: rgba(255,255,255,.15);
            color: var(--sidebar-text-active);
            border-left: 3px solid var(--sidebar-accent) !important;
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-sans antialiased">

{{-- SIDEBAR DESKTOP --}}
<aside class="fixed left-0 top-0 h-screen w-[240px] hidden lg:flex flex-col z-30" style="background:{{ $color_sidebar }};border-right:1px solid rgba(255,255,255,.08)">

    <div class="px-lg pt-xl pb-lg" style="border-bottom:1px solid rgba(255,255,255,.08)">
        <h1 class="text-headline-lg font-bold" style="color:var(--sidebar-text-active)">Just Speak</h1>
        <p class="text-body-md mt-xs" style="color:var(--sidebar-text-active)">
            @if(Auth::user()->role === 'admin') Admin Portal
            @elseif(Auth::user()->role === 'cfo') CFO Portal
            @elseif(Auth::user()->role === 'tutor') Tutor Portal
            @elseif(Auth::user()->role === 'student') Student Portal
            @endif
        </p>
    </div>

    <nav class="flex-1 px-sm space-y-xs overflow-y-auto py-md">
        @if(Auth::user()->role === 'admin')
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

        @elseif(Auth::user()->role === 'cfo')
            <x-sidebar-link href="{{ route('finance.index') }}" :active="request()->routeIs('finance.index')" icon="account_balance">Dashboard</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.accounts.index') }}" :active="request()->routeIs('finance.accounts.*')" icon="account_tree">Accounts</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.journals.index') }}" :active="request()->routeIs('finance.journals.*')" icon="receipt_long">Journals</x-sidebar-link>
            <x-sidebar-link href="{{ route('finance.payroll.index') }}" :active="request()->routeIs('finance.payroll.*')" icon="payments">Payroll</x-sidebar-link>
            <div class="pt-sm">
                <p class="text-label-lg px-md pb-xs tracking-widest uppercase" style="color:#6B7280">Reports</p>
                <x-sidebar-link href="{{ route('finance.reports.trial-balance') }}" :active="request()->routeIs('finance.reports.trial-balance')" icon="balance">Trial Balance</x-sidebar-link>
                <x-sidebar-link href="{{ route('finance.reports.profit-loss') }}" :active="request()->routeIs('finance.reports.profit-loss')" icon="trending_up">Profit & Loss</x-sidebar-link>
                <x-sidebar-link href="{{ route('finance.reports.balance-sheet') }}" :active="request()->routeIs('finance.reports.balance-sheet')" icon="summarize">Balance Sheet</x-sidebar-link>
                <x-sidebar-link href="{{ route('finance.reports.deferred-revenue') }}" :active="request()->routeIs('finance.reports.deferred-revenue')" icon="savings">Deferred Revenue</x-sidebar-link>
            </div>

        @elseif(Auth::user()->role === 'tutor')
            <x-sidebar-link href="{{ route('tutor.dashboard') }}" :active="request()->routeIs('tutor.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
            <x-sidebar-link href="{{ route('tutor.schedule.index') }}" :active="request()->routeIs('tutor.schedule.*')" icon="calendar_month">Jadwal</x-sidebar-link>
            <x-sidebar-link href="{{ route('tutor.attendance.index') }}" :active="request()->routeIs('tutor.attendance.*')" icon="assignment_turned_in">Absensi</x-sidebar-link>
            <x-sidebar-link href="{{ route('tutor.availability.index') }}" :active="request()->routeIs('tutor.availability.*')" icon="event_available">Availabilitas</x-sidebar-link>

        @elseif(Auth::user()->role === 'student')
            <x-sidebar-link href="{{ route('student.dashboard') }}" :active="request()->routeIs('student.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
        @endif
    </nav>

    <div class="px-sm pb-lg pt-md space-y-xs" style="border-top:1px solid rgba(255,255,255,.08)">
        <a href="{{ route('profile.edit') }}"
           class="flex items-center gap-md px-md py-sm rounded-lg text-body-md transition-all sidebar-link"
           onmouseover="this.style.background='rgba(255,255,255,.05)'"
           onmouseout="this.style.background='transparent'">
            <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
            <span>Profile</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="w-full flex items-center gap-md px-md py-sm rounded-lg text-body-md transition-all"
                style="color:#9CA3AF"
                onmouseover="this.style.background='rgba(255,255,255,.05)';this.style.color='#EF4444'"
                onmouseout="this.style.background='transparent';this.style.color='#9CA3AF'">
                <span class="material-symbols-outlined text-[20px]">logout</span>
                <span>Sign Out</span>
            </button>
        </form>
    </div>
</aside>

{{-- TOP BAR --}}
<header class="fixed top-0 right-0 left-0 lg:left-[240px] h-16 z-40 flex items-center px-gutter" style="background:#ffffff;border-bottom:1px solid #E5E7EB;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <div class="flex justify-between items-center w-full">
        <div class="flex items-center gap-md">
            <button onclick="document.getElementById('mobile-drawer').checked = true"
                class="lg:hidden p-xs rounded-lg transition-colors text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <h2 class="text-headline-md font-semibold text-on-surface">{{ $title ?? 'Dashboard' }}</h2>
        </div>
        <div class="flex items-center gap-sm">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm text-white" style="background:{{ $color_secondary }}">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="hidden md:block">
                <p class="text-body-md font-semibold text-on-surface leading-tight">{{ Auth::user()->name }}</p>
                <p class="text-label-lg text-on-surface-variant capitalize">{{ Auth::user()->role }}</p>
            </div>
        </div>
    </div>
</header>

{{-- MOBILE DRAWER --}}
<div class="drawer lg:hidden">
    <input id="mobile-drawer" type="checkbox" class="drawer-toggle" />
    <div class="drawer-side z-50">
        <label for="mobile-drawer" class="drawer-overlay"></label>
        <aside class="w-[240px] min-h-full flex flex-col p-md" style="background:{{ $color_sidebar }}">
            <div class="flex justify-between items-center mb-lg">
                <div>
                    <h1 class="text-headline-md font-bold" style="color:var(--sidebar-text-active)">Just Speak</h1>
                    <p class="text-body-md" style="color:#9CA3AF">
                        @if(Auth::user()->role === 'admin') Admin Portal
                        @elseif(Auth::user()->role === 'cfo') CFO Portal
                        @elseif(Auth::user()->role === 'tutor') Tutor Portal
                        @elseif(Auth::user()->role === 'student') Student Portal
                        @endif
                    </p>
                </div>
                <label for="mobile-drawer" class="btn btn-ghost btn-sm btn-circle" style="color:#9CA3AF">
                    <span class="material-symbols-outlined">close</span>
                </label>
            </div>
            <nav class="flex-1 space-y-xs">
                @if(Auth::user()->role === 'admin')
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
                @elseif(Auth::user()->role === 'cfo')
                    <x-sidebar-link href="{{ route('finance.index') }}" :active="request()->routeIs('finance.index')" icon="account_balance">Dashboard</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.accounts.index') }}" :active="request()->routeIs('finance.accounts.*')" icon="account_tree">Accounts</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.journals.index') }}" :active="request()->routeIs('finance.journals.*')" icon="receipt_long">Journals</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.payroll.index') }}" :active="request()->routeIs('finance.payroll.*')" icon="payments">Payroll</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.reports.trial-balance') }}" :active="request()->routeIs('finance.reports.trial-balance')" icon="balance">Trial Balance</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.reports.profit-loss') }}" :active="request()->routeIs('finance.reports.profit-loss')" icon="trending_up">Profit & Loss</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.reports.balance-sheet') }}" :active="request()->routeIs('finance.reports.balance-sheet')" icon="summarize">Balance Sheet</x-sidebar-link>
                    <x-sidebar-link href="{{ route('finance.reports.deferred-revenue') }}" :active="request()->routeIs('finance.reports.deferred-revenue')" icon="savings">Deferred Revenue</x-sidebar-link>
                @elseif(Auth::user()->role === 'tutor')
                    <x-sidebar-link href="{{ route('tutor.dashboard') }}" :active="request()->routeIs('tutor.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
                    <x-sidebar-link href="{{ route('tutor.schedule.index') }}" :active="request()->routeIs('tutor.schedule.*')" icon="calendar_month">Jadwal</x-sidebar-link>
                    <x-sidebar-link href="{{ route('tutor.attendance.index') }}" :active="request()->routeIs('tutor.attendance.*')" icon="assignment_turned_in">Absensi</x-sidebar-link>
                    <x-sidebar-link href="{{ route('tutor.availability.index') }}" :active="request()->routeIs('tutor.availability.*')" icon="event_available">Availabilitas</x-sidebar-link>
                @elseif(Auth::user()->role === 'student')
                    <x-sidebar-link href="{{ route('student.dashboard') }}" :active="request()->routeIs('student.dashboard')" icon="dashboard">Dashboard</x-sidebar-link>
                @endif
            </nav>
            <div class="pt-md" style="border-top:1px solid rgba(255,255,255,.08)">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-md px-md py-sm rounded-lg text-body-md"
                        style="color:#9CA3AF">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </aside>
    </div>
</div>

{{-- MAIN CONTENT --}}
<main class="pt-16 lg:ml-[240px] min-h-screen" style="background:#F3F4F6">
    {{ $slot }}
</main>

<script>
function hexToRgb(hex) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return {r, g, b};
}
function luminance({r, g, b}) {
    const a = [r, g, b].map(v => {
        v /= 255;
        return v <= 0.03928 ? v/12.92 : Math.pow((v+0.055)/1.055, 2.4);
    });
    return 0.2126*a[0] + 0.7152*a[1] + 0.0722*a[2];
}

const sidebarColor = '{{ $color_sidebar }}';
const lum = luminance(hexToRgb(sidebarColor));
const isDark = lum < 0.4;
const root = document.documentElement;

if (!isDark) {
    root.style.setProperty('--sidebar-text', '#374151');
    root.style.setProperty('--sidebar-text-active', '#111827');
    root.style.setProperty('--sidebar-accent', '{{ $color_primary }}');
} else {
    root.style.setProperty('--sidebar-text', '#9CA3AF');
    root.style.setProperty('--sidebar-text-active', '#ffffff');
    root.style.setProperty('--sidebar-accent', '{{ $color_secondary }}');
}
</script>
</body>
</html>
