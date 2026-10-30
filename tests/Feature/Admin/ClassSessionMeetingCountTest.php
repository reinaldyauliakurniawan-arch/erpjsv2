<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jumlah pertemuan per kelas untuk kelas grup tanpa data siswa (mis. B2B):
 * total_meetings (kontrak kelas) + meetings_before_start (sebelum dicatat di ERP).
 */
class ClassSessionMeetingCountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function admin_can_create_group_class_without_students_with_meeting_count(): void
    {
        $program = Program::create(['name' => 'B2B Class', 'type' => 'group', 'price' => 0, 'total_meetings' => 0, 'min_quota' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.class-sessions.store'), [
                'name' => 'RU PUTRI', 'program_id' => $program->id, 'class_type' => 'group', 'status' => 'active',
                'total_meetings' => 50, 'meetings_before_start' => 12,
            ])
            ->assertRedirect();

        $cs = ClassSession::where('name', 'RU PUTRI')->firstOrFail();
        $this->assertSame(50, $cs->total_meetings);
        $this->assertSame(12, $cs->meetings_before_start);
        $this->assertSame(0, $cs->enrollments()->count());
    }

    #[Test]
    public function meetings_held_counts_history_plus_recorded_attendance(): void
    {
        $cs = ClassSession::factory()->create(['total_meetings' => 50, 'meetings_before_start' => 12]);
        Attendance::factory()->count(3)->sequence(
            ['date' => '2026-10-02'], ['date' => '2026-10-06'], ['date' => '2026-10-09'],
        )->create(['class_session_id' => $cs->id]);

        $cs = ClassSession::withCount('attendances')->find($cs->id);
        $this->assertSame(15, $cs->meetingsHeld());
        $this->assertSame(35, $cs->meetingsRemaining());

        $this->actingAs($this->admin)
            ->get(route('admin.class-sessions.show', $cs->id))
            ->assertOk()
            ->assertSee('Pertemuan 15 dari 50');

        $this->actingAs($this->admin)
            ->get(route('admin.class-sessions.index'))
            ->assertOk()
            ->assertSee('/ 50');
    }

    #[Test]
    public function meeting_count_is_optional_and_can_be_cleared_on_update(): void
    {
        $cs = ClassSession::factory()->create(['total_meetings' => 24, 'meetings_before_start' => 3]);

        $this->actingAs($this->admin)
            ->put(route('admin.class-sessions.update', $cs->id), [
                'name' => $cs->name, 'program_id' => $cs->program_id, 'class_type' => 'group', 'status' => 'active',
                'total_meetings' => '', 'meetings_before_start' => 0,
            ])
            ->assertRedirect();

        $cs->refresh();
        $this->assertNull($cs->total_meetings);
        $this->assertNull($cs->meetingsRemaining());
    }

    #[Test]
    public function meeting_count_must_be_positive(): void
    {
        $cs = ClassSession::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.class-sessions.update', $cs->id), [
                'name' => $cs->name, 'program_id' => $cs->program_id, 'class_type' => 'group', 'status' => 'active',
                'total_meetings' => 0,
            ])
            ->assertSessionHasErrors('total_meetings');
    }
}
