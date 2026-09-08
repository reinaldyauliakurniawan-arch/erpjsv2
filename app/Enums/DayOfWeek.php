<?php

namespace App\Enums;

use Carbon\Carbon;

/**
 * Hari dalam seminggu — bentuk baku yang dipakai di kolom `day`
 * (`schedules`, `tutor_availability`): Bahasa Indonesia, kapital di awal.
 *
 * Untuk menormalkan input bebas ("monday", "Senin", "SELASA") pakai
 * App\Support\ScheduleFormat::day().
 */
enum DayOfWeek: string
{
    case MONDAY = 'Senin';
    case TUESDAY = 'Selasa';
    case WEDNESDAY = 'Rabu';
    case THURSDAY = 'Kamis';
    case FRIDAY = 'Jumat';
    case SATURDAY = 'Sabtu';
    case SUNDAY = 'Minggu';

    /** Nama hari (baku, Bahasa Indonesia) dari sebuah tanggal. */
    public static function fromDate(Carbon|string $date): self
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return self::cases()[$carbon->dayOfWeekIso - 1];
    }
}
