<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory;
    protected $fillable = ['enrollment_id', 'amount', 'due_date', 'paid_at', 'payment_channel'];

    protected $casts = [
    'amount' => 'decimal:2',
    'due_date' => 'date',
    'paid_at' => 'date',
];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
