<?php

namespace App\Models;

use App\Enums\ClassroomKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'capacity', 'kind', 'is_at_just_speak'];

    protected $casts = [
        'is_at_just_speak' => 'boolean',
        'kind' => ClassroomKind::class,
    ];

    /**
     * `is_at_just_speak` dipertahankan (dipakai import/export CSV) dan selalu
     * diturunkan dari `kind`: hanya ruang fisik yang "di Just Speak".
     */
    protected static function booted(): void
    {
        static::saving(function (Classroom $classroom) {
            $kind = $classroom->kind instanceof ClassroomKind
                ? $classroom->kind
                : ClassroomKind::tryFrom((string) $classroom->kind);
            $classroom->is_at_just_speak = $kind === ClassroomKind::PHYSICAL;
        });
    }

    public function isPhysical(): bool
    {
        return $this->kind === ClassroomKind::PHYSICAL;
    }

    /** Ruang ini dihitung untuk okupansi & cek kapasitas/bentrok. */
    public function countsForOccupancy(): bool
    {
        return (bool) $this->kind?->countsForOccupancy();
    }

    /** Scope: hanya ruang fisik Just Speak. */
    public function scopePhysical(Builder $query): Builder
    {
        return $query->where('kind', ClassroomKind::PHYSICAL->value);
    }
}
