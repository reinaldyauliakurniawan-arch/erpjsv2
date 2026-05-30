<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustingJournalItem extends Model
{
    protected $fillable = ['adjusting_journal_id', 'account_id', 'debit', 'credit'];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
