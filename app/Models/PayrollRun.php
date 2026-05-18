<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;
    protected $fillable = ['month', 'status', 'approved_by'];

    protected $casts = [
        'month' => 'date',
    ];

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
