<?php

namespace App\Support;

/**
 * Normalisasi format hari & jam untuk jadwal.
 *
 * Kolom `day` dan `time_block` di `schedules`, `tutor_availability`, dan tabel
 * lain adalah string bebas yang datang dari banyak sumber (form admin, import
 * spreadsheet, dropdown enum). Historisnya isinya campur aduk:
 *   - hari  : "Senin" / "senin" / "Monday"
 *   - jam   : "09:00-10:30" / "09.00 - 10.30" / "09.00-10.30"
 *
 * Akibatnya pencocokan antar tabel gagal (mis. okupansi tutor selalu 0% karena
 * slot `schedules` tak pernah cocok dengan slot `tutor_availability`).
 *
 * Bentuk baku:
 *   - hari  : Bahasa Indonesia, huruf kapital di awal  -> "Senin".."Minggu"
 *   - jam   : titik dua sebagai pemisah, tanpa spasi    -> "09:00-10:30"
 *             (bisa langsung dibaca sebagai jam oleh Carbon)
 *
 * SEMUA penulisan ke kolom day/time_block harus lewat helper ini.
 */
class ScheduleFormat
{
    /** Urutan hari baku (Senin dulu). */
    public const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    /** Blok jam baku. */
    public const TIME_BLOCKS = ['09:00-10:30', '10:30-12:00', '13:00-14:30', '14:30-16:00', '16:00-17:30', '18:30-20:00'];

    /** Peta segala varian nama hari -> bentuk baku. */
    private const DAY_MAP = [
        'senin' => 'Senin', 'monday' => 'Senin', 'mon' => 'Senin',
        'selasa' => 'Selasa', 'tuesday' => 'Selasa', 'tue' => 'Selasa',
        'rabu' => 'Rabu', 'wednesday' => 'Rabu', 'wed' => 'Rabu',
        'kamis' => 'Kamis', 'thursday' => 'Kamis', 'thu' => 'Kamis',
        'jumat' => 'Jumat', "jum'at" => 'Jumat', 'jumaat' => 'Jumat', 'friday' => 'Jumat', 'fri' => 'Jumat',
        'sabtu' => 'Sabtu', 'saturday' => 'Sabtu', 'sat' => 'Sabtu',
        'minggu' => 'Minggu', 'ahad' => 'Minggu', 'sunday' => 'Minggu', 'sun' => 'Minggu',
    ];

    /**
     * Kembalikan nama hari baku (Bahasa Indonesia, kapital di awal).
     * Nilai yang tidak dikenal dikembalikan apa adanya setelah di-trim +
     * di-ucfirst supaya tidak menghilangkan data.
     */
    public static function day(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = strtolower(trim($value));
        if ($key === '') {
            return $value;
        }

        return self::DAY_MAP[$key] ?? ucfirst($key);
    }

    /**
     * Kembalikan blok jam baku: titik dua sebagai pemisah jam:menit, tanpa
     * spasi. "09.00 - 10.30" -> "09:00-10:30". Nilai non-jam ("Custom")
     * dikembalikan setelah trim.
     */
    public static function timeBlock(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim($value);
        if ($v === '' || strcasecmp($v, 'Custom') === 0) {
            return $v;
        }

        $v = preg_replace('/\s+/', '', $v);          // buang semua spasi
        $v = preg_replace('/[–—]/u', '-', $v);       // en/em dash -> hyphen
        $v = preg_replace('/(?<=\d)\.(?=\d)/', ':', $v); // 09.00 -> 09:00

        return $v;
    }

    /** Slug hari+jam untuk membandingkan dua slot. */
    public static function slotKey(?string $day, ?string $timeBlock): string
    {
        return self::day($day).'|'.self::timeBlock($timeBlock);
    }
}
