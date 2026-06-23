<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-md">
        @csrf

        <div class="fieldset">
            <label class="glass-label">Nama</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                required autofocus autocomplete="name" placeholder="Nama lengkap"
                class="input w-full glass-input @error('name') input-error @enderror" />
            @error('name')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset">
            <label class="glass-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                required autocomplete="username" placeholder="nama@email.com"
                class="input w-full glass-input @error('email') input-error @enderror" />
            @error('email')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset">
            <label class="glass-label">Password</label>
            <input id="password" type="password" name="password"
                required autocomplete="new-password" placeholder="••••••••"
                class="input w-full glass-input @error('password') input-error @enderror" />
            @error('password')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset">
            <label class="glass-label">Konfirmasi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                required autocomplete="new-password" placeholder="••••••••"
                class="input w-full glass-input @error('password_confirmation') input-error @enderror" />
            @error('password_confirmation')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-block glass-btn">Daftar</button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="guest-link">
                Sudah punya akun? Masuk
            </a>
        </div>
    </form>
</x-guest-layout>
