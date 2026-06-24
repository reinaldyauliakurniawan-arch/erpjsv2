<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\PayrollRun;
use App\Models\Program;
use App\Models\Rab;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the global search endpoint.
 *
 * Verifies:
 *   - Admin role sees operational entities (students, tutors, programs, etc.)
 *   - CFO role sees finance entities (accounts, journals, payroll, etc.)
 *   - Role isolation: admin cannot see CFO entities and vice versa
 *   - Minimum 2 characters required
 *   - Substring matching works (case-insensitive on MySQL utf8mb4_unicode_ci)
 *   - Empty result when no match
 *   - JSON response structure
 *   - Throttle (30 req/min) — covered by middleware, not tested here
 *   - Authorization: guest → 401, wrong role → 403
 */
class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->cfo   = User::factory()->cfo()->create();
    }

    // =========================================================
    //  AUTHORIZATION
    // =========================================================

    #[Test]
    public function guest_gets_401_json(): void
    {
        $this->getJson('/search?q=test')
            ->assertStatus(401)
            ->assertJson(['error' => 'unauthenticated']);
    }

    #[Test]
    public function tutor_role_is_forbidden(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->getJson('/search?q=test')
            ->assertStatus(403)
            ->assertJson(['error' => 'forbidden']);
    }

    #[Test]
    public function student_role_is_forbidden(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->getJson('/search?q=test')
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_access_search(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/search?q=ab')
            ->assertOk()
            ->assertJsonStructure(['query', 'results', 'total']);
    }

    #[Test]
    public function cfo_can_access_search(): void
    {
        $this->actingAs($this->cfo)
            ->getJson('/search?q=ab')
            ->assertOk()
            ->assertJsonStructure(['query', 'results', 'total']);
    }

    // =========================================================
    //  VALIDATION
    // =========================================================

    #[Test]
    public function requires_q_parameter(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function rejects_single_character_query(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/search?q=a')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function rejects_query_over_100_chars(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/search?q=' . str_repeat('a', 101))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    // =========================================================
    //  ADMIN SEARCH — operational entities
    // =========================================================

    #[Test]
    public function admin_finds_student_by_name(): void
    {
        $student = Student::factory()->create();
        // The factory creates a user with a random name; we override it here
        $student->user->update(['name' => 'Andi Pratama']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=Andi')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Students'));
        $this->assertSame('Andi Pratama', $response->json('results.Students.0.label'));
    }

    #[Test]
    public function admin_finds_student_by_email_substring(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['email' => 'budi.santoso@example.com']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=santoso@example')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Students'));
    }

    #[Test]
    public function admin_finds_tutor_by_persona(): void
    {
        // Create a tutor with a unique persona we can search for.
        // Use a Tutor user (role=tutor) so the factory chain is realistic.
        $tutorUser = User::factory()->tutor()->create();
        $tutor = Tutor::factory()->forUser($tutorUser)->create(['persona' => 'NativeSpeakerUnique']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=NativeSpeakerUnique')
            ->assertOk();

        $tutorResults = $response->json('results.Tutors', []);
        $this->assertNotEmpty($tutorResults, 'Expected to find at least one tutor with persona "NativeSpeakerUnique"');

        // Verify the matching tutor is in the results
        $found = false;
        foreach ($tutorResults as $t) {
            if (str_contains(strtolower($t['subtitle'] ?? ''), 'nativespeakerunique')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Tutor with persona "NativeSpeakerUnique" was not in the search results');
    }

    #[Test]
    public function admin_finds_program_by_name(): void
    {
        Program::factory()->create(['name' => 'English Beginner']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=Beginner')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Programs'));
        $this->assertSame('English Beginner', $response->json('results.Programs.0.label'));
    }

    #[Test]
    public function admin_finds_enrollment_via_student_name(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['name' => 'Citra Lestari']);
        Enrollment::factory()->create(['student_id' => $student->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=Citra')
            ->assertOk();

        $enrollmentResults = $response->json('results.Enrollments', []);
        $this->assertNotEmpty($enrollmentResults);
        $this->assertStringContainsString('Citra Lestari', $enrollmentResults[0]['label']);
    }

    #[Test]
    public function admin_finds_class_session_by_name(): void
    {
        ClassSession::factory()->create(['name' => 'Morning Conversation']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=Morning')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Class Sessions'));
    }

    #[Test]
    public function admin_finds_classroom_by_name(): void
    {
        Classroom::factory()->create(['name' => 'Studio A']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=Studio')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Classrooms'));
    }

    // =========================================================
    //  CFO SEARCH — finance entities
    // =========================================================

    #[Test]
    public function cfo_finds_account_by_code(): void
    {
        Account::factory()->create(['code' => '1101', 'name' => 'Cash']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=1101')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Accounts'));
    }

    #[Test]
    public function cfo_finds_account_by_name_substring(): void
    {
        Account::factory()->create(['code' => '9999', 'name' => 'Bank BCA']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=BCA')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Accounts'));
    }

    #[Test]
    public function cfo_finds_journal_by_reference(): void
    {
        Journal::factory()->create(['reference' => 'INV-2025-001', 'description' => 'Test']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=INV-2025')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Journals'));
    }

    #[Test]
    public function cfo_finds_journal_by_description_substring(): void
    {
        Journal::factory()->create([
            'reference'   => 'UNIQUE-REF-' . uniqid(),
            'description' => 'Monthly rent payment for office',
        ]);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=rent payment')
            ->assertOk();

        $journalResults = $response->json('results.Journals', []);
        $this->assertNotEmpty($journalResults);
    }

    #[Test]
    public function cfo_finds_payroll_run_by_month(): void
    {
        PayrollRun::factory()->create(['month' => '2025-06-01', 'status' => 'approved']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=2025-06')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Payroll Runs'));
    }

    #[Test]
    public function cfo_finds_fixed_asset_by_name(): void
    {
        FixedAsset::factory()->create(['name' => 'Proyektor Epson', 'category' => 'Peralatan']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=Proyektor')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Fixed Assets'));
    }

    #[Test]
    public function cfo_finds_rab_by_division(): void
    {
        Rab::factory()->create(['division' => 'Marketing', 'year' => 2025, 'account_name' => 'Ads']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=Marketing')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.RAB'));
    }

    // =========================================================
    //  ROLE ISOLATION
    // =========================================================

    #[Test]
    public function admin_does_not_see_finance_entities(): void
    {
        // Create finance data that only CFO should find
        Account::factory()->create(['code' => 'UNIQUE-CODE-999', 'name' => 'UniqAccountName']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=UniqAccountName')
            ->assertOk();

        // Admin should see no results — finance entities aren't in their search scope
        $this->assertSame(0, $response->json('total'));
    }

    #[Test]
    public function cfo_does_not_see_operational_entities(): void
    {
        // Create operational data that only admin should find
        $student = Student::factory()->create();
        $student->user->update(['name' => 'UniqueStudentName']);

        $response = $this->actingAs($this->cfo)
            ->getJson('/search?q=UniqueStudentName')
            ->assertOk();

        $this->assertSame(0, $response->json('total'));
    }

    // =========================================================
    //  EDGE CASES
    // =========================================================

    #[Test]
    public function returns_empty_results_when_no_match(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=ZZZNoSuchEntityZZZ')
            ->assertOk();

        $this->assertSame(0, $response->json('total'));
        $this->assertSame([], $response->json('results'));
    }

    #[Test]
    public function result_item_includes_url_for_navigation(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['name' => 'TestStudentWithUrl']);

        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=TestStudentWithUrl')
            ->assertOk();

        $item = $response->json('results.Students.0');
        $this->assertArrayHasKey('url', $item);
        $this->assertStringContainsString('/admin/students/', $item['url']);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['name' => 'UPPERCASE_NAME']);

        // Search with lowercase — MySQL utf8mb4_unicode_ci is case-insensitive
        $response = $this->actingAs($this->admin)
            ->getJson('/search?q=uppercase_name')
            ->assertOk();

        $this->assertNotEmpty($response->json('results.Students'));
    }
}
