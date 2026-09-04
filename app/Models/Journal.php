<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'description', 'reference', 'type', 'total_amount', 'approved_by', 'enrollment_id'];

    public function items()
    {
        return $this->hasMany(JournalItem::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
