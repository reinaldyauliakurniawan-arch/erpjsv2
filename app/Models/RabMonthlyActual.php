<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Realisasi RAB per bulan per akun beban (dicatat manual oleh CFO).
 * Lihat migrasi create_rab_monthly_actuals_table.
 */
class RabMonthlyActual extends Model
{
    protected $table = 'rab_monthly_actuals';

    protected $fillable = ['year', 'account_code', 'month', 'amount'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'amount' => 'integer',
    ];
}
