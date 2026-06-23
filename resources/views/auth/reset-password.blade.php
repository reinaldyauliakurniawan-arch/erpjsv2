<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-md">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="fieldset">
            <label class="glass-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                required autofocus autocomplete="username" placeholder="nama@email.com"
                class="input w-full glass-input @error('email') input-error @enderror" />
            @error('email')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset">
            <label class="glass-label">Password Baru</label>
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

        <button type="submit" class="btn btn-block glass-btn">Reset Password</button>
    </form>
</x-guest-layout>
