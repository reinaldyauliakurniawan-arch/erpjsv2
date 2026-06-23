<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Just Speak') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="guest-bg">
        <div class="glass-card">
            {{-- Brand --}}
            <div class="guest-brand text-center mb-xl">
                <img src="{{ asset('images/logo.png') }}" alt="Just Speak" style="height:60px;margin:0 auto 0.5rem;display:block;">
                <p>English Learning Management System</p>
            </div>

            <div class="guest-divider"></div>

            {{ $slot }}
        </div>
    </div>
</body>
</html>
