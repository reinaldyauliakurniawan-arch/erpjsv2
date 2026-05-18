<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBooking extends Model
{
    protected $fillable = [
        'classroom_id', 'date', 'time_block', 'type',
        'enrollment_id', 'tutor_id', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }
}
