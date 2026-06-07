<x-guest-layout>
    <p style="color:rgba(255,255,255,0.8);font-size:0.875rem;margin-bottom:1.25rem;line-height:1.6;">
        Terima kasih sudah mendaftar! Sebelum mulai, silakan verifikasi email kamu dengan mengklik link yang sudah kami kirimkan.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div style="background:rgba(255,255,255,0.15);border-radius:12px;padding:0.75rem 1rem;margin-bottom:1rem;">
            <p style="color:#fff;font-size:0.875rem;">Link verifikasi baru sudah dikirim ke email kamu.</p>
        </div>
    @endif

    <div class="space-y-sm">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-block glass-btn">Kirim Ulang Email Verifikasi</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-block" style="background:transparent;border:1px solid rgba(255,255,255,0.3);color:rgba(255,255,255,0.7);border-radius:12px;">
                Keluar
            </button>
        </form>
    </div>
</x-guest-layout>
