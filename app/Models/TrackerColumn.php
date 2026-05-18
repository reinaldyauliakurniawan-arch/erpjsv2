<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackerColumn extends Model
{
    protected $fillable = ['name', 'order'];

    public function entries()
    {
        return $this->hasMany(TrackerEntry::class);
    }
}
