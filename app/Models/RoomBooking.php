<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBooking extends Model
{
    protected $fillable = [
        'classroom_id', 'schedule_id', 'date', 'time_block', 'type',
        'enrollment_id', 'tutor_id', 'notes',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

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
