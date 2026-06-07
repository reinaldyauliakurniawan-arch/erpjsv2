<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Just Speak') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .guest-bg {
            min-height: 100vh;
            background: linear-gradient(135deg, #00a982 0%, #007a60 50%, #006b5a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .guest-bg::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            top: -200px;
            left: -200px;
        }
        .guest-bg::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            bottom: -150px;
            right: -100px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 26rem;
            position: relative;
            z-index: 1;
        }
        .glass-input {
            background: rgba(255,255,255,0.2) !important;
            border: 1px solid rgba(255,255,255,0.4) !important;
            border-radius: 12px !important;
            color: #fff !important;
            backdrop-filter: blur(4px);
        }
        .glass-input::placeholder {
            color: rgba(255,255,255,0.6) !important;
        }
        .glass-input:focus {
            outline: none !important;
            border-color: rgba(255,255,255,0.8) !important;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.15) !important;
        }
        .glass-btn {
            background: rgba(255,255,255,0.9) !important;
            color: #006b5a !important;
            border: none !important;
            border-radius: 12px !important;
            font-weight: 700 !important;
            letter-spacing: 0.02em;
            transition: all 0.2s ease;
            padding: 0.75rem !important;
        }
        .glass-btn:hover {
            background: #fff !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
            transform: translateY(-1px);
        }
        .glass-label {
            color: rgba(255,255,255,0.9) !important;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .glass-checkbox {
            accent-color: #fff;
        }
        .guest-brand h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .guest-brand p {
            color: rgba(255,255,255,0.75);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .guest-divider {
            height: 1px;
            background: rgba(255,255,255,0.2);
            margin: 1.5rem 0;
        }
    </style>
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
