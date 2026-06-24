<?php

namespace Tests\Feature\Admin;

use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TutorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->admin->role = 'admin';
        $this->admin->save();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.tutors.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_view_tutor_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tutors.index'))
            ->assertOk()
            ->assertViewIs('admin.tutors.index');
    }

    #[Test]
    public function admin_can_view_tutor_detail(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorUser->role = 'tutor';
        $tutorUser->save();
        $tutor = Tutor::factory()->forUser($tutorUser)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.tutors.show', $tutor->id))
            ->assertOk()
            ->assertViewIs('admin.tutors.show');
    }

    #[Test]
    public function admin_can_create_tutor(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tutors.store'), [
                'name' => 'Test Tutor',
                'email' => 'testtutor@example.com',
                'password' => 'password123',
                'persona' => 'S1 TESOL',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'testtutor@example.com',
        ]);
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $student = User::factory()->student()->create();
        $student->role = 'student';
        $student->save();

        $this->actingAs($student)
            ->get(route('admin.tutors.index'))
            ->assertForbidden();
    }
}
