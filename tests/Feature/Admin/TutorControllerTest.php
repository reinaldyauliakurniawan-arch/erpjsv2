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
    public function admin_can_set_tutor_as_permanent_with_salary(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = Tutor::factory()->forUser($tutorUser)->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.tutors.update', $tutor->id), [
                'name' => $tutorUser->name,
                'email' => $tutorUser->email,
                'persona' => $tutor->persona,
                'status' => 'active',
                'employment_type' => 'permanent',
                'monthly_salary' => 3_500_000,
                'salaried_since' => '2026-09-01',
            ])
            ->assertRedirect();

        $tutor->refresh();
        $this->assertSame('permanent', $tutor->employment_type);
        $this->assertEquals(3_500_000, $tutor->monthly_salary);
        $this->assertSame('2026-09-01', $tutor->salaried_since->toDateString());
    }

    #[Test]
    public function permanent_tutor_requires_salary_and_start_date(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = Tutor::factory()->forUser($tutorUser)->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.tutors.update', $tutor->id), [
                'name' => $tutorUser->name,
                'email' => $tutorUser->email,
                'persona' => $tutor->persona,
                'status' => 'active',
                'employment_type' => 'permanent',
            ])
            ->assertSessionHasErrors(['monthly_salary', 'salaried_since']);
    }

    #[Test]
    public function ending_permanent_status_requires_an_end_date_and_keeps_history(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = Tutor::factory()->forUser($tutorUser)->create([
            'employment_type' => 'permanent',
            'monthly_salary' => 3_500_000,
            'salaried_since' => '2026-09-01',
        ]);

        $base = [
            'name' => $tutorUser->name,
            'email' => $tutorUser->email,
            'persona' => $tutor->persona,
            'status' => 'active',
        ];

        // Tanpa tanggal akhir → ditolak.
        $this->actingAs($this->admin)
            ->patch(route('admin.tutors.update', $tutor->id), $base + ['employment_type' => 'freelance'])
            ->assertSessionHasErrors('salaried_until');

        // Dengan tanggal akhir → sukses, riwayat gaji/mulai dipertahankan.
        $this->actingAs($this->admin)
            ->patch(route('admin.tutors.update', $tutor->id), $base + [
                'employment_type' => 'freelance',
                'salaried_until' => '2026-11-30',
            ])
            ->assertRedirect();

        $tutor->refresh();
        $this->assertSame('freelance', $tutor->employment_type);
        $this->assertEquals(3_500_000, $tutor->monthly_salary);
        $this->assertSame('2026-09-01', $tutor->salaried_since->toDateString());
        $this->assertSame('2026-11-30', $tutor->salaried_until->toDateString());
        $this->assertTrue($tutor->isSalariedOn('2026-10-15'));
        $this->assertFalse($tutor->isSalariedOn('2026-12-01'));
    }

    #[Test]
    public function freelance_tutor_that_was_never_permanent_stays_clean(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutor = Tutor::factory()->forUser($tutorUser)->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.tutors.update', $tutor->id), [
                'name' => $tutorUser->name,
                'email' => $tutorUser->email,
                'persona' => $tutor->persona,
                'status' => 'active',
                'employment_type' => 'freelance',
            ])
            ->assertRedirect();

        $tutor->refresh();
        $this->assertSame('freelance', $tutor->employment_type);
        $this->assertNull($tutor->monthly_salary);
        $this->assertNull($tutor->salaried_since);
        $this->assertNull($tutor->salaried_until);
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
