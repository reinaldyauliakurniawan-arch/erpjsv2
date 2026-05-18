<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasFactory;
    protected $fillable = ['date', 'description', 'reference', 'total_amount', 'approved_by'];

    public function items()
    {
        return $this->hasMany(JournalItem::class);
    }
}
