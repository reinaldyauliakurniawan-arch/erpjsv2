<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'persona', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rates()
    {
        return $this->hasMany(TutorRate::class);
    }

    public function availability()
    {
        return $this->hasMany(TutorAvailability::class);
    }

    public function enrollments()
    {
        return $this->belongsToMany(Enrollment::class, 'enrollment_tutor')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function classSessions()
    {
        return $this->belongsToMany(ClassSession::class, 'class_session_tutor')
            ->withPivot('status')
            ->withTimestamps();
    }
}
