<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Security: 'role' is intentionally NOT in $fillable to prevent privilege
// escalation via mass assignment. Set role explicitly via $user->role = 'admin'.
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

     public function tutor()
{
    return $this->hasOne(Tutor::class);
}

public function student()
{
    return $this->hasOne(Student::class);
}

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Peran ────────────────────────────────────────────────────────────
    // Kolom `role` tetap disimpan sebagai string. Untuk membandingkan peran,
    // pakai helper di bawah — jangan menulis `$user->role === 'cfo'` lepas.

    /** Peran sebagai enum (null kalau nilainya tidak dikenal). */
    public function roleEnum(): ?Role
    {
        return Role::tryFrom((string) $this->role);
    }

    public function hasRole(Role $role): bool
    {
        return $this->roleEnum() === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isCfo(): bool
    {
        return $this->hasRole(Role::CFO);
    }

    public function isTutor(): bool
    {
        return $this->hasRole(Role::TUTOR);
    }

    public function isStudent(): bool
    {
        return $this->hasRole(Role::STUDENT);
    }

    /**
     * Staf back-office (admin + cfo). Satu-satunya tempat kedua peran ini
     * sengaja digabung — mis. kotak pencarian global di topbar.
     */
    public function isBackOffice(): bool
    {
        return (bool) $this->roleEnum()?->isBackOffice();
    }

    public function practices()
{
    return $this->hasMany(Practice::class, 'tutor_id');
}

public function assignedPractices()
{
    return $this->belongsToMany(Practice::class, 'practice_student', 'student_id', 'practice_id')
                ->withPivot('completion_status', 'completed_at')
                ->withTimestamps();
}
}
