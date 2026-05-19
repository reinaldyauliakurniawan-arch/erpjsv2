<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;
    protected $fillable = ['enrollment_id', 'class_session_id', 'classroom_id', 'day', 'time_block'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function roomBookings()
{
    return $this->hasMany(RoomBooking::class);
}
}
