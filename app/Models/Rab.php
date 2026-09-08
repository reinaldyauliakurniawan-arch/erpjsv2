<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rab extends Model
{
    use HasFactory;

    protected $fillable = ['year', 'division', 'account_name', 'account_code', 'activity', 'rab_prev', 'annual_budget', 'q1', 'q2', 'q3', 'q4'];

    protected $casts = [
        'rab_prev' => 'integer',
        'annual_budget' => 'integer',
        'q1' => 'integer', 'q2' => 'integer',
        'q3' => 'integer', 'q4' => 'integer',
        'total' => 'integer',
    ];

    /** Anggaran tahunan efektif (fallback ke total kuartal kalau belum di-set). */
    public function annualBudget(): int
    {
        return (int) ($this->annual_budget ?: $this->total);
    }

    public static function divisions(): array
    {
        return ['CEO', 'MARKETING', 'OPERATION', 'PEOPLE & PRODUCT', 'FINANCE'];
    }
}
