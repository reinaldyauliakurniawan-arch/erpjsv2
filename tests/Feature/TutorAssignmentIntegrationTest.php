<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Tutor;
use App\Services\TutorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integrasi penugasan tutor: kedua daftar (class_session_tutor & enrollment_tutor)
 * HARUS selalu sinkron, apa pun jalur masuknya.
 */
class TutorAssignmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeClassWithStudents(int $students = 3): array
    {
        $program = Program::factory()->create(['min_quota' => 2, 'total_meetings' => 10]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $enrollments = Enrollment::factory()->count($students)->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active',
        ]);

        return [$cs, $enrollments];
    }

    #[Test]
    public function assigning_tutor_to_class_session_propagates_to_every_enrollment()
    {
        [$cs, $enrollments] = $this->makeClassWithStudents(3);
        $tutor = Tutor::factory()->create();

        app(TutorAssignmentService::class)->assignToClassSession($cs, $tutor->id);

        $this->assertTrue($cs->tutors()->where('tutor_id', $tutor->id)->exists());
        foreach ($enrollments as $e) {
            $this->assertDatabaseHas('enrollment_tutor', [
                'enrollment_id' => $e->id, 'tutor_id' => $tutor->id, 'status' => 'pending',
            ]);
        }
    }

    #[Test]
    public function assigning_tutor_to_one_enrollment_registers_them_on_the_class()
    {
        [$cs, $enrollments] = $this->makeClassWithStudents(2);
        $tutor = Tutor::factory()->create();

        app(TutorAssignmentService::class)->assignToEnrollment($enrollments->first(), $tutor->id);

        // Tutor muncul di class_session_tutor -> boleh isi absensi.
        $this->assertTrue($cs->fresh()->tutors()->where('tutor_id', $tutor->id)->exists());
        // ...dan di SEMUA enrollment kelas itu, bukan cuma satu.
        $this->assertEquals(2, DB::table('enrollment_tutor')->where('tutor_id', $tutor->id)->count());
    }

    #[Test]
    public function confirming_tutor_syncs_status_on_both_lists_and_activates_waitlist()
    {
        $program = Program::factory()->create(['min_quota' => 2, 'total_meetings' => 10]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'inactive']);
        $enrollments = Enrollment::factory()->count(2)->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'waitlist',
        ]);
        $tutor = Tutor::factory()->create();
        $svc = app(TutorAssignmentService::class);
        $svc->assignToClassSession($cs, $tutor->id);

        $svc->setStatus($cs->fresh()->load('program'), $tutor->id, 'confirmed');

        $this->assertDatabaseHas('class_session_tutor', ['class_session_id' => $cs->id, 'tutor_id' => $tutor->id, 'status' => 'confirmed']);
        $this->assertEquals(2, DB::table('enrollment_tutor')->where('tutor_id', $tutor->id)->where('status', 'confirmed')->count());
        // quota terpenuhi + tutor confirmed -> waitlist jadi active
        $this->assertEquals(0, Enrollment::where('class_session_id', $cs->id)->where('status', 'waitlist')->count());
        $this->assertEquals('active', $cs->fresh()->status);
    }

    #[Test]
    public function removing_tutor_from_class_clears_both_lists()
    {
        [$cs, $enrollments] = $this->makeClassWithStudents(2);
        $tutor = Tutor::factory()->create();
        $svc = app(TutorAssignmentService::class);
        $svc->assignToClassSession($cs, $tutor->id);

        $svc->removeFromClassSession($cs, $tutor->id);

        $this->assertFalse($cs->fresh()->tutors()->where('tutor_id', $tutor->id)->exists());
        $this->assertEquals(0, DB::table('enrollment_tutor')->where('tutor_id', $tutor->id)->count());
    }

    #[Test]
    public function moving_a_student_into_a_class_gives_them_the_class_tutors()
    {
        [$cs] = $this->makeClassWithStudents(1);
        $tutor = Tutor::factory()->create();
        app(TutorAssignmentService::class)->assignToClassSession($cs, $tutor->id);

        $newbie = Enrollment::factory()->create(['program_id' => $cs->program_id, 'class_session_id' => null, 'status' => 'active']);
        $newbie->update(['class_session_id' => $cs->id]);
        app(TutorAssignmentService::class)->syncEnrollmentToClassTutors($newbie->fresh()->load('classSession.program'));

        $this->assertDatabaseHas('enrollment_tutor', ['enrollment_id' => $newbie->id, 'tutor_id' => $tutor->id]);
    }
}
