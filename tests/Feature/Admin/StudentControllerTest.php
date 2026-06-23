<?php

namespace Tests\Feature\Admin;

use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Critical-path feature tests for the admin StudentController.
 *
 * Covers:
 *   - Index / data endpoint (paginated JSON response)
 *   - Show / edit / update
 *   - Soft invariants (cannot delete student with active enrollment or journal history)
 */
class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // =========================================================
    //  AUTHORIZATION
    // =========================================================

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.students.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function non_admin_user_is_forbidden(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->get(route('admin.students.index'))
            ->assertForbidden();
    }

    // =========================================================
    //  INDEX & DATA
    // =========================================================

    #[Test]
    public function admin_can_view_student_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertViewIs('admin.students.index');
    }

    #[Test]
    public function data_returns_paginated_students_with_summary(): void
    {
        Student::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.data'))
            ->assertOk()
            ->assertJsonStructure(['data', 'summary', 'last_page']);

        $this->assertCount(3, $response->json('data'));
        $this->assertArrayHasKey('total', $response->json('summary'));
        $this->assertArrayHasKey('active', $response->json('summary'));
    }

    #[Test]
    public function data_filter_inactive_returns_only_students_without_active_enrollment(): void
    {
        // Student with active enrollment
        $activeStudent = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $activeStudent->id,
            'status'     => 'active',
        ]);

        // Student without any active enrollment
        Student::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.data', ['filter' => 'inactive']))
            ->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($activeStudent->id, $returnedIds);
    }

    #[Test]
    public function data_filter_overdue_returns_only_students_with_overdue_installments(): void
    {
        $overdueStudent = Student::factory()->create();
        $enrollment     = Enrollment::factory()->create([
            'student_id' => $overdueStudent->id,
            'status'     => 'active',
        ]);
        Installment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'paid_at'       => null,
            'due_date'      => now()->subDays(7)->toDateString(),
        ]);

        $okStudent = Student::factory()->create();
        $okEnrollment = Enrollment::factory()->create([
            'student_id' => $okStudent->id,
            'status'     => 'active',
        ]);
        Installment::factory()->create([
            'enrollment_id' => $okEnrollment->id,
            'paid_at'       => now(),
            'due_date'      => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.data', ['filter' => 'overdue']))
            ->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id');
        $this->assertContains($overdueStudent->id, $returnedIds);
        $this->assertNotContains($okStudent->id, $returnedIds);
    }

    // =========================================================
    //  SHOW
    // =========================================================

    #[Test]
    public function admin_can_view_student_detail(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertViewIs('admin.students.show');
    }

    // =========================================================
    //  UPDATE
    // =========================================================

    #[Test]
    public function admin_can_update_student_profile(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.students.update', $student), [
                'name'            => 'Updated Name',
                'email'           => 'updated@example.com',
                'notes'           => 'Updated notes',
                'education_level' => 'SMA',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id'    => $student->user_id,
            'name'  => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
        $this->assertDatabaseHas('students', [
            'id'              => $student->id,
            'notes'           => 'Updated notes',
            'education_level' => 'SMA',
        ]);
    }

    #[Test]
    public function admin_can_reset_student_password(): void
    {
        $student = Student::factory()->create();
        $oldHash = $student->user->password;

        $this->actingAs($this->admin)
            ->put(route('admin.students.update', $student), [
                'password' => 'newSecret123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->user->refresh();
        $this->assertNotEquals($oldHash, $student->user->password);
    }

    #[Test]
    public function update_validates_email_uniqueness_excluding_self(): void
    {
        $otherStudent = Student::factory()->create();
        $student      = Student::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.students.update', $student), [
                'name'  => 'X',
                'email' => $otherStudent->user->email, // already taken
            ])
            ->assertSessionHasErrors(['email']);
    }

    // =========================================================
    //  DESTROY
    // =========================================================

    #[Test]
    public function admin_can_delete_student_without_enrollments(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    #[Test]
    public function cannot_delete_student_with_active_enrollment(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'status'     => 'active',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    #[Test]
    public function cannot_delete_student_with_journal_history(): void
    {
        $student    = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'status'     => 'expired', // not active, but has journal
        ]);

        // Simulate a payment journal tied to this enrollment
        \App\Models\Journal::factory()->create([
            'reference' => 'PAYMENT-ENROLL-' . $enrollment->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }
}
