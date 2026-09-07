<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RELIABILITY — elemen interaktif tiap peran (filter, search bar, dropdown AJAX)
 * benar-benar menyaring seperti yang dimaksud, tidak "halu".
 *
 * Pola tiap test: bikin data yang bisa dibedakan -> panggil endpoint dg filter
 * -> tegaskan hasilnya PERSIS subset yang benar (jumlah + id yang ada/tidak).
 */
class InteractiveEndpointsReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['1001', '1002', '1003', '2002', '2003', '4101', '5001'] as $code) {
            Account::factory()->create(['code' => $code, 'name' => "Acc {$code}"]);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cfo = User::factory()->create(['role' => 'cfo']);
    }

    private function ids($json): array
    {
        $data = $json['data'] ?? $json;

        return collect($data)->pluck('id')->sort()->values()->all();
    }

    // ═══ ADMIN — daftar enrollment (search + filter status + filter bayar) ═══

    #[Test]
    public function enrollment_list_filters_return_exactly_the_right_rows()
    {
        $prgA = Program::factory()->create(['name' => 'Kelas Alpha']);
        $prgB = Program::factory()->create(['name' => 'Kelas Beta']);
        $andi = Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Andi Wijaya'])->id]);
        $budi = Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Budi Santoso'])->id]);

        $e1 = Enrollment::factory()->create(['student_id' => $andi->id, 'program_id' => $prgA->id, 'status' => 'active', 'payment_status' => 'full']);
        $e2 = Enrollment::factory()->create(['student_id' => $budi->id, 'program_id' => $prgA->id, 'status' => 'waitlist', 'payment_status' => 'partial']);
        $e3 = Enrollment::factory()->create(['student_id' => $budi->id, 'program_id' => $prgB->id, 'status' => 'active', 'payment_status' => 'partial']);

        // tanpa filter -> semua
        $all = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data'))->json();
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id, $e3->id], $this->ids($all));

        // search nama siswa
        $byName = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['search' => 'Andi']))->json();
        $this->assertSame([$e1->id], $this->ids($byName));

        // search nama program
        $byProgram = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['search' => 'Beta']))->json();
        $this->assertSame([$e3->id], $this->ids($byProgram));

        // filter status
        $active = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['status' => 'active']))->json();
        $this->assertEqualsCanonicalizing([$e1->id, $e3->id], $this->ids($active));

        // filter payment_status
        $partial = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['payment_status' => 'partial']))->json();
        $this->assertEqualsCanonicalizing([$e2->id, $e3->id], $this->ids($partial));

        // filter gabungan
        $combo = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['status' => 'active', 'payment_status' => 'partial']))->json();
        $this->assertSame([$e3->id], $this->ids($combo));
    }

    #[Test]
    public function enrollment_list_pagination_is_honest()
    {
        Enrollment::factory()->count(25)->create();

        $p1 = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['size' => 10, 'page' => 1]))->json();
        $p3 = $this->actingAs($this->admin)->getJson(route('admin.enrollments.data', ['size' => 10, 'page' => 3]))->json();

        $this->assertCount(10, $p1['data']);
        $this->assertCount(5, $p3['data']);
        $this->assertSame(3, (int) $p1['last_page']);
        $this->assertEmpty(array_intersect($this->ids($p1), $this->ids($p3)), 'halaman tidak boleh overlap');
    }

    // ═══ ADMIN — form enroll: autocomplete siswa ═══

    #[Test]
    public function student_search_autocomplete_matches_name_and_email_only()
    {
        Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Sinta Melati', 'email' => 'sinta@mail.test'])->id]);
        Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Rina Kartika', 'email' => 'rina@mail.test'])->id]);

        $byName = $this->actingAs($this->admin)->getJson(route('admin.enrollments.students.search', ['q' => 'Sinta']))->json();
        $this->assertCount(1, $byName);
        $this->assertSame('Sinta Melati', $byName[0]['name']);

        $byEmail = $this->actingAs($this->admin)->getJson(route('admin.enrollments.students.search', ['q' => 'rina@mail']))->json();
        $this->assertCount(1, $byEmail);
        $this->assertSame('Rina Kartika', $byEmail[0]['name']);

        $none = $this->actingAs($this->admin)->getJson(route('admin.enrollments.students.search', ['q' => 'Zzz']))->json();
        $this->assertCount(0, $none);
    }

    // ═══ ADMIN — form enroll: sesi kelas yang layak ═══

    #[Test]
    public function eligible_sessions_are_scoped_to_the_program_and_day_time()
    {
        $prg = Program::factory()->create(['type' => 'group', 'total_meetings' => 20, 'min_quota' => 1]);
        $otherPrg = Program::factory()->create(['type' => 'group']);
        $room = Classroom::factory()->create(['capacity' => 10]);

        $csMon = ClassSession::factory()->create(['program_id' => $prg->id, 'status' => 'active', 'name' => 'Grup Senin']);
        Schedule::factory()->create(['class_session_id' => $csMon->id, 'classroom_id' => $room->id, 'day' => 'Senin', 'time_block' => '08:00-09:30']);
        $csWed = ClassSession::factory()->create(['program_id' => $prg->id, 'status' => 'active', 'name' => 'Grup Rabu']);
        Schedule::factory()->create(['class_session_id' => $csWed->id, 'classroom_id' => $room->id, 'day' => 'Rabu', 'time_block' => '08:00-09:30']);
        ClassSession::factory()->create(['program_id' => $otherPrg->id, 'status' => 'active', 'name' => 'Grup Lain']);
        ClassSession::factory()->create(['program_id' => $prg->id, 'status' => 'inactive', 'name' => 'Grup Nonaktif']);

        // program saja -> hanya sesi aktif program itu (2)
        $r = $this->actingAs($this->admin)->getJson(route('admin.enrollments.sessions.eligible', ['program_id' => $prg->id]))->json();
        $this->assertEqualsCanonicalizing([$csMon->id, $csWed->id], collect($r)->pluck('id')->all());

        // + hari & jam -> hanya yang cocok
        $r2 = $this->actingAs($this->admin)->getJson(route('admin.enrollments.sessions.eligible', [
            'program_id' => $prg->id, 'day' => 'Senin', 'time_block' => '08:00-09:30',
        ]))->json();
        $this->assertSame([$csMon->id], collect($r2)->pluck('id')->all());

        // + pencarian nama
        $r3 = $this->actingAs($this->admin)->getJson(route('admin.enrollments.sessions.eligible', ['program_id' => $prg->id, 'q' => 'Rabu']))->json();
        $this->assertSame([$csWed->id], collect($r3)->pluck('id')->all());
    }

    #[Test]
    public function eligible_sessions_hides_full_group_classes()
    {
        $prg = Program::factory()->create(['type' => 'group', 'total_meetings' => 20, 'min_quota' => 1]);
        $room = Classroom::factory()->create(['capacity' => 2]);
        $cs = ClassSession::factory()->create(['program_id' => $prg->id, 'status' => 'active']);
        Schedule::factory()->create(['class_session_id' => $cs->id, 'classroom_id' => $room->id, 'day' => 'Senin', 'time_block' => '08:00-09:30']);
        Enrollment::factory()->count(2)->create(['program_id' => $prg->id, 'class_session_id' => $cs->id, 'status' => 'active']);

        $r = $this->actingAs($this->admin)->getJson(route('admin.enrollments.sessions.eligible', ['program_id' => $prg->id]))->json();
        $this->assertCount(0, $r, 'kelas penuh (2/2) tidak boleh muncul');
    }

    // ═══ ADMIN — tutor yang available di slot tertentu ═══

    #[Test]
    public function available_tutors_excludes_those_already_booked_at_that_slot()
    {
        $free = Tutor::factory()->create();
        TutorAvailability::factory()->create(['tutor_id' => $free->id, 'day' => 'Selasa', 'time_block' => '10:00-11:30', 'status' => 'available']);

        $busy = Tutor::factory()->create();
        TutorAvailability::factory()->create(['tutor_id' => $busy->id, 'day' => 'Selasa', 'time_block' => '10:00-11:30', 'status' => 'available']);
        $cs = ClassSession::factory()->create();
        $cs->tutors()->attach($busy->id, ['status' => 'confirmed']);
        $room = Classroom::factory()->create();
        Schedule::factory()->create(['class_session_id' => $cs->id, 'classroom_id' => $room->id, 'day' => 'Selasa', 'time_block' => '10:00-11:30']);

        $r = $this->actingAs($this->admin)->getJson(route('admin.enrollments.tutors.available', [
            'day' => 'Selasa', 'time_block' => '10:00-11:30',
        ]))->json();

        $this->assertSame([$free->id], collect($r)->pluck('id')->all());
    }

    // ═══ ADMIN — class session dropdown: enrollment yang belum punya kelas ═══

    #[Test]
    public function available_enrollments_only_lists_active_unassigned_of_that_program()
    {
        $prg = Program::factory()->create();
        $wanted = Enrollment::factory()->create(['program_id' => $prg->id, 'status' => 'active', 'class_session_id' => null]);
        Enrollment::factory()->create(['program_id' => $prg->id, 'status' => 'waitlist', 'class_session_id' => null]); // bukan active
        $cs = ClassSession::factory()->create(['program_id' => $prg->id]);
        Enrollment::factory()->create(['program_id' => $prg->id, 'status' => 'active', 'class_session_id' => $cs->id]); // sudah ada kelas
        Enrollment::factory()->create(['status' => 'active', 'class_session_id' => null]); // program lain

        $r = $this->actingAs($this->admin)->getJson(route('admin.class-sessions.available-enrollments', $prg->id))->json();

        $this->assertSame([$wanted->id], collect($r)->pluck('id')->all());
    }

    #[Test]
    public function class_session_info_counts_are_accurate()
    {
        $prg = Program::factory()->create(['total_meetings' => 12, 'price' => 900_000]);
        $room = Classroom::factory()->create(['capacity' => 8]);
        $cs = ClassSession::factory()->create(['program_id' => $prg->id]);
        Schedule::factory()->create(['class_session_id' => $cs->id, 'classroom_id' => $room->id]);
        Enrollment::factory()->count(3)->create(['program_id' => $prg->id, 'class_session_id' => $cs->id, 'status' => 'active']);
        Enrollment::factory()->create(['program_id' => $prg->id, 'class_session_id' => $cs->id, 'status' => 'graduate']);
        Attendance::factory()->count(2)->create(['class_session_id' => $cs->id, 'status' => 'finished']);
        Attendance::factory()->create(['class_session_id' => $cs->id, 'status' => 'scheduled']);

        $r = $this->actingAs($this->admin)->getJson(route('admin.class-sessions.info', $cs->id))->json();

        $this->assertSame(2, $r['finished_meetings']);
        $this->assertSame(12, $r['total_meetings']);
        $this->assertSame(10, $r['remaining_default']);
        $this->assertSame(3, $r['enrollment_count']); // graduate tidak dihitung
        $this->assertSame(8, $r['capacity']);
        $this->assertTrue($r['is_mid_join']);
    }

    // ═══ ADMIN — daftar siswa: filter inactive / overdue ═══

    #[Test]
    public function student_list_inactive_and_overdue_filters_work()
    {
        $activeStu = Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Aktif'])->id]);
        Enrollment::factory()->create(['student_id' => $activeStu->id, 'status' => 'active']);

        $idleStu = Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Nganggur'])->id]);
        Enrollment::factory()->create(['student_id' => $idleStu->id, 'status' => 'graduate']);

        $overdueStu = Student::factory()->create(['user_id' => User::factory()->create(['name' => 'Nunggak'])->id]);
        $od = Enrollment::factory()->create(['student_id' => $overdueStu->id, 'status' => 'active', 'payment_method' => 'installment']);
        Installment::factory()->create(['enrollment_id' => $od->id, 'paid_at' => null, 'due_date' => now()->subMonth()]);

        $inactive = $this->actingAs($this->admin)->getJson(route('admin.students.data', ['filter' => 'inactive']))->json();
        $this->assertEqualsCanonicalizing([$idleStu->id], $this->ids($inactive));

        $overdue = $this->actingAs($this->admin)->getJson(route('admin.students.data', ['filter' => 'overdue']))->json();
        $this->assertEqualsCanonicalizing([$overdueStu->id], $this->ids($overdue));
    }

    // ═══ ADMIN — daftar absensi: filter tanggal / status / tutor / tipe ═══

    #[Test]
    public function attendance_list_filters_narrow_correctly()
    {
        $room = Classroom::factory()->create();
        $prgPrivate = Program::factory()->create(['type' => 'private']);
        $prgGroup = Program::factory()->create(['type' => 'group']);
        $csP = ClassSession::factory()->create(['program_id' => $prgPrivate->id]);
        $csG = ClassSession::factory()->create(['program_id' => $prgGroup->id]);

        $tutU = User::factory()->create(['name' => 'Pak Guru Xavier']);
        $tut = Tutor::factory()->create(['user_id' => $tutU->id]);

        $jan = Attendance::factory()->create(['class_session_id' => $csP->id, 'classroom_id' => $room->id, 'date' => '2026-01-15', 'status' => 'finished']);
        $jan->tutors()->attach($tut->id, ['payable_amount' => 0, 'pending_rate' => true]);
        $mar = Attendance::factory()->create(['class_session_id' => $csG->id, 'classroom_id' => $room->id, 'date' => '2026-03-20', 'status' => 'scheduled']);

        $byDate = $this->actingAs($this->admin)->getJson(route('admin.attendance.data', ['date_from' => '2026-02-01', 'date_to' => '2026-12-31']))->json();
        $this->assertSame([$mar->id], $this->ids($byDate['rows']));

        $byStatus = $this->actingAs($this->admin)->getJson(route('admin.attendance.data', ['status' => 'finished']))->json();
        $this->assertSame([$jan->id], $this->ids($byStatus['rows']));

        $byTutor = $this->actingAs($this->admin)->getJson(route('admin.attendance.data', ['tutor' => 'Xavier']))->json();
        $this->assertSame([$jan->id], $this->ids($byTutor['rows']));

        $byType = $this->actingAs($this->admin)->getJson(route('admin.attendance.data', ['program_type' => 'group']))->json();
        $this->assertSame([$mar->id], $this->ids($byType['rows']));
    }

    // ═══ FINANCE — daftar jurnal: search + rentang tanggal ═══

    #[Test]
    public function journal_list_search_and_date_range_work()
    {
        $acc = Account::where('code', '1002')->first();
        $mk = function (string $ref, string $desc, string $date) use ($acc) {
            $j = Journal::create(['date' => $date, 'description' => $desc, 'reference' => $ref, 'total_amount' => 1000, 'type' => 'general']);
            JournalItem::create(['journal_id' => $j->id, 'account_id' => $acc->id, 'debit' => 1000, 'credit' => 0]);

            return $j;
        };
        $a = $mk('PAYMENT-ENROLL-1', 'Pembayaran siswa', '2026-07-10');
        $b = $mk('REV-REC-5-5', 'Pengakuan pendapatan', '2026-07-20');
        $c = $mk('PAYROLL-1', 'Gaji tutor', '2026-08-05');

        $bySearchRef = $this->actingAs($this->cfo)->getJson(route('finance.journals.data', ['search' => 'PAYMENT-ENROLL']))->json();
        $this->assertSame([$a->id], $this->ids($bySearchRef));

        $bySearchDesc = $this->actingAs($this->cfo)->getJson(route('finance.journals.data', ['search' => 'pendapatan']))->json();
        $this->assertSame([$b->id], $this->ids($bySearchDesc));

        $byDate = $this->actingAs($this->cfo)->getJson(route('finance.journals.data', ['date_from' => '2026-08-01']))->json();
        $this->assertSame([$c->id], $this->ids($byDate));
    }

    // ═══ TUTOR — hanya kelasnya sendiri + filter search/tanggal ═══

    #[Test]
    public function tutor_attendance_list_shows_only_own_classes_and_respects_search()
    {
        $room = Classroom::factory()->create();
        $mineUser = User::factory()->create(['role' => 'tutor']);
        $mine = Tutor::factory()->create(['user_id' => $mineUser->id]);
        $other = Tutor::factory()->create();

        $prg = Program::factory()->create(['name' => 'IELTS Prep']);
        $csMine = ClassSession::factory()->create(['program_id' => $prg->id]);
        $csOther = ClassSession::factory()->create(['program_id' => $prg->id]);

        $attMine = Attendance::factory()->create(['class_session_id' => $csMine->id, 'classroom_id' => $room->id, 'date' => '2026-07-10']);
        $attMine->tutors()->attach($mine->id, ['payable_amount' => 0]);
        $attOther = Attendance::factory()->create(['class_session_id' => $csOther->id, 'classroom_id' => $room->id, 'date' => '2026-07-11']);
        $attOther->tutors()->attach($other->id, ['payable_amount' => 0]);

        $all = $this->actingAs($mineUser)->getJson(route('tutor.attendance.data'))->json();
        $this->assertSame([$attMine->id], collect($all)->pluck('id')->all());

        $bySearch = $this->actingAs($mineUser)->getJson(route('tutor.attendance.data', ['search' => 'IELTS']))->json();
        $this->assertSame([$attMine->id], collect($bySearch)->pluck('id')->all());

        $byDateEmpty = $this->actingAs($mineUser)->getJson(route('tutor.attendance.data', ['date_from' => '2026-08-01']))->json();
        $this->assertCount(0, $byDateEmpty);
    }

    #[Test]
    public function tutor_session_search_own_mode_only_returns_assigned_classes()
    {
        $mineUser = User::factory()->create(['role' => 'tutor']);
        $mine = Tutor::factory()->create(['user_id' => $mineUser->id]);

        $prg = Program::factory()->create(['name' => 'TOEFL Booster']);
        $csMine = ClassSession::factory()->create(['program_id' => $prg->id, 'status' => 'active', 'name' => 'TOEFL Booster A']);
        $csMine->tutors()->attach($mine->id, ['status' => 'confirmed']);
        ClassSession::factory()->create(['program_id' => $prg->id, 'status' => 'active', 'name' => 'TOEFL Booster B']);

        $own = $this->actingAs($mineUser)->getJson(route('tutor.attendance.search-sessions', ['q' => 'TOEFL', 'mode' => 'own']))->json();
        $this->assertSame([$csMine->id], collect($own)->pluck('id')->all());

        // mode replacement -> boleh lihat semua sesi (untuk menggantikan)
        $repl = $this->actingAs($mineUser)->getJson(route('tutor.attendance.search-sessions', ['q' => 'TOEFL', 'mode' => 'replacement']))->json();
        $this->assertCount(2, $repl);
    }
}
