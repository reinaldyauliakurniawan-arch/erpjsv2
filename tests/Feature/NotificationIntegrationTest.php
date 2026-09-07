<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use App\Services\Notifier;
use App\Services\TutorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tutor_is_notified_when_assigned_to_a_class()
    {
        $program = Program::factory()->create(['min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active']);
        $tutor = Tutor::factory()->create();

        app(TutorAssignmentService::class)->assignToClassSession($cs, $tutor->id);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertEquals(1, $tutor->user->unreadNotifications()->count());
        $this->assertEquals('tutor_assigned', $tutor->user->notifications()->first()->data['type']);
    }

    #[Test]
    public function class_tutors_are_notified_of_a_new_student()
    {
        $program = Program::factory()->create();
        $cs = ClassSession::factory()->create(['program_id' => $program->id]);
        $tutor = Tutor::factory()->create();
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);

        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'student_id' => $student->id,
        ]);

        app(Notifier::class)->newStudentInClass($enrollment->load('classSession', 'student.user'));

        $this->assertEquals(1, $tutor->user->unreadNotifications()->count());
        $this->assertEquals('new_student', $tutor->user->notifications()->first()->data['type']);
    }

    #[Test]
    public function bell_endpoint_returns_unread_count_and_items()
    {
        $user = User::factory()->create(['role' => 'admin']);
        app(Notifier::class)->toUser($user, 'new_student', 'Halo', 'body');

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJson(['unread' => 1])
            ->assertJsonPath('items.0.title', 'Halo');
    }

    #[Test]
    public function marking_all_read_clears_the_badge()
    {
        $user = User::factory()->create(['role' => 'tutor']);
        app(Notifier::class)->toUser($user, 'x', 'a');
        app(Notifier::class)->toUser($user, 'x', 'b');

        $this->actingAs($user)->postJson(route('notifications.read-all'))->assertOk();

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }
}
