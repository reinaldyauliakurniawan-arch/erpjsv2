<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;
    protected static function booted()
{
    static::deleting(function ($enrollment) {
        $enrollment->tutors()->detach();
        $enrollment->schedules()->delete();
        $enrollment->installments()->delete();

        $journal = \App\Models\Journal::where('reference', 'PAYMENT-ENROLL-' . $enrollment->id)->first();
        if ($journal) {
            $journal->items()->delete();
            $journal->delete();
        }

        $revRecJournals = \App\Models\Journal::where('reference', 'like', 'REV-REC-%-' . $enrollment->id)->get();
        foreach ($revRecJournals as $journal) {
            $journal->items()->delete();
            $journal->delete();
        }
    });
}
    protected $fillable = [
        'student_id', 'program_id', 'class_session_id', 'enrollment_date', 'expiry_date',
        'payment_method', 'payment_channel', 'total_amount', 'payment_status', 'status', 'remaining_meetings'
    ];

    protected $casts = [
    'total_amount' => 'decimal:2',
    'enrollment_date' => 'date',
    'expiry_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function tutors()
    {
        return $this->belongsToMany(Tutor::class, 'enrollment_tutor')->withPivot('status')->withTimestamps();
    }
}
