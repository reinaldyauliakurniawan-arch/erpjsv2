<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class AttendanceTutor extends Model
{
    use HasFactory;
    protected $table = 'attendance_tutor';
    protected $fillable = ['attendance_id', 'tutor_id', 'payable_amount', 'pending_rate', 'journal_id', 'paid_at', 'is_replacement', 'replaced_tutor_id', 'is_team_teaching'];
    protected $casts = [
    'payable_amount' => 'decimal:2',
    'pending_rate' => 'boolean',
    'paid_at' => 'datetime',
];
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }
}
