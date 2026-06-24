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
            'phone'             => fake()->optional()->phoneNumber(),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Set the user's role. Called after create because 'role' is not
     * mass-assignable (removed from $fillable for security).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            // Default role is set via the role() state method below.
        })->afterCreating(function (User $user) {
            if ($user->role === null) {
                $user->role = 'admin';
                $user->save();
            }
        });
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
     *
     * Note: 'role' is not mass-assignable (removed from $fillable for
     * security). We stash the desired role in a class property and the
     * afterCreating hook in configure() applies it via explicit assignment.
     */
    public function role(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            $user->role = $role;
            $user->save();
        });
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
