<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EnrollmentLedgerService;
use App\Support\LedgerStart;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tanggal mulai pembukuan: riwayat sebelum tanggal itu diringkas jadi satu
 * jurnal saldo awal per enrollment; tidak ada jurnal enrollment bertanggal
 * sebelumnya, dan buku besar tetap sinkron dengan data operasional.
 */
class LedgerStartTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2026-09-28';

    private EnrollmentLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->ledger = app(EnrollmentLedgerService::class);
        LedgerStart::set(self::START);
    }

    #[Test]
    public function rebuild_posts_one_opening_journal_instead_of_backdated_history(): void
    {
        // 10 pertemuan @100rb; bayar 600rb sebelum tanggal mulai; 4 pertemuan
        // sudah terpakai di sistem lama → sisa uang titipan 200rb.
        [$e] = $this->enrollment(openingUsed: 4, paidBefore: 600_000, unpaid: 400_000);

        $this->rebuild($e);

        $this->assertSame(0, DB::table('journals')->where('date', '<', self::START)->count(), 'tidak ada jurnal sebelum tanggal mulai');
        $journals = DB::table('journals')->where('enrollment_id', $e->id)->pluck('reference')->all();
        $this->assertSame(["SALDO-AWAL-ENR-{$e->id}"], $journals);
        $this->assertEqualsWithDelta(200_000, $this->balance('2002', credit: true), 0.01);
        $this->assertEqualsWithDelta(200_000, $this->balance('3003'), 0.01, 'lawannya Ekuitas Saldo Awal');
        $this->assertEqualsWithDelta(0, $this->balance('4101', credit: true), 0.01, 'pendapatan lama tidak ikut masuk');
        $this->assertInSyncAndBalanced($e);
    }

    #[Test]
    public function meetings_and_payments_after_start_continue_from_the_opening_balance(): void
    {
        [$e, $tutorUser] = $this->enrollment(openingUsed: 4, paidBefore: 600_000, unpaid: 400_000);
        $this->rebuild($e);

        // Pertemuan ke-5 & ke-6 sesudah tanggal mulai: diambil dari uang titipan.
        $this->mark($e, $tutorUser, '2026-09-29');
        $this->mark($e, $tutorUser, '2026-09-30');
        $this->assertEqualsWithDelta(200_000, $this->balance('4101', credit: true), 0.01);
        $this->assertEqualsWithDelta(0, $this->balance('2002', credit: true), 0.01);
        $this->assertInSyncAndBalanced($e->fresh());

        // Pertemuan ke-7 belum ada uangnya → jadi tagihan.
        $this->mark($e, $tutorUser, '2026-10-01');
        $this->assertEqualsWithDelta(100_000, $this->balance('1003'), 0.01);
        $this->assertInSyncAndBalanced($e->fresh());

        // Sisa cicilan dibayar sesudah tanggal mulai, lalu buku disusun ulang
        // (jalur yang sama dengan edit enrollment): tagihan lunas dulu.
        $e->installments()->whereNull('paid_at')->update(['paid_at' => '2026-10-02']);
        $this->rebuild($e->fresh());

        $this->assertSame(0, DB::table('journals')->where('date', '<', self::START)->count());
        $this->assertEqualsWithDelta(0, $this->balance('1003'), 0.01);
        $this->assertEqualsWithDelta(300_000, $this->balance('2002', credit: true), 0.01);
        $this->assertEqualsWithDelta(400_000, $this->balance('1002'), 0.01, 'kas hanya yang masuk sejak tanggal mulai');
        $this->assertEqualsWithDelta(300_000, $this->balance('4101', credit: true), 0.01);
        $this->assertInSyncAndBalanced($e->fresh());
    }

    #[Test]
    public function student_who_owed_money_at_start_gets_an_opening_receivable(): void
    {
        // Baru bayar 200rb tapi sudah 4 pertemuan (400rb) → tagihan 200rb.
        [$e] = $this->enrollment(openingUsed: 4, paidBefore: 200_000, unpaid: 800_000);
        $this->rebuild($e);

        $this->assertEqualsWithDelta(200_000, $this->balance('1003'), 0.01);
        $this->assertEqualsWithDelta(200_000, $this->balance('3003', credit: true), 0.01);
        $this->assertInSyncAndBalanced($e);

        // Bayar 500rb sesudah tanggal mulai: 200rb melunasi tagihan, 300rb titipan.
        $e->installments()->whereNull('paid_at')->update(['amount' => 500_000, 'paid_at' => '2026-10-05']);
        Installment::create(['enrollment_id' => $e->id, 'amount' => 300_000, 'due_date' => '2026-11-01', 'payment_channel' => 'transfer']);
        $this->rebuild($e->fresh());

        $this->assertEqualsWithDelta(0, $this->balance('1003'), 0.01);
        $this->assertEqualsWithDelta(300_000, $this->balance('2002', credit: true), 0.01);
        $this->assertInSyncAndBalanced($e->fresh());
    }

    #[Test]
    public function finished_enrollment_forfeit_is_never_dated_before_start(): void
    {
        [$e] = $this->enrollment(openingUsed: 4, paidBefore: 1_000_000, unpaid: 0);
        $e->update(['status' => 'graduate', 'remaining_meetings' => 0]);

        $this->ledger->rebuild($e->fresh(), '2026-07-01');

        $this->assertSame(0, DB::table('journals')->where('date', '<', self::START)->count());
        $this->assertEqualsWithDelta(600_000, $this->balance('4101', credit: true), 0.01, 'sisa uang titipan hangus jadi pendapatan');
        $this->assertEqualsWithDelta(0, $this->balance('2002', credit: true), 0.01);
        $this->assertInSyncAndBalanced($e->fresh());
    }

    // ── helper ───────────────────────────────────────────────────────────

    /** @return array{0:Enrollment,1:User} */
    private function enrollment(int $openingUsed, int $paidBefore, int $unpaid): array
    {
        $program = Program::factory()->create(['total_meetings' => 10, 'price' => 1_000_000, 'min_quota' => 1]);
        $cs = ClassSession::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $tutorUser = User::factory()->create(['role' => 'tutor']);
        $tutor = Tutor::factory()->create(['user_id' => $tutorUser->id]);
        $cs->tutors()->attach($tutor->id, ['status' => 'confirmed']);
        TutorRate::factory()->create(['tutor_id' => $tutor->id, 'program_id' => $program->id, 'rate' => 50_000]);

        $e = Enrollment::factory()->create([
            'program_id' => $program->id, 'class_session_id' => $cs->id, 'status' => 'active',
            'payment_method' => 'installment', 'payment_channel' => 'transfer',
            'payment_status' => $unpaid > 0 ? 'partial' : 'full',
            'remaining_meetings' => 10 - $openingUsed, 'total_amount' => 1_000_000,
            'enrollment_date' => '2026-08-01', 'expiry_date' => '2026-12-31',
        ]);
        $e->opening_meetings_used = $openingUsed;
        $e->save();

        Installment::create(['enrollment_id' => $e->id, 'amount' => $paidBefore, 'due_date' => '2026-08-01', 'paid_at' => '2026-08-01', 'payment_channel' => 'transfer']);
        if ($unpaid > 0) {
            Installment::create(['enrollment_id' => $e->id, 'amount' => $unpaid, 'due_date' => '2026-10-15', 'payment_channel' => 'transfer']);
        }

        return [$e->fresh(), $tutorUser];
    }

    private function rebuild(Enrollment $e): void
    {
        DB::transaction(fn () => $this->ledger->rebuild(Enrollment::with('program')->lockForUpdate()->find($e->id)));
    }

    private function mark(Enrollment $e, User $tutorUser, string $date): void
    {
        app(AttendanceService::class)->markAttendance([
            'class_session_id' => $e->class_session_id, 'date' => $date, 'time_block' => '08:00-09:30',
            'classroom_id' => Classroom::factory()->create()->id, 'marked_by' => $tutorUser->id,
            'students' => [['enrollment_id' => $e->id, 'is_present' => true]],
        ]);
    }

    /** Saldo akun (debit − kredit, atau kebalikannya untuk akun bersaldo kredit). */
    private function balance(string $code, bool $credit = false): float
    {
        $v = (float) DB::table('journal_items as ji')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->where('a.code', $code)
            ->selectRaw('COALESCE(SUM(ji.debit) - SUM(ji.credit), 0) v')->value('v');

        return $credit ? -$v : $v;
    }

    private function assertInSyncAndBalanced(Enrollment $e): void
    {
        $this->assertTrue(
            $this->ledger->isInSync($e->load('program')),
            'buku besar sinkron: target='.json_encode($this->ledger->targetPosition($e)).' posted='.json_encode($this->ledger->postedPosition($e))
        );
        $tb = (float) DB::table('journal_items')->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) v')->value('v');
        $this->assertEqualsWithDelta(0, $tb, 0.01, 'trial balance tetap 0');
    }
}
