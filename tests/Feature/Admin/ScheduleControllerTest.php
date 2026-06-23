<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Critical-path feature tests for the admin ScheduleController.
 *
 * Covers the create / update / destroy flow with role protection.
 * Focus: validation, conflict detection, soft invariants.
 */
class ScheduleControllerTest extends TestCase
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
        $this->get(route('admin.schedule.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function non_admin_user_is_forbidden(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->get(route('admin.schedule.index'))
            ->assertForbidden();
    }

    // =========================================================
    //  INDEX
    // =========================================================

    #[Test]
    public function admin_can_view_schedule_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.schedule.index'))
            ->assertOk()
            ->assertViewIs('admin.schedule.index');
    }

    // =========================================================
    //  STORE
    // =========================================================

    #[Test]
    public function admin_can_create_schedule_for_class_session(): void
    {
        $classroom = Classroom::factory()->create();
        $session   = ClassSession::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->post(route('admin.schedule.store'), [
                'class_session_id' => $session->id,
                'classroom_id'     => $classroom->id,
                'day'              => 'Monday',
                'time_block'       => '09:00-10:30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('schedules', [
            'class_session_id' => $session->id,
            'classroom_id'     => $classroom->id,
            'day'              => 'Monday',
            'time_block'       => '09:00-10:30',
        ]);
    }

    #[Test]
    public function store_fails_when_slot_already_occupied_in_same_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $session   = ClassSession::factory()->create(['status' => 'active']);

        // Pre-existing schedule that occupies the slot
        Schedule::factory()->create([
            'classroom_id' => $classroom->id,
            'day'          => 'Tuesday',
            'time_block'   => '10:30-12:00',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.schedule.store'), [
                'class_session_id' => $session->id,
                'classroom_id'     => $classroom->id,
                'day'              => 'Tuesday',
                'time_block'       => '10:30-12:00',
            ])
            ->assertSessionHasErrors(['error']);
    }

    #[Test]
    public function store_fails_when_class_session_already_scheduled_in_that_slot(): void
    {
        $classroom = Classroom::factory()->create();
        $session   = ClassSession::factory()->create(['status' => 'active']);

        // Pre-existing schedule for the SAME class session in this slot (different classroom is fine, but the CS already has it)
        Schedule::factory()->create([
            'class_session_id' => $session->id,
            'classroom_id'     => $classroom->id,
            'day'              => 'Wednesday',
            'time_block'       => '13:00-14:30',
        ]);

        // Try to add the SAME class_session to the SAME slot (different classroom is irrelevant — the CS already has a schedule in this slot)
        $otherClassroom = Classroom::factory()->create();
        $this->actingAs($this->admin)
            ->post(route('admin.schedule.store'), [
                'class_session_id' => $session->id,
                'classroom_id'     => $otherClassroom->id,
                'day'              => 'Wednesday',
                'time_block'       => '13:00-14:30',
            ])
            ->assertSessionHasErrors(['error']);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.schedule.store'), [])
            ->assertSessionHasErrors(['class_session_id', 'classroom_id', 'day', 'time_block']);
    }

    // =========================================================
    //  UPDATE
    // =========================================================

    #[Test]
    public function admin_can_move_schedule_to_different_slot(): void
    {
        $classroom    = Classroom::factory()->create();
        $newClassroom = Classroom::factory()->create();
        $session      = ClassSession::factory()->create(['status' => 'active']);
        $schedule     = Schedule::factory()->create([
            'class_session_id' => $session->id,
            'classroom_id'     => $classroom->id,
            'day'              => 'Monday',
            'time_block'       => '09:00-10:30',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.schedule.update', $schedule->id), [
                'classroom_id' => $newClassroom->id,
                'day'          => 'Thursday',
                'time_block'   => '15:00-16:30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $schedule->refresh();
        $this->assertEquals($newClassroom->id, $schedule->classroom_id);
        $this->assertEquals('Thursday', $schedule->day);
        $this->assertEquals('15:00-16:30', $schedule->time_block);
    }

    // =========================================================
    //  DESTROY
    // =========================================================

    #[Test]
    public function admin_can_delete_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.schedule.destroy', $schedule->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }
}
