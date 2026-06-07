<x-guest-layout>
    <p style="color:rgba(255,255,255,0.8);font-size:0.875rem;margin-bottom:1.25rem;line-height:1.6;">
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
                <p style="color:rgba(255,200,200,0.9);font-size:0.8rem;">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-block glass-btn">Kirim Link Reset Password</button>
        <div class="text-center">
            <a href="{{ route('login') }}" style="color:rgba(255,255,255,0.7);font-size:0.875rem;" class="hover:underline">
                Kembali ke login
            </a>
        </div>
    </form>
</x-guest-layout>
