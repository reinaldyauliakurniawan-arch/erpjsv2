<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TutorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'persona' => fake()->word(),
            'status'  => 'active',
        ];
    }

    /**
     * Create tutor with a freshly-created user (default behavior, kept for backward compat).
     * Optionally accepts attributes to override the user factory.
     *
     * Usage:
     *   Tutor::factory()->withUser()->create()
     *   Tutor::factory()->withUser(['name' => 'Budi'])->create()
     */
    public function withUser(array $userAttrs = []): static
    {
        return $this->state(fn () => [
            'user_id' => \App\Models\User::factory()->create($userAttrs)->id,
        ]);
    }

    /**
     * Explicitly attach an existing user (instead of creating a new one).
     * Usage: Tutor::factory()->forUser($user)->create()
     */
    public function forUser(\App\Models\User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /**
     * Create tutor with an attached TutorRate for the given program.
     * Usage: Tutor::factory()->withRate($programId, 150000)->create()
     */
    public function withRate(?int $programId = null, int $rate = 150_000): static
    {
        return $this->has(
            \App\Models\TutorRate::factory()->state(fn () => array_filter([
                'program_id' => $programId,
                'rate'       => $rate,
            ])),
            'rates'
        );
    }
}
