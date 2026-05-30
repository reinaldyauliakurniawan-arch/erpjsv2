<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rab extends Model
{
    protected $fillable = ['year', 'division', 'account_name', 'account_code', 'activity', 'q1', 'q2', 'q3', 'q4'];
    protected $casts = [
        'q1' => 'integer', 'q2' => 'integer',
        'q3' => 'integer', 'q4' => 'integer',
        'total' => 'integer',
    ];

    public static function divisions(): array
    {
        return ['CEO', 'MARKETING', 'OPERATION', 'PEOPLE & PRODUCT', 'FINANCE'];
    }
}
