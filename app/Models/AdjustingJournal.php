<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AdjustingJournal extends Model
{
    protected $fillable = [
        'period', 'reference', 'description', 'type',
        'status', 'source_id', 'source_type', 'total_amount', 'posted_journal_id',
    ];

    protected $casts = [
        'period' => 'date',
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
        $prefix = 'AJE-'.Carbon::parse($period)->format('Y-m');
        // Portable (jangan pakai SUBSTRING_INDEX yang MySQL-only): ambil semua
        // referensi bulan itu, cari nomor urut terbesar di sisi PHP.
        $lastNumber = (int) static::where('reference', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->pluck('reference')
            ->map(fn ($r) => (int) substr($r, strrpos($r, '-') + 1))
            ->max();

        return $prefix.'-'.str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }
}
