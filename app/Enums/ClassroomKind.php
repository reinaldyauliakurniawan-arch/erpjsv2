<?php

namespace App\Enums;

/**
 * Jenis "ruangan" kelas.
 *
 *  - physical : ruang fisik di kantor Just Speak (Harvard, Stanford, dst).
 *               Punya kapasitas, dihitung untuk okupansi ruangan, tidak boleh
 *               dobel-booking di slot yang sama.
 *  - online   : kelas daring. Bisa dari mana saja, tidak ada batas ruang,
 *               TIDAK dihitung untuk okupansi. Tetap tampil di jadwal supaya
 *               admin tahu kelas online apa saja yang berjalan.
 *  - offsite  : kelas di luar Just Speak (mis. program B2B, tutor dikirim ke
 *               kantor klien). Lokasinya bukan tanggung jawab okupansi Just
 *               Speak, jadi TIDAK dihitung — tapi tetap tercatat.
 *
 * Hanya `physical` yang dihitung untuk okupansi dan dibatasi kapasitas.
 */
enum ClassroomKind: string
{
    case PHYSICAL = 'physical';
    case ONLINE = 'online';
    case OFFSITE = 'offsite';

    public function label(): string
    {
        return match ($this) {
            self::PHYSICAL => 'Ruang Fisik',
            self::ONLINE => 'Online',
            self::OFFSITE => 'Luar Just Speak',
        };
    }

    /** Dihitung untuk okupansi ruangan & cek "ruang penuh" / bentrok. */
    public function countsForOccupancy(): bool
    {
        return $this === self::PHYSICAL;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
