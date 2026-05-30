<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $fillable = [
        'name', 'category', 'acquired_at',
        'cost', 'salvage_value', 'useful_life',
        'depreciation_method', 'notes',
        'expense_account_id', 'accumulated_account_id', 'is_active',
    ];

    protected $casts = [
        'acquired_at'   => 'date',
        'cost'          => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function accumulatedAccount()
    {
        return $this->belongsTo(Account::class, 'accumulated_account_id');
    }

    public function getMonthsElapsedAttribute(): int
    {
        return (int) min(
            $this->acquired_at->diffInMonths(now()),
            $this->useful_life
        );
    }

    public function getMonthlyDepreciationAttribute(): float
    {
        if ($this->useful_life <= 0) return 0;
        return ((float)$this->cost - (float)$this->salvage_value) / $this->useful_life;
    }

    public function getAccumulatedDepreciationAttribute(): float
    {
        return $this->monthly_depreciation * $this->months_elapsed;
    }

    public function getBookValueAttribute(): float
    {
        return max((float)$this->cost - $this->accumulated_depreciation, (float)$this->salvage_value);
    }

    public function getStatusAttribute(): string
    {
        return $this->months_elapsed >= $this->useful_life ? 'fully_depreciated' : 'active';
    }
}
