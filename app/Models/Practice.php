<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Practice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tutor_id', 'title', 'description', 'external_link',
        'estimated_duration', 'deadline', 'status',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function tutor()
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'practice_student', 'practice_id', 'student_id')
                    ->withPivot('completion_status', 'completed_at', 'opened_at', 'reflection')
                    ->withTimestamps();
    }
}
