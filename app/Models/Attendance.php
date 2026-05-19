<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Attendance;

class Attendance extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'attendance';
    protected $fillable = ['class_session_id', 'date', 'time_block', 'classroom_id', 'marked_by', 'status', 'notes'];

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function tutors()
    {
        return $this->belongsToMany(Tutor::class, 'attendance_tutor')->withPivot('payable_amount', 'pending_rate', 'journal_id')->withTimestamps();
    }

    public function students()
    {
        return $this->belongsToMany(Enrollment::class, 'attendance_student')->withPivot('is_present', 'notes')->withTimestamps();
    }
}
