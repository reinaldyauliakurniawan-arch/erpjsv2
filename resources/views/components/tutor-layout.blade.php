@props(['title' => 'Tutor Portal'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }} — {{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface font-sans antialiased">

    <!-- Sidebar Desktop -->
    <aside class="fixed left-0 top-0 h-screen w-[280px] hidden lg:flex flex-col bg-surface-container-lowest border-r border-surface-border p-md z-30">
        <div class="mb-xl pt-md px-sm">
            <h1 class="text-headline-lg font-bold text-secondary">Just Speak</h1>
            <p class="text-body-md text-on-surface-variant">Tutor Portal</p>
        </div>

        <nav class="flex-1 space-y-xs">
            <a href="{{ route('tutor.dashboard') }}"
               class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
               {{ request()->routeIs('tutor.dashboard') ? 'text-secondary bg-surface-container-low border-r-4 border-secondary' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-secondary' }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('tutor.schedule.index') }}"
               class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
               {{ request()->routeIs('tutor.schedule.*') ? 'text-secondary bg-surface-container-low border-r-4 border-secondary' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-secondary' }}">
                <span class="material-symbols-outlined">calendar_month</span>
                <span>Jadwal</span>
            </a>
            <a href="{{ route('tutor.attendance.index') }}"
               class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
               {{ request()->routeIs('tutor.attendance.*') ? 'text-secondary bg-surface-container-low border-r-4 border-secondary' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-secondary' }}">
                <span class="material-symbols-outlined">assignment_turned_in</span>
                <span>Presensi</span>
            </a>
            <a href="{{ route('tutor.availability.index') }}"
               class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
               {{ request()->routeIs('tutor.availability.*') ? 'text-secondary bg-surface-container-low border-r-4 border-secondary' : 'text-on-surface-variant hover:bg-surface-container-low hover:text-secondary' }}">
                <span class="material-symbols-outlined">event_available</span>
                <span>Ketersediaan</span>
            </a>
        </nav>

        <div class="pt-lg border-t border-surface-border space-y-xs">
            <a href="{{ route('profile.edit') }}"
               class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low hover:text-secondary transition-all">
                <span class="material-symbols-outlined">manage_accounts</span>
                <span>Profile</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low hover:text-secondary transition-all">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Top AppBar -->
    <header class="fixed top-0 right-0 left-0 lg:left-[280px] z-40 bg-surface border-b border-surface-border">
        <div class="flex justify-between items-center px-gutter py-sm max-w-container-max mx-auto">
            <div class="flex items-center gap-md">
                <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                    class="lg:hidden p-xs rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h2 class="text-headline-md font-semibold text-secondary">{{ $title }}</h2>
            </div>
            <div class="flex items-center gap-sm">
                <div class="w-9 h-9 rounded-full bg-secondary-container flex items-center justify-center text-on-secondary-container font-bold text-sm">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <span class="hidden md:block text-sm font-semibold text-on-surface">{{ Auth::user()->name }}</span>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden fixed inset-0 z-50 lg:hidden">
        <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('mobile-menu').classList.add('hidden')"></div>
        <aside class="absolute left-0 top-0 h-full w-[280px] bg-surface-container-lowest border-r border-surface-border p-md flex flex-col z-10">
            <div class="mb-xl pt-md px-sm flex justify-between items-center">
                <div>
                    <h1 class="text-headline-lg font-bold text-secondary">Just Speak</h1>
                    <p class="text-body-md text-on-surface-variant">Tutor Portal</p>
                </div>
                <button onclick="document.getElementById('mobile-menu').classList.add('hidden')"
                    class="p-xs rounded-lg text-on-surface-variant hover:bg-surface-container-low">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <nav class="flex-1 space-y-xs">
                <a href="{{ route('tutor.dashboard') }}"
                   class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
                   {{ request()->routeIs('tutor.dashboard') ? 'text-secondary bg-surface-container-low' : 'text-on-surface-variant hover:bg-surface-container-low' }}">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('tutor.schedule.index') }}"
                   class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
                   {{ request()->routeIs('tutor.schedule.*') ? 'text-secondary bg-surface-container-low' : 'text-on-surface-variant hover:bg-surface-container-low' }}">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span>Jadwal</span>
                </a>
                <a href="{{ route('tutor.attendance.index') }}"
                   class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
                   {{ request()->routeIs('tutor.attendance.*') ? 'text-secondary bg-surface-container-low' : 'text-on-surface-variant hover:bg-surface-container-low' }}">
                    <span class="material-symbols-outlined">assignment_turned_in</span>
                    <span>Presensi</span>
                </a>
                <a href="{{ route('tutor.availability.index') }}"
                   class="flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold transition-all
                   {{ request()->routeIs('tutor.availability.*') ? 'text-secondary bg-surface-container-low' : 'text-on-surface-variant hover:bg-surface-container-low' }}">
                    <span class="material-symbols-outlined">event_available</span>
                    <span>Ketersediaan</span>
                </a>
            </nav>
            <div class="pt-lg border-t border-surface-border space-y-xs">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-md px-md py-sm rounded-lg text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low transition-all">
                        <span class="material-symbols-outlined">logout</span>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <!-- Main Content -->
    <main class="pt-[64px] lg:ml-[280px] min-h-screen bg-background">
        {{ $slot }}
    </main>

    <!-- Bottom Nav Mobile (4 items) -->
    <nav class="fixed bottom-0 w-full lg:hidden z-40 bg-surface border-t border-surface-border">
        <div class="flex justify-around items-center h-16 px-md">
            <a href="{{ route('tutor.dashboard') }}"
               class="flex flex-col items-center justify-center gap-xs {{ request()->routeIs('tutor.dashboard') ? 'text-secondary' : 'text-on-surface-variant' }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-[10px] font-semibold">Home</span>
            </a>
            <a href="{{ route('tutor.schedule.index') }}"
               class="flex flex-col items-center justify-center gap-xs {{ request()->routeIs('tutor.schedule.*') ? 'text-secondary' : 'text-on-surface-variant' }}">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="text-[10px] font-semibold">Jadwal</span>
            </a>
            <a href="{{ route('tutor.attendance.index') }}"
               class="flex flex-col items-center justify-center gap-xs {{ request()->routeIs('tutor.attendance.*') ? 'text-secondary' : 'text-on-surface-variant' }}">
                <span class="material-symbols-outlined">assignment_turned_in</span>
                <span class="text-[10px] font-semibold">Presensi</span>
            </a>
            <a href="{{ route('tutor.availability.index') }}"
               class="flex flex-col items-center justify-center gap-xs {{ request()->routeIs('tutor.availability.*') ? 'text-secondary' : 'text-on-surface-variant' }}">
                <span class="material-symbols-outlined">event_available</span>
                <span class="text-[10px] font-semibold">Ketersediaan</span>
            </a>
        </div>
    </nav>

</body>
</html>



