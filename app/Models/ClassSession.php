<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'program_id', 'class_type', 'status', 'total_meetings', 'meetings_before_start'];

    protected $casts = [
        'total_meetings' => 'integer',
        'meetings_before_start' => 'integer',
    ];

    /**
     * Pertemuan kelas yang sudah berjalan: sebelum dicatat di ERP + absensi di
     * ERP. Pakai attendances_count kalau sudah di-load lewat withCount().
     */
    public function meetingsHeld(): int
    {
        $recorded = $this->attendances_count ?? $this->attendances()->count();

        return (int) $this->meetings_before_start + (int) $recorded;
    }

    /** Sisa pertemuan kontrak kelas; null kalau jumlah pertemuan kelas tidak diisi. */
    public function meetingsRemaining(): ?int
    {
        return $this->total_meetings === null ? null : max(0, $this->total_meetings - $this->meetingsHeld());
    }

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
