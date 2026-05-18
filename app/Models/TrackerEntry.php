<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackerEntry extends Model
{
    protected $fillable = ['student_id', 'tracker_column_id', 'is_done'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function column()
    {
        return $this->belongsTo(TrackerColumn::class);
    }
}
