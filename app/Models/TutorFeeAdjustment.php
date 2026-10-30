<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Penyesuaian honor tutor di luar absensi per pertemuan (B2B, tunjangan
 * prestasi, koreksi, saldo awal). Dibayar oleh payroll run yang periodenya
 * (26–25, lihat PayrollPeriod) memuat `date`.
 */
class TutorFeeAdjustment extends Model
{
    use HasFactory;

    public const KINDS = [
        'honor' => 'Honor tambahan / koreksi',
        'tunjangan' => 'Tunjangan prestasi',
        'saldo_awal' => 'Saldo awal utang honor',
    ];

    protected $fillable = ['tutor_id', 'date', 'kind', 'amount', 'description', 'journal_id', 'payroll_run_id', 'paid_at', 'created_by'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Penyesuaian bertanggal di [$from, $to] (Y-m-d, inklusif). Pakai whereDate
     * karena kolom `date` bisa tersimpan dengan jam 00:00:00.
     *
     * @param  array{0: string, 1: string}  $bounds
     */
    public function scopeInPayrollPeriod($query, array $bounds)
    {
        return $query->whereDate('date', '>=', $bounds[0])->whereDate('date', '<=', $bounds[1]);
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
