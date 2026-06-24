<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Just Speak') }} - {{ $title ?? 'Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- ════════════════════════════════════════════════════════════════
         Runtime theme overrides — driven by Settings (admin/settings/colors).
         All tokens are centralized in app.css; we only override the three
         brand colors + sidebar text contrast here. The contrast check is
         done server-side (no client-side JS hack).
         ════════════════════════════════════════════════════════════════ --}}
    @php
        use App\Models\Setting;

        $colorPrimary   = Setting::get('color_primary',   '#065f46');
        $colorSecondary = Setting::get('color_secondary', '#059669');
        $colorSidebar   = Setting::get('color_sidebar',   '#111827');

        // Server-side luminance check — flips sidebar text color automatically.
        $hex = ltrim($colorSidebar, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        $sidebarIsLight = $luminance >= 0.4;
    @endphp
    <style>
        :root {
            --color-primary: {{ $colorPrimary }};
            --color-secondary: {{ $colorSecondary }};
            --color-sidebar-bg: {{ $colorSidebar }};
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-sans antialiased">

{{-- SIDEBAR DESKTOP --}}
<aside class="app-sidebar hidden lg:flex {{ $sidebarIsLight ? 'app-sidebar--light' : '' }}">
    @include('partials.sidebar-nav')
</aside>

{{-- TOP BAR --}}
<header class="app-topbar">
    <div class="flex justify-between items-center w-full gap-md">
        <div class="flex items-center gap-md flex-shrink-0">
            <button type="button" onclick="document.getElementById('mobile-drawer').checked = true"
                class="app-mobile-trigger lg:hidden"
                aria-label="Open menu">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <h2 class="text-headline-md font-semibold text-on-surface">{{ $title ?? 'Dashboard' }}</h2>
        </div>

        {{-- Global search — admin & CFO only. Sits in the topbar so it's
             available on every page without consuming content area.
             Brand Guide Section 7: "The topbar is identical across modes"
             so the search bar lives here rather than in page content. --}}
        @if(in_array(auth()->user()->role, ['admin', 'cfo']))
            <div class="flex-1 max-w-md hidden md:block">
                <x-search-bar />
            </div>
        @endif

        <div class="flex items-center gap-sm flex-shrink-0">
            <div class="app-user-avatar">
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
        <label for="mobile-drawer" class="drawer-overlay" aria-label="Close menu"></label>
        <aside class="app-sidebar app-sidebar--in-drawer {{ $sidebarIsLight ? 'app-sidebar--light' : '' }}">
            <div class="flex justify-end p-sm">
                <label for="mobile-drawer" class="app-mobile-trigger app-mobile-trigger--sidebar" aria-label="Close menu">
                    <span class="material-symbols-outlined">close</span>
                </label>
            </div>
            @include('partials.sidebar-nav')
        </aside>
    </div>
</div>

{{-- MAIN CONTENT --}}
<main class="app-main">
    {{ $slot }}
</main>

</body>
</html>
