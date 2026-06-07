<x-guest-layout>
    <p style="color:rgba(255,255,255,0.8);font-size:0.875rem;margin-bottom:1.25rem;line-height:1.6;">
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
                <p style="color:rgba(255,200,200,0.9);font-size:0.8rem;">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-block glass-btn">Konfirmasi</button>
    </form>
</x-guest-layout>
