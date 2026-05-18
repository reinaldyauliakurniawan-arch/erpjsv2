<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorRate extends Model
{
    use HasFactory;
    protected $fillable = ['tutor_id', 'program_id', 'rate'];

    protected $casts = [
    'rate' => 'decimal:2',
];

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
