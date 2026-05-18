<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================
    //  LOGIN
    // =========================================================

    #[Test]
    public function user_can_view_login_form()
    {
        $this->get(route('login'))->assertOk();
    }

    #[Test]
    public function admin_can_login_and_redirected_to_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    #[Test]
    public function tutor_can_login_and_redirected_to_tutor_dashboard()
    {
        $user = User::factory()->create(['role' => 'tutor', 'password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('tutor.dashboard'));
    }

    #[Test]
    public function student_can_login_and_redirected_to_student_dashboard()
    {
        $user = User::factory()->create(['role' => 'student', 'password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('student.dashboard'));
    }

    #[Test]
    public function login_fails_with_wrong_password()
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function login_fails_with_nonexistent_email()
    {
        $this->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function login_is_rate_limited_after_too_many_attempts()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email'); // Too Many Attempts error
    }

    // =========================================================
    //  LOGOUT
    // =========================================================

    #[Test]
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    // =========================================================
    //  MIDDLEWARE / ROLE GUARD
    // =========================================================

    #[Test]
    public function guest_is_redirected_from_admin_dashboard()
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function guest_is_redirected_from_tutor_dashboard()
    {
        $this->get(route('tutor.dashboard'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function guest_is_redirected_from_student_dashboard()
    {
        $this->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function student_cannot_access_admin_dashboard()
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function tutor_cannot_access_admin_dashboard()
    {
        $tutor = User::factory()->create(['role' => 'tutor']);

        $this->actingAs($tutor)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
