<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Tanggal mulai pembukuan (setting `ledger_start_date`, format Y-m-d).
 *
 * Kalau diisi, buku besar dianggap DIMULAI pada tanggal ini: seluruh riwayat
 * sebelumnya (uang masuk & pertemuan yang sudah terpakai) tidak dicatat per
 * transaksi, melainkan diringkas jadi satu jurnal saldo awal per enrollment
 * yang bertanggal tepat di tanggal mulai. Tidak ada jurnal enrollment yang
 * ditulis ulang dengan tanggal sebelum tanggal ini.
 *
 * Kosong (NULL) = tidak ada batas; perilaku lama (semua riwayat dicatat
 * sesuai tanggal aslinya).
 */
class LedgerStart
{
    public const SETTING_KEY = 'ledger_start_date';

    public static function date(): ?string
    {
        $value = Setting::get(self::SETTING_KEY);

        return $value ? substr((string) $value, 0, 10) : null;
    }

    public static function set(?string $date): void
    {
        Setting::set(self::SETTING_KEY, $date);
    }
}
