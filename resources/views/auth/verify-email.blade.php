<x-guest-layout>
    <p class="guest-info-text">
        Terima kasih sudah mendaftar! Sebelum mulai, silakan verifikasi email kamu dengan mengklik link yang sudah kami kirimkan.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="guest-notice">
            Link verifikasi baru sudah dikirim ke email kamu.
        </div>
    @endif

    <div class="space-y-sm">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-block glass-btn">Kirim Ulang Email Verifikasi</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-block guest-ghost-btn">
                Keluar
            </button>
        </form>
    </div>
</x-guest-layout>
