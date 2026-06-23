<x-guest-layout>
    <p class="guest-info-text">
        Masukkan email kamu dan kami akan mengirimkan link untuk reset password.
    </p>

    <x-auth-session-status class="mb-md" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-md">
        @csrf
        <div class="fieldset">
            <label class="glass-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                required autofocus placeholder="nama@email.com"
                class="input w-full glass-input @error('email') input-error @enderror" />
            @error('email')
                <p class="label guest-error-text">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-block glass-btn">Kirim Link Reset Password</button>
        <div class="text-center">
            <a href="{{ route('login') }}" class="guest-link">
                Kembali ke login
            </a>
        </div>
    </form>
</x-guest-layout>
