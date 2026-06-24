<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    // Account codes sesuai AccountCode enum
    const ACC_CASH             = '1001';
    const ACC_BANK             = '1002';
    const ACC_DEFERRED_REV     = '2002';
    const ACC_TUTOR_PAYABLE    = '2003';
    const ACC_REVENUE_TUITION  = '4101';
    const ACC_EXPENSE_TUTOR    = '5101';

    const PROGRAM_MEETINGS = [
        1=>6,2=>10,3=>20,4=>6,5=>10,6=>20,7=>6,8=>10,9=>20,
        10=>12,11=>24,12=>12,13=>24,14=>80,15=>80,16=>1,
        17=>6,18=>6,19=>6,20=>10,21=>10,22=>10,23=>20,24=>20,25=>20,
        26=>6,27=>10,28=>20,29=>24,30=>20,31=>1,32=>1,33=>1,
    ];

    const PROGRAM_TYPE = [
        1=>'private',2=>'private',3=>'private',4=>'private',5=>'private',
        6=>'private',7=>'private',8=>'private',9=>'private',10=>'private',
        11=>'private',12=>'private',13=>'private',14=>'private',15=>'private',
        16=>'private',17=>'semi-private',18=>'semi-private',19=>'semi-private',
        20=>'semi-private',21=>'semi-private',22=>'semi-private',23=>'semi-private',
        24=>'semi-private',25=>'semi-private',26=>'semi-private',27=>'semi-private',
        28=>'semi-private',29=>'group',30=>'group',31=>'group',32=>'group',33=>'group',
    ];

    const PROGRAM_PRICES = [
        1=>750000,2=>1200000,3=>2200000,4=>800000,5=>1300000,6=>2400000,
        7=>800000,8=>1300000,9=>2400000,10=>1500000,11=>2800000,
        12=>1200000,13=>2200000,14=>8000000,15=>8500000,16=>300000,
        17=>600000,18=>500000,19=>450000,20=>950000,21=>800000,22=>750000,
        23=>1800000,24=>1500000,25=>1400000,26=>650000,27=>1050000,28=>1950000,
        29=>1800000,30=>1500000,31=>500000,32=>500000,33=>500000,
    ];

    const EXISTING_TUTOR_IDS   = [];
    const EXISTING_STUDENT_IDS = [];
    const TRACKER_COLUMN_IDS   = [4,5,6];
    const ADMIN_USER_ID        = 1;
    const CLASSROOMS_SMALL     = [5,6,7];
    const CLASSROOMS_LARGE     = [2,3,4,8];
    const CLASSROOM_ONLINE     = 9;

    const ALL_DAYS = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const ALL_SLOTS = [
        'Senin'  => ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'],
        'Selasa' => ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'],
        'Rabu'   => ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'],
        'Kamis'  => ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'],
        'Jumat'  => ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'],
        'Sabtu'  => ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00'],
    ];

    // Cache: tutorId → [day → [slot, ...]] hanya yang status='available'
    private array $availCache = [];
    // Cache: account_code → account_id
    private array $accountIds = [];


    use \Database\Seeders\Traits\HasSeederHelpers,
        \Database\Seeders\Traits\HasStudentSeeders,
        \Database\Seeders\Traits\HasEnrollmentSeeders,
        \Database\Seeders\Traits\HasAttendanceSeeders,
        \Database\Seeders\Traits\HasFinancialSeeders,
        \Database\Seeders\Traits\HasOperationsSeeders;

    public function run(): void
    {
        // ──────────────────────────────────────────────────────────────
        // Ensure foundational data exists before seeding transactions.
        // On a fresh database (empty), DatabaseSeeder would fail because
        // it references Program IDs 1-33, Classroom IDs 1-9, and Tutor
        // records by ID — none of which exist yet. InitialDataSeeder
        // creates them idempotently.
        // ──────────────────────────────────────────────────────────────
        $this->call([InitialDataSeeder::class]);

        // Cleanup seeded data agar aman dijalankan ulang.
        // Cross-database: SQLite uses PRAGMA foreign_keys=OFF, MySQL uses
        // SET FOREIGN_KEY_CHECKS=0. We detect the driver and use the right
        // syntax. TRUNCATE is faster on MySQL but resets auto-increment;
        // DELETE preserves auto-increment and works on both. Using DELETE
        // for portability — the seeder runs infrequently so the perf
        // difference is negligible.
        $this->disableForeignKeys();
        DB::table('attendance_tutor')->delete();
        DB::table('attendance_student')->delete();
        DB::table('attendance')->delete();
        DB::table('installments')->delete();
        DB::table('enrollments')->delete();
        DB::table('enrollment_tutor')->delete();
        DB::table('schedules')->delete();
        DB::table('room_bookings')->delete();
        DB::table('class_session_tutor')->delete();
        DB::table('class_sessions')->delete();
        DB::table('tutor_availability')->delete();
        DB::table('tutor_rates')->delete();
        DB::table('payroll_runs')->delete();
        DB::table('journal_items')->delete();
        DB::table('journals')->delete();
        DB::table('tracker_entries')->delete();
        DB::table('practices')->delete();
        DB::table('practice_student')->delete();
        // Only delete seeded students (email pattern), preserve any manually-created ones
        DB::table('students')->whereNotExists(function($q) {
            $q->select(DB::raw(1))->from('users')
              ->whereColumn('users.id', 'students.user_id')
              ->whereNotLike('users.email', 'student%@justspeak.test');
        })->delete();
        DB::table('users')->where('email','like','student%@justspeak.test')->delete();
        $this->enableForeignKeys();

        $this->call([ChartOfAccountsSeeder::class]);
        $this->buildAccountCache();

        $existingStudentIds = DB::table('students')->pluck('id')->toArray();
        $existingTutorIds = DB::table('tutors')->pluck('id')->toArray();

        $this->command->info('Seeding students...');
        $newStudentIds = $this->seedStudents(20);
        $allStudentIds = array_merge($existingStudentIds, $newStudentIds);

        $this->command->info('Seeding tutor rates...');
        $this->seedTutorRates();

        $this->command->info('Seeding tutor availability...');
        $this->seedTutorAvailability();
        $this->buildAvailabilityCache();

        $this->command->info('Seeding private enrollments...');
        $privateEnrollments = $this->seedPrivateEnrollments($allStudentIds);

        $this->command->info('Seeding semi-private sessions...');
        $semiEnrollments = $this->seedSemiPrivateSessions($allStudentIds);

        $this->command->info('Seeding group sessions...');
        $groupEnrollments = $this->seedGroupSessions($allStudentIds);

        $allEnrollments = array_merge($privateEnrollments, $semiEnrollments, $groupEnrollments);

        $this->command->info('Seeding attendance + rev-rec journals...');
        $this->seedAttendanceWithRevRec($allEnrollments);

        $this->command->info('Seeding installments...');
        $this->seedInstallments($allEnrollments);

        $this->command->info('Seeding payroll runs + marking paid...');
        $this->seedPayrollRuns();

        $this->command->info('Seeding room bookings...');
        $this->seedRoomBookings($allEnrollments);

        $this->command->info('Seeding tracker entries...');
        $this->seedTrackerEntries($allStudentIds);

        $this->command->info('Seeding practices...');
        $this->seedPractices($allStudentIds);

        $this->command->info('Seeding fixed assets...');
        $this->seedFixedAssets();

        $this->command->info('Seeding RAB...');
        $this->seedRab();

        $this->command->info('Seeding adjusting journals...');
        $this->seedAdjustingJournals();

        $this->command->info('✓ Done!');
    }

    // ────────────────────────────────────────────────────────────────────
    // BOOTSTRAP CACHES
    // ────────────────────────────────────────────────────────────────────
}
