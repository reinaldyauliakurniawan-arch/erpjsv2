<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'notes', 'education_level'];

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
        return $this->hasOne(Enrollment::class)->ofMany([
            'id' => 'max',
        ], function ($query) {
            $query->where('status', 'active');
        });
    }

    public function trackerEntries()
    {
        return $this->hasMany(TrackerEntry::class);
    }
}
