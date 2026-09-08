<?php

namespace Database\Factories;

use App\Enums\ClassroomKind;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->bothify('Room ##'),
            'capacity' => fake()->numberBetween(5, 30),
            'kind' => ClassroomKind::PHYSICAL->value,
        ];
    }

    /** Ruang online — tidak dihitung untuk okupansi. */
    public function online(): static
    {
        return $this->state(fn () => ['name' => 'Online', 'kind' => ClassroomKind::ONLINE->value]);
    }

    /** Ruang di luar Just Speak (B2B) — tidak dihitung untuk okupansi. */
    public function offsite(): static
    {
        return $this->state(fn () => ['kind' => ClassroomKind::OFFSITE->value]);
    }
}
