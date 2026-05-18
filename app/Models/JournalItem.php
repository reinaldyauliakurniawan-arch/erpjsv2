<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalItem extends Model
{
    use HasFactory;
    protected $fillable = ['journal_id', 'account_id', 'debit', 'credit', 'program_id'];

    protected $casts = [
    'debit' => 'decimal:2',
    'credit' => 'decimal:2',
];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
