<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Just Speak') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface flex items-center justify-center p-md">

    <div class="w-full" style="max-width: 24rem">

        {{-- Brand --}}
        <div class="text-center mb-xl">
            <h1 class="text-headline-lg font-bold text-primary-container">Just Speak</h1>
            <p class="text-body-md text-on-surface-variant mt-xs">English Learning Management System</p>
        </div>

        {{-- Card --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-xl shadow-sm">
            {{ $slot }}
        </div>

    </div>

</body>
</html>



