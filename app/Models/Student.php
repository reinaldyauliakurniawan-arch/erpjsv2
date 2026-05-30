<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollment()
    {
        return $this->hasOne(Enrollment::class)->where('status', 'active')->latestOfMany();
    }

    public function trackerEntries()
    {
        return $this->hasMany(TrackerEntry::class);
    }
}
