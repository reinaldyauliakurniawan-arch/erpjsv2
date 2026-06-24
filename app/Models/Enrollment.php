<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Enrollment extends Model
{
    use HasFactory;
    protected static function booted()
    {
        // Atomicity fix: previously the cascade deletes (tutors/schedules/installments)
        // ran as 3 separate statements with no transaction. If any failed, the
        // enrollment would NOT be deleted (the parent delete aborts), but the
        // already-detached/deleted children were gone — leaving orphans.
        // Wrap the cascade in a transaction so all-or-nothing semantics hold.
        // Callers of $enrollment->delete() should also wrap in DB::transaction
        // for full atomicity across the cascade + their own writes.
        static::deleting(function ($enrollment) {
            DB::transaction(function () use ($enrollment) {
                $enrollment->tutors()->detach();
                $enrollment->schedules()->delete();
                $enrollment->installments()->delete();
                // Jurnal tidak dihapus — reversal dilakukan via flow refund yang proper
            });
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
