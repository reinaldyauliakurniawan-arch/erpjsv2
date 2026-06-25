<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Just Speak') }} - {{ $title ?? 'Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        use App\Models\Setting;

        $colorPrimary   = Setting::get('color_primary',   '#065f46');
        $colorSecondary = Setting::get('color_secondary', '#059669');
        $colorSidebar   = Setting::get('color_sidebar',   '#054e3b');

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

{{--
    APP SHELL — single Alpine root controls sidebar on ALL screen sizes.
    Desktop: hamburger toggles collapse (sidebar slides away, content full-width).
    Mobile:  hamburger opens overlay drawer.

    Replaced DaisyUI drawer (caused "weird empty bar" due to CSS grid space
    reservation even when hidden). This Alpine approach has zero layout
    side-effects when the drawer is closed.
--}}
<div x-data="appShell()" @keydown.escape.window="mobileOpen = false">

{{-- DESKTOP SIDEBAR --}}
<aside
    class="app-sidebar {{ $sidebarIsLight ? 'app-sidebar--light' : '' }}"
    x-show="!collapsed"
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
>
    @include('partials.sidebar-nav')
</aside>

{{-- TOPBAR --}}
<header class="app-topbar" :class="collapsed ? 'app-topbar--expanded' : ''">
    <div class="flex justify-between items-center w-full gap-md">
        <div class="flex items-center gap-md flex-shrink-0">
            <button type="button" @click="toggle()"
                class="app-mobile-trigger"
                aria-label="Toggle sidebar">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <h2 class="text-headline-md font-semibold text-on-surface">{{ $title ?? 'Dashboard' }}</h2>
        </div>

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

{{-- MOBILE DRAWER — pure overlay, no DaisyUI grid --}}
<div
    x-show="mobileOpen"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 lg:hidden"
>
    <div class="absolute inset-0 bg-black/40" @click="mobileOpen = false"></div>
    <aside
        class="app-sidebar {{ $sidebarIsLight ? 'app-sidebar--light' : '' }} absolute left-0 top-0 h-full"
        style="width:var(--size-sidebar-width);position:fixed;"
        @click.stop
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
    >
        <div class="flex justify-end p-sm">
            <button type="button" @click="mobileOpen = false"
                class="app-mobile-trigger app-mobile-trigger--sidebar"
                aria-label="Close menu">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        @include('partials.sidebar-nav')
    </aside>
</div>

{{-- MAIN CONTENT --}}
<main class="app-main" :class="collapsed ? 'app-main--expanded' : ''">
    {{ $slot }}
</main>

</div>{{-- end x-data root --}}
</body>
</html>
