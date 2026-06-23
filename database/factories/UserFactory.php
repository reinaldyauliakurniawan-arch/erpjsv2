<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => 'admin',
            'phone'             => fake()->optional()->phoneNumber(),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign a specific role to the user.
     * Usage: User::factory()->role('admin')->create()
     */
    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    /**
     * Convenience: admin role.
     */
    public function admin(): static
    {
        return $this->role('admin');
    }

    /**
     * Convenience: cfo role.
     */
    public function cfo(): static
    {
        return $this->role('cfo');
    }

    /**
     * Convenience: tutor role.
     */
    public function tutor(): static
    {
        return $this->role('tutor');
    }

    /**
     * Convenience: student role.
     */
    public function student(): static
    {
        return $this->role('student');
    }
}
