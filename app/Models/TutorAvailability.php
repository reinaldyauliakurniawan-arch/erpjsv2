<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorAvailability extends Model
{
    use HasFactory;
    protected $table = 'tutor_availability';
    protected $fillable = ['tutor_id', 'day', 'time_block', 'status'];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }
}
