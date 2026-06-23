<x-guest-layout>
    <p class="guest-info-text">
        Ini adalah area aman. Konfirmasi password kamu sebelum melanjutkan.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-md">
        @csrf
        <div class="fieldset">
            <label class="glass-label">Password</label>
            <input id="password" type="password" name="password"
                required autocomplete="current-password" placeholder="••••••••"
                class="input w-full glass-input @error('password') input-error @enderror" />
            @error('password')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-block glass-btn">Konfirmasi</button>
    </form>
</x-guest-layout>
