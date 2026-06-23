<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'month'  => fake()->date('Y-m-d'),
            'status' => 'pending',
            'approved_by' => null,
            'reversed_by' => null,
        ];
    }

    /**
     * Mark the payroll run as approved by the given user.
     */
    public function approved(?int $userId = null): static
    {
        return $this->state(fn () => [
            'status'      => 'approved',
            'approved_by' => $userId,
        ]);
    }

    /**
     * Mark the payroll run as reversed by the given user.
     */
    public function reversed(?int $userId = null): static
    {
        return $this->state(fn () => [
            'status'      => 'reversed',
            'reversed_by' => $userId,
        ]);
    }
}
