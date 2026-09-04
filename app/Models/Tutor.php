<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Tutor extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'persona', 'status', 'employment_type', 'monthly_salary', 'salaried_since', 'salaried_until'];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'salaried_since' => 'date',
        'salaried_until' => 'date',
    ];

    /**
     * Apakah tutor ini digaji tetap (bukan dibayar per meeting) pada tanggal
     * tertentu.
     *
     * Sengaja TIDAK bergantung pada `employment_type` (status "sekarang"),
     * melainkan pada periode tetap `[salaried_since, salaried_until]`. Dengan
     * begitu, meeting historis pada saat tutor masih tetap tetap dihitung
     * benar (payable 0, tanpa akru fee) walaupun tutor-nya sekarang sudah
     * balik jadi freelance atau sudah tidak aktif.
     *
     * Meeting sebelum `salaried_since` atau sesudah `salaried_until` tetap
     * diperlakukan freelance.
     */
    public function isSalariedOn(\DateTimeInterface|string $date): bool
    {
        if (! $this->salaried_since) {
            return false;
        }

        $d = Carbon::parse($date)->startOfDay();

        if ($d->lt($this->salaried_since->copy()->startOfDay())) {
            return false;
        }

        if ($this->salaried_until && $d->gt($this->salaried_until->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    /** Tutor tetap yang periodenya masih berjalan hari ini. */
    public function isCurrentlySalaried(): bool
    {
        return $this->isSalariedOn(Carbon::now());
    }

    /**
     * Gaji tetap yang jatuh tempo untuk sebuah bulan, pro-rata per hari
     * kalender untuk bulan pertama/terakhir yang tidak penuh. Bulan penuh
     * mengembalikan `monthly_salary` apa adanya (tanpa drift pembulatan).
     * Mengembalikan "0.00" kalau periode tetap tidak menyentuh bulan itu.
     *
     * @param  \DateTimeInterface|string  $month  tanggal apa pun dalam bulan target
     */
    public function proratedSalaryForMonth(\DateTimeInterface|string $month): string
    {
        if (! $this->salaried_since || ! $this->monthly_salary || (float) $this->monthly_salary <= 0) {
            return '0.00';
        }

        $monthStart = Carbon::parse($month)->startOfMonth();
        $monthEnd = Carbon::parse($month)->endOfMonth()->startOfDay();

        $since = $this->salaried_since->copy()->startOfDay();
        $until = $this->salaried_until?->copy()->startOfDay();

        $effStart = $since->greaterThan($monthStart) ? $since : $monthStart->copy();
        $effEnd = ($until && $until->lessThan($monthEnd)) ? $until : $monthEnd->copy();

        if ($effStart->greaterThan($effEnd)) {
            return '0.00';
        }

        // Hitung dalam satuan hari penuh (kedua ujung sudah di startOfDay).
        $daysCovered = (int) $effStart->diffInDays($effEnd) + 1;
        $daysInMonth = (int) $monthStart->copy()->endOfMonth()->day;

        if ($daysCovered >= $daysInMonth) {
            return number_format((float) $this->monthly_salary, 2, '.', '');
        }

        // Kalikan dulu baru bagi supaya pecahan seperti 10/28 tidak kehilangan
        // presisi (mis. gaji 2.8jt × 10 ÷ 28 tepat 1.000.000).
        return bcdiv(
            bcmul((string) $this->monthly_salary, (string) $daysCovered, 2),
            (string) $daysInMonth,
            2
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rates()
    {
        return $this->hasMany(TutorRate::class);
    }

    public function availability()
    {
        return $this->hasMany(TutorAvailability::class);
    }

    public function enrollments()
    {
        return $this->belongsToMany(Enrollment::class, 'enrollment_tutor')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function classSessions()
    {
        return $this->belongsToMany(ClassSession::class, 'class_session_tutor')
            ->withPivot('status')
            ->withTimestamps();
    }
}
