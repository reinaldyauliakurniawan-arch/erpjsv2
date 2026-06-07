<x-guest-layout>
    <x-auth-session-status class="mb-md" :status="session('status')" />
    <form method="POST" action="{{ route('login') }}" class="space-y-md">
        @csrf
        {{-- Email --}}
        <div class="fieldset">
            <label class="glass-label">Email</label>
            <input id="email" type="email" name="email"
                value="{{ old('email') }}"
                required autofocus autocomplete="username"
                placeholder="nama@email.com"
                class="input w-full glass-input @error('email') input-error @enderror" />
            @error('email')
                <p class="label" style="color:rgba(255,200,200,0.9)">{{ $message }}</p>
            @enderror
        </div>
        {{-- Password --}}
        <div class="fieldset">
            <label class="glass-label">Password</label>
            <input id="password" type="password" name="password"
                required autocomplete="current-password"
                placeholder="••••••••"
                class="input w-full glass-input @error('password') input-error @enderror" />
            @error('password')
                <p class="label" style="color:rgba(255,200,200,0.9)">{{ $message }}</p>
            @enderror
        </div>
        {{-- Remember + Forgot --}}
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-sm cursor-pointer">
                <input type="checkbox" name="remember" class="checkbox checkbox-sm glass-checkbox" />
                <span class="glass-label">Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   style="color:rgba(255,255,255,0.8);font-size:0.875rem;" class="hover:underline">
                    Lupa password?
                </a>
            @endif
        </div>
        {{-- Submit --}}
        <button type="submit" class="btn btn-block glass-btn">
            Masuk
        </button>
    </form>
</x-guest-layout>
