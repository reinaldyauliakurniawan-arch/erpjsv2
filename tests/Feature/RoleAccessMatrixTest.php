<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MATRIKS AKSES PERAN — "siapa boleh baca / tulis apa".
 *
 * Menegaskan bahwa setiap area (admin / finance / tutor / student) hanya bisa
 * diakses perannya sendiri, guest selalu dilempar ke login, dan data yang
 * dilihat tutor/siswa hanya miliknya.
 */
class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create(['role' => $role]);
        if ($role === 'tutor') {
            Tutor::factory()->create(['user_id' => $u->id]);
        }
        if ($role === 'student') {
            Student::factory()->create(['user_id' => $u->id]);
        }

        return $u;
    }

    /** @return array<string,array{0:string,1:string}> nama => [method, path] */
    private function routes(): array
    {
        return [
            'admin.students' => ['get', '/admin/students'],
            'admin.enrollments' => ['get', '/admin/enrollments'],
            'admin.class-sessions' => ['get', '/admin/class-sessions'],
            'admin.tutors' => ['get', '/admin/tutors'],
            'finance.dashboard' => ['get', '/finance'],
            'finance.journals' => ['get', '/finance/journals'],
            'finance.payroll' => ['get', '/finance/payroll'],
            'finance.reports.tb' => ['get', '/finance/reports/trial-balance'],
            'tutor.dashboard' => ['get', '/tutor/dashboard'],
            'tutor.attendance' => ['get', '/tutor/attendance'],
            'tutor.availability' => ['get', '/tutor/availability'],
            'student.dashboard' => ['get', '/student/dashboard'],
            'student.practice' => ['get', '/student/practice'],
        ];
    }

    /** @return array<string,string> nama route => area (admin/finance/tutor/student) */
    private function areaOf(): array
    {
        $map = [];
        foreach (array_keys($this->routes()) as $name) {
            $map[$name] = explode('.', $name)[0];
        }

        return $map;
    }

    #[Test]
    public function guest_is_redirected_to_login_everywhere()
    {
        foreach ($this->routes() as $name => [$method, $path]) {
            $this->$method($path)->assertRedirect('/login');
        }
    }

    #[Test]
    public function each_role_can_only_reach_its_own_area()
    {
        $areaForRole = ['admin' => 'admin', 'cfo' => 'finance', 'tutor' => 'tutor', 'student' => 'student'];

        foreach ($areaForRole as $role => $ownArea) {
            $user = $this->user($role);
            foreach ($this->routes() as $name => [$method, $path]) {
                $expectedOwn = $this->areaOf()[$name] === $ownArea;
                $res = $this->actingAs($user)->$method($path);
                if ($expectedOwn) {
                    $this->assertContains($res->status(), [200, 302], "role={$role} SEHARUSNYA boleh {$name} (dapat {$res->status()})");
                } else {
                    $this->assertSame(403, $res->status(), "role={$role} SEHARUSNYA ditolak {$name} (dapat {$res->status()})");
                }
            }
        }
    }

    #[Test]
    public function guest_cannot_read_notifications()
    {
        $this->getJson('/notifications')->assertUnauthorized();
        $this->get('/notifications')->assertRedirect('/login');
    }

    #[Test]
    public function every_authenticated_role_can_read_own_notifications()
    {
        foreach (['admin', 'cfo', 'tutor', 'student'] as $role) {
            $this->actingAs($this->user($role))
                ->getJson('/notifications')
                ->assertOk()
                ->assertJsonStructure(['unread', 'items']);
            auth()->logout();
        }
    }

    #[Test]
    public function tutor_cannot_read_attendance_history_of_a_class_they_do_not_teach()
    {
        $program = Program::factory()->create();
        $cs = ClassSession::factory()->create(['program_id' => $program->id]);
        Enrollment::factory()->create(['program_id' => $program->id, 'class_session_id' => $cs->id]);

        $outsider = $this->user('tutor');

        $this->actingAs($outsider)
            ->get('/tutor/attendance/history?class_session_id='.$cs->id)
            ->assertForbidden();
    }

    #[Test]
    public function student_dashboard_only_shows_the_acting_students_enrollments()
    {
        $program = Program::factory()->create();
        $mine = $this->user('student');
        $other = $this->user('student');

        $myEnrollment = Enrollment::factory()->create([
            'student_id' => $mine->student->id, 'program_id' => $program->id,
        ]);
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $other->student->id, 'program_id' => $program->id,
        ]);

        $res = $this->actingAs($mine)->get('/student/dashboard')->assertOk();
        $enrollments = $res->viewData('enrollments');

        $this->assertTrue($enrollments->contains('id', $myEnrollment->id));
        $this->assertFalse($enrollments->contains('id', $otherEnrollment->id));
    }
}
