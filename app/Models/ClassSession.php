<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'program_id', 'class_type', 'status'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'class_session_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function tutors()
    {
        return $this->belongsToMany(Tutor::class, 'class_session_tutor')
            ->withPivot('status')
            ->withTimestamps();
    }
}
