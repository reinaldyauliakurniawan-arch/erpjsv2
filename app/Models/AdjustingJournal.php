<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustingJournal extends Model
{
    protected $fillable = [
        'period', 'reference', 'description', 'type',
        'status', 'source_id', 'source_type', 'total_amount', 'posted_journal_id',
    ];

    protected $casts = [
        'period'       => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(AdjustingJournalItem::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function postedJournal()
    {
        return $this->belongsTo(Journal::class, 'posted_journal_id');
    }

    // Generate referensi otomatis: AJE-2024-01-001
    public static function generateReference(string $period): string
    {
        $prefix = 'AJE-' . \Carbon\Carbon::parse($period)->format('Y-m');
        $last = static::where('reference', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(reference, "-", -1) AS UNSIGNED) DESC')
            ->value('reference');
        $lastNumber = $last ? (int) substr($last, strrpos($last, '-') + 1) : 0;
        return $prefix . '-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }
}
