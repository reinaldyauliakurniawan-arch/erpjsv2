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
    private function buildAccountCache(): void
    {
        $rows = DB::table('accounts')->select('id', 'code')->get();
        foreach ($rows as $row) {
            $this->accountIds[$row->code] = $row->id;
        }
    }

    private function buildAvailabilityCache(): void
    {
        $rows = DB::table('tutor_availability')
            ->where('status', 'available')
            ->get();
        foreach ($rows as $r) {
            $this->availCache[$r->tutor_id][$r->day][] = $r->time_block;
        }
    }

    private function accountId(string $code): int
    {
        return $this->accountIds[$code] ?? throw new \RuntimeException("Account code {$code} not found");
    }

    /**
     * Disable FK constraints — cross-database.
     *   SQLite: PRAGMA foreign_keys = OFF (per-connection)
     *   MySQL:  SET FOREIGN_KEY_CHECKS = 0
     */
    private function disableForeignKeys(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }
    }

    /**
     * Re-enable FK constraints — cross-database.
     */
    private function enableForeignKeys(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // STUDENTS
    // ────────────────────────────────────────────────────────────────────
    private function seedStudents(int $count): array
    {
        $names = [
            'Andi Pratama','Budi Santoso','Citra Dewi','Dian Rahayu','Eka Putra',
            'Fajar Nugroho','Gita Lestari','Hendra Wijaya','Indah Permata','Joko Susilo',
            'Kartika Sari','Lukman Hakim','Maya Anggraini','Nanda Kusuma','Omar Fauzi',
            'Putri Handayani','Rizky Maulana','Sari Wahyuni','Taufik Hidayat','Umar Bakri',
            'Vina Octavia','Wahyu Setiawan','Xena Maharani','Yusuf Abdillah','Zara Puspita',
        ];
        $now = Carbon::now();
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $userId = DB::table('users')->insertGetId([
                'name'       => $names[$i % count($names)],
                'email'      => 'student' . ($i + 100) . '@justspeak.test',
                'phone'      => '08' . rand(100000000, 999999999),
                'password'   => Hash::make('password'),
                'role'       => 'student',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $ids[] = DB::table('students')->insertGetId([
                'user_id'    => $userId,
                'notes'      => rand(0,3) > 0 ? null : 'Perlu perhatian khusus pada pronunciation.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        return $ids;
    }

    // ────────────────────────────────────────────────────────────────────
    // TUTOR RATES
    // ────────────────────────────────────────────────────────────────────
    private function seedTutorRates(): void
    {
        $now     = Carbon::now();
        $rateMap = ['private'=>[75000,85000,95000,100000],'semi-private'=>[60000,70000,80000],'group'=>[50000,55000,60000]];
        $tutorIds = DB::table('tutors')->pluck('id')->toArray();
        foreach ($tutorIds as $tutorId) {
            $programs = range(1, 33);
            shuffle($programs);
            foreach (array_slice($programs, 0, rand(12, 20)) as $pid) {
                if (!DB::table('tutor_rates')->where('tutor_id',$tutorId)->where('program_id',$pid)->exists()) {
                    $type = self::PROGRAM_TYPE[$pid];
                    DB::table('tutor_rates')->insert([
                        'tutor_id'=>$tutorId,'program_id'=>$pid,
                        'rate'=>$rateMap[$type][array_rand($rateMap[$type])],
                        'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // TUTOR AVAILABILITY
    // ────────────────────────────────────────────────────────────────────
    private function seedTutorAvailability(): void
    {
        $now = Carbon::now();
        $tutorIds = DB::table('tutors')->pluck('id')->toArray();
        foreach ($tutorIds as $tutorId) {
            foreach (self::ALL_SLOTS as $day => $slots) {
                // Tiap tutor available di 4-6 slot per hari
                $available = array_slice($slots, 0, rand(4, count($slots)));
                foreach ($available as $slot) {
                    if (!DB::table('tutor_availability')
                        ->where('tutor_id',$tutorId)->where('day',$day)->where('time_block',$slot)->exists()) {
                        DB::table('tutor_availability')->insert([
                            'tutor_id'=>$tutorId,'day'=>$day,'time_block'=>$slot,
                            'status'=>'available','created_at'=>$now,'updated_at'=>$now,
                        ]);
                    }
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // HELPERS: slot & tutor picking
    // ────────────────────────────────────────────────────────────────────
    private function findSlotForTutor(int $tutorId): ?array
    {
        $cache = $this->availCache[$tutorId] ?? [];
        if (empty($cache)) return null;
        $days = array_keys($cache);
        shuffle($days);
        foreach ($days as $day) {
            $slots = $cache[$day];
            if (!empty($slots)) {
                return ['day'=>$day,'time_block'=>$slots[array_rand($slots)]];
            }
        }
        return null;
    }

    private function pickTutorForProgram(int $programId): int
    {
        $tutorIds = DB::table('tutors')->pluck('id')->toArray();
        $id = DB::table('tutor_rates')
            ->where('program_id',$programId)
            ->inRandomOrder()->value('tutor_id');
        return $id ?? $tutorIds[array_rand($tutorIds)];
    }

    private function getTutorRateAmount(int $tutorId, int $programId): ?float
    {
        return DB::table('tutor_rates')
            ->where('tutor_id',$tutorId)->where('program_id',$programId)
            ->value('rate');
    }

    private function classroomFor(string $type, bool $online = false): int
    {
        if ($online) return self::CLASSROOM_ONLINE;
        return match($type) {
            'group'        => self::CLASSROOMS_LARGE[array_rand(self::CLASSROOMS_LARGE)],
            'semi-private' => self::CLASSROOMS_SMALL[array_rand(self::CLASSROOMS_SMALL)],
            default        => self::CLASSROOMS_SMALL[array_rand(self::CLASSROOMS_SMALL)],
        };
    }

    // ────────────────────────────────────────────────────────────────────
    // JOURNAL helper — replicates AccountingService::createJournal
    // ────────────────────────────────────────────────────────────────────
    private function journal(string $date, string $desc, string $ref, array $lines, string $type = 'general', ?int $programId = null): ?int
    {
        if (DB::table('journals')->where('reference',$ref)->exists()) return null;

        $total = collect($lines)->sum('debit');
        $now   = Carbon::now();

        $jid = DB::table('journals')->insertGetId([
            'date'=>$date,'description'=>$desc,'reference'=>$ref,
            'total_amount'=>$total,'type'=>$type,
            'approved_by'=>self::ADMIN_USER_ID,
            'created_at'=>$now,'updated_at'=>$now,
        ]);

        foreach ($lines as $line) {
            DB::table('journal_items')->insert([
                'journal_id' => $jid,
                'account_id' => $this->accountId($line['code']),
                'debit'      => $line['debit'],
                'credit'     => $line['credit'],
                'program_id' => $programId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $jid;
    }

    // ────────────────────────────────────────────────────────────────────
    // PRIVATE ENROLLMENTS
    // Setiap private enrollment → ClassSession baru (TutorName_StudentName)
    // persis seperti EnrollmentService::enroll
    // ────────────────────────────────────────────────────────────────────
    private function seedPrivateEnrollments(array $studentIds): array
    {
        $now         = Carbon::now();
        $privateProgs = range(1, 16);
        $results     = [];

        foreach ($studentIds as $studentId) {
            $studentUser = DB::table('users')
                ->join('students','students.user_id','=','users.id')
                ->where('students.id',$studentId)
                ->value('users.name');
            $studentFirst = explode(' ', trim($studentUser ?? 'Student'))[0];

            $enrollCount = rand(1, 3);
            for ($e = 0; $e < $enrollCount; $e++) {
                $programId   = $privateProgs[array_rand($privateProgs)];
                $totalMeet   = self::PROGRAM_MEETINGS[$programId];
                $tutorId     = $this->pickTutorForProgram($programId);
                $slot        = $this->findSlotForTutor($tutorId) ?? ['day'=>'Monday','time_block'=>'09:00-10:00'];
                $isOnline    = str_contains(strtolower(DB::table('programs')->where('id',$programId)->value('name') ?? ''),'online');
                $classroomId = $this->classroomFor('private', $isOnline);
                $enrollDate  = Carbon::now()->subDays(rand(30, 300));
                $totalAmount = (float)(self::PROGRAM_PRICES[$programId] ?? 1000000);

                // Tentukan scenario status enrollment
                // Untuk test semua path: active, expired, graduate, cancelled
                $scenario = $this->pickScenario();

                // ClassSession per private enrollment (seperti di EnrollmentService)
                $tutorUser   = DB::table('users')
                    ->join('tutors','tutors.user_id','=','users.id')
                    ->where('tutors.id',$tutorId)->value('users.name');
                $tutorFirst  = explode(' ', trim($tutorUser ?? 'Tutor'))[0];
                $sessionName = $tutorFirst . '_' . $studentFirst . '_' . $e;

                // Pastikan nama unik
                $existing = DB::table('class_sessions')->where('name',$sessionName)->exists();
                if ($existing) $sessionName .= '_' . rand(100,999);

                $sessionId = DB::table('class_sessions')->insertGetId([
                    'name'       => $sessionName,
                    'program_id' => $programId,
                    'class_type' => 'private',
                    'status'     => in_array($scenario['status'],['active']) ? 'active' : 'inactive',
                    'created_at' => $now,'updated_at' => $now,
                ]);

                DB::table('class_session_tutor')->insert([
                    'class_session_id' => $sessionId,
                    'tutor_id'         => $tutorId,
                    'status'           => 'confirmed',
                    'created_at'       => $now,'updated_at' => $now,
                ]);

                $enrollId = DB::table('enrollments')->insertGetId([
                    'student_id'         => $studentId,
                    'program_id'         => $programId,
                    'class_session_id'   => $sessionId,
                    'enrollment_date'    => $enrollDate->toDateString(),
                    'expiry_date'        => $enrollDate->copy()->addMonths($scenario['expiry_months'])->toDateString(),
                    'payment_method'     => $scenario['pay_method'],
                    'payment_channel'    => $scenario['pay_channel'],
                    'total_amount'       => $totalAmount,
                    // payment_status akan di-update setelah installment di-seed
                    'payment_status'     => $scenario['pay_method'] === 'full upfront' ? 'full' : 'partial',
                    'status'             => $scenario['status'],
                    // remaining_meetings akan di-update setelah attendance di-seed
                    'remaining_meetings' => $totalMeet,
                    'created_at'         => $now,'updated_at' => $now,
                ]);

                DB::table('enrollment_tutor')->insert([
                    'enrollment_id' => $enrollId,
                    'tutor_id'      => $tutorId,
                    'status'        => 'confirmed',
                    'created_at'    => $now,'updated_at' => $now,
                ]);

                $scheduleId = DB::table('schedules')->insertGetId([
                    'enrollment_id'    => $enrollId,
                    'class_session_id' => $sessionId,
                    'classroom_id'     => $classroomId,
                    'day'              => $slot['day'],
                    'time_block'       => $slot['time_block'],
                    'created_at'       => $now,'updated_at' => $now,
                ]);

                // Mark tutor availability occupied untuk slot ini
                DB::table('tutor_availability')
                    ->where('tutor_id',$tutorId)
                    ->where('day',$slot['day'])
                    ->where('time_block',$slot['time_block'])
                    ->update(['status'=>'occupied']);

                // Payment journal: semua ke deferred revenue dulu
                $payAmount = $scenario['pay_method'] === 'full upfront'
                    ? $totalAmount
                    : round($totalAmount / rand(2,4), -3);

                $cashCode = $scenario['pay_channel'] === 'bank' ? self::ACC_BANK : self::ACC_CASH;
                $this->journal(
                    $enrollDate->toDateString(),
                    "Student Payment - Enrollment #{$enrollId}",
                    "PAYMENT-ENROLL-{$enrollId}",
                    [
                        ['code'=>$cashCode,             'debit'=>$payAmount,'credit'=>0],
                        ['code'=>self::ACC_DEFERRED_REV,'debit'=>0,'credit'=>$payAmount],
                    ],
                    'payment',
                    $programId
                );

                $results[] = [
                    'id'            => $enrollId,
                    'program_id'    => $programId,
                    'student_id'    => $studentId,
                    'tutor_id'      => $tutorId,
                    'session_id'    => $sessionId,
                    'schedule_id'   => $scheduleId,
                    'classroom_id'  => $classroomId,
                    'day'           => $slot['day'],
                    'time_block'    => $slot['time_block'],
                    'total_meetings'=> $totalMeet,
                    'total_amount'  => $totalAmount,
                    'status'        => $scenario['status'],
                    'pay_method'    => $scenario['pay_method'],
                    'pay_channel'   => $scenario['pay_channel'],
                    'enroll_date'   => $enrollDate,
                    'type'          => 'private',
                ];
            }
        }

        return $results;
    }

    // ────────────────────────────────────────────────────────────────────
    // SEMI-PRIVATE SESSIONS
    // ────────────────────────────────────────────────────────────────────
    private function seedSemiPrivateSessions(array $studentIds): array
    {
        $now     = Carbon::now();
        $results = [];

        $sessions = [
            ['name'=>'ESP SP Morning A',   'program_id'=>17],
            ['name'=>'ESP SP Morning B',   'program_id'=>20],
            ['name'=>'IELTS SP Afternoon', 'program_id'=>26],
            ['name'=>'ESP SP Weekend',     'program_id'=>18],
            ['name'=>'IELTS SP Weekend',   'program_id'=>27],
            ['name'=>'Scholarship SP',     'program_id'=>23],
        ];

        foreach ($sessions as $sess) {
            $programId   = $sess['program_id'];
            $totalMeet   = self::PROGRAM_MEETINGS[$programId];
            $tutorId     = $this->pickTutorForProgram($programId);
            $slot        = $this->findSlotForTutor($tutorId) ?? ['day'=>'Saturday','time_block'=>'09:00-10:00'];
            $classroomId = $this->classroomFor('semi-private');

            $sessionId = DB::table('class_sessions')->insertGetId([
                'name'=>$sess['name'],'program_id'=>$programId,
                'class_type'=>'semi-private','status'=>'active',
                'created_at'=>$now,'updated_at'=>$now,
            ]);

            DB::table('class_session_tutor')->insert([
                'class_session_id'=>$sessionId,'tutor_id'=>$tutorId,
                'status'=>'confirmed','created_at'=>$now,'updated_at'=>$now,
            ]);

            // Mark tutor occupied
            DB::table('tutor_availability')
                ->where('tutor_id',$tutorId)->where('day',$slot['day'])->where('time_block',$slot['time_block'])
                ->update(['status'=>'occupied']);

            // 2-4 student, semua share slot & classroom
            shuffle($studentIds);
            $picked = array_slice($studentIds, 0, rand(2, 4));

            foreach ($picked as $studentId) {
                $scenario    = $this->pickScenario();
                $enrollDate  = Carbon::now()->subDays(rand(30, 180));
                $totalAmount = (float)(self::PROGRAM_PRICES[$programId] ?? 1000000);
                $payAmount   = $scenario['pay_method'] === 'full upfront'
                    ? $totalAmount
                    : round($totalAmount / rand(2,3), -3);
                $cashCode    = $scenario['pay_channel'] === 'bank' ? self::ACC_BANK : self::ACC_CASH;

                $enrollId = DB::table('enrollments')->insertGetId([
                    'student_id'         => $studentId,
                    'program_id'         => $programId,
                    'class_session_id'   => $sessionId,
                    'enrollment_date'    => $enrollDate->toDateString(),
                    'expiry_date'        => $enrollDate->copy()->addMonths($scenario['expiry_months'])->toDateString(),
                    'payment_method'     => $scenario['pay_method'],
                    'payment_channel'    => $scenario['pay_channel'],
                    'total_amount'       => $totalAmount,
                    'payment_status'     => $scenario['pay_method'] === 'full upfront' ? 'full' : 'partial',
                    'status'             => $scenario['status'],
                    'remaining_meetings' => $totalMeet,
                    'created_at'         => $now,'updated_at'=>$now,
                ]);

                DB::table('enrollment_tutor')->insert([
                    'enrollment_id'=>$enrollId,'tutor_id'=>$tutorId,
                    'status'=>'confirmed','created_at'=>$now,'updated_at'=>$now,
                ]);

                $scheduleId = DB::table('schedules')->insertGetId([
                    'enrollment_id'    => $enrollId,
                    'class_session_id' => $sessionId,
                    'classroom_id'     => $classroomId,
                    'day'              => $slot['day'],
                    'time_block'       => $slot['time_block'],
                    'created_at'       => $now,'updated_at'=>$now,
                ]);

                $this->journal(
                    $enrollDate->toDateString(),
                    "Student Payment - Enrollment #{$enrollId}",
                    "PAYMENT-ENROLL-{$enrollId}",
                    [
                        ['code'=>$cashCode,             'debit'=>$payAmount,'credit'=>0],
                        ['code'=>self::ACC_DEFERRED_REV,'debit'=>0,'credit'=>$payAmount],
                    ],
                    'payment', $programId
                );

                $results[] = [
                    'id'=>$enrollId,'program_id'=>$programId,'student_id'=>$studentId,
                    'tutor_id'=>$tutorId,'session_id'=>$sessionId,'schedule_id'=>$scheduleId,
                    'classroom_id'=>$classroomId,'day'=>$slot['day'],'time_block'=>$slot['time_block'],
                    'total_meetings'=>$totalMeet,'total_amount'=>$totalAmount,
                    'status'=>$scenario['status'],'pay_method'=>$scenario['pay_method'],
                    'pay_channel'=>$scenario['pay_channel'],'enroll_date'=>$enrollDate,
                    'type'=>'semi-private',
                ];
            }
        }

        return $results;
    }

    // ────────────────────────────────────────────────────────────────────
    // GROUP SESSIONS
    // ────────────────────────────────────────────────────────────────────
    private function seedGroupSessions(array $studentIds): array
    {
        $now     = Carbon::now();
        $results = [];

        $sessions = [
            ['name'=>'EDC Batch 12 - Morning',  'program_id'=>29,'classroom_id'=>2],
            ['name'=>'EDC Batch 13 - Afternoon','program_id'=>29,'classroom_id'=>3],
            ['name'=>'Speedy English Batch 5',  'program_id'=>30,'classroom_id'=>4],
            ['name'=>'Speedy English Batch 6',  'program_id'=>30,'classroom_id'=>8],
        ];

        foreach ($sessions as $sess) {
            $programId   = $sess['program_id'];
            $totalMeet   = self::PROGRAM_MEETINGS[$programId];
            $classroomId = $sess['classroom_id'];
            $tutorId     = $this->pickTutorForProgram($programId);
            $slot        = $this->findSlotForTutor($tutorId) ?? ['day'=>'Monday','time_block'=>'09:00-10:00'];

            $sessionId = DB::table('class_sessions')->insertGetId([
                'name'=>$sess['name'],'program_id'=>$programId,
                'class_type'=>'group','status'=>'active',
                'created_at'=>$now,'updated_at'=>$now,
            ]);

            DB::table('class_session_tutor')->insert([
                'class_session_id'=>$sessionId,'tutor_id'=>$tutorId,
                'status'=>'confirmed','created_at'=>$now,'updated_at'=>$now,
            ]);

            DB::table('tutor_availability')
                ->where('tutor_id',$tutorId)->where('day',$slot['day'])->where('time_block',$slot['time_block'])
                ->update(['status'=>'occupied']);

            shuffle($studentIds);
            $picked = array_slice($studentIds, 0, rand(6, 10));

            foreach ($picked as $studentId) {
                $scenario    = $this->pickScenario();
                $enrollDate  = Carbon::now()->subDays(rand(14, 120));
                $totalAmount = (float)(self::PROGRAM_PRICES[$programId] ?? 1000000);
                $payAmount   = $scenario['pay_method'] === 'full upfront'
                    ? $totalAmount
                    : round($totalAmount / rand(2,3), -3);
                $cashCode    = $scenario['pay_channel'] === 'bank' ? self::ACC_BANK : self::ACC_CASH;

                $enrollId = DB::table('enrollments')->insertGetId([
                    'student_id'         => $studentId,
                    'program_id'         => $programId,
                    'class_session_id'   => $sessionId,
                    'enrollment_date'    => $enrollDate->toDateString(),
                    'expiry_date'        => $enrollDate->copy()->addMonths($scenario['expiry_months'])->toDateString(),
                    'payment_method'     => $scenario['pay_method'],
                    'payment_channel'    => $scenario['pay_channel'],
                    'total_amount'       => $totalAmount,
                    'payment_status'     => $scenario['pay_method'] === 'full upfront' ? 'full' : 'partial',
                    'status'             => $scenario['status'],
                    'remaining_meetings' => $totalMeet,
                    'created_at'         => $now,'updated_at'=>$now,
                ]);

                DB::table('enrollment_tutor')->insert([
                    'enrollment_id'=>$enrollId,'tutor_id'=>$tutorId,
                    'status'=>'confirmed','created_at'=>$now,'updated_at'=>$now,
                ]);

                DB::table('schedules')->insertGetId([
                    'enrollment_id'    => $enrollId,
                    'class_session_id' => $sessionId,
                    'classroom_id'     => $classroomId,
                    'day'              => $slot['day'],
                    'time_block'       => $slot['time_block'],
                    'created_at'       => $now,'updated_at'=>$now,
                ]);

                $this->journal(
                    $enrollDate->toDateString(),
                    "Student Payment - Enrollment #{$enrollId}",
                    "PAYMENT-ENROLL-{$enrollId}",
                    [
                        ['code'=>$cashCode,             'debit'=>$payAmount,'credit'=>0],
                        ['code'=>self::ACC_DEFERRED_REV,'debit'=>0,'credit'=>$payAmount],
                    ],
                    'payment', $programId
                );

                $results[] = [
                    'id'=>$enrollId,'program_id'=>$programId,'student_id'=>$studentId,
                    'tutor_id'=>$tutorId,'session_id'=>$sessionId,
                    'classroom_id'=>$classroomId,'day'=>$slot['day'],'time_block'=>$slot['time_block'],
                    'total_meetings'=>$totalMeet,'total_amount'=>$totalAmount,
                    'status'=>$scenario['status'],'pay_method'=>$scenario['pay_method'],
                    'pay_channel'=>$scenario['pay_channel'],'enroll_date'=>$enrollDate,
                    'type'=>'group',
                ];
            }
        }

        return $results;
    }

    // ────────────────────────────────────────────────────────────────────
    // ATTENDANCE + REV-REC
    // Setiap attendance yang terjadi:
    //   1. Kurangi remaining_meetings di enrollment
    //   2. Journal rev-rec: debit deferred_rev, credit revenue_tuition_fees
    //   3. Journal tutor fee: debit expense_tutor, credit tutor_payable
    //      (skip journal + flag pending_rate=true jika tutor tidak punya rate)
    // ────────────────────────────────────────────────────────────────────
    private function seedAttendanceWithRevRec(array $enrollments): void
    {
        $now       = Carbon::now();
        $feedbacks = [
            'Progres bagus, pronunciation sudah meningkat.',
            'Perlu lebih banyak latihan speaking.',
            'Aktif dalam diskusi, vocabulary berkembang.',
            'Masih perlu perbaikan di grammar.',
            'Excellent! Sudah percaya diri berbicara.',
            null, null,
        ];

        // Kelompokkan by session untuk group/semi-private
        $bySession  = [];
        $privateArr = [];
        foreach ($enrollments as $enroll) {
            if (isset($enroll['session_id']) && in_array($enroll['type'], ['semi-private','group'])) {
                $bySession[$enroll['session_id']][] = $enroll;
            } else {
                $privateArr[] = $enroll;
            }
        }

        // ── Private ──────────────────────────────────────────────────
        foreach ($privateArr as $enroll) {
            if (in_array($enroll['status'], ['cancelled','waitlist'])) continue;

            // Tentukan berapa meeting yang sudah terjadi berdasarkan status
            $meetingsDone = $this->meetingsDoneForScenario($enroll);
            if ($meetingsDone <= 0) continue;

            $perMeetingRev = round($enroll['total_amount'] / $enroll['total_meetings'], 2);
            $date          = $enroll['enroll_date']->copy();

            for ($m = 0; $m < $meetingsDone; $m++) {
                $date->addDays(rand(3, 7));
                if ($date->gt($now)) break;

                $attId = DB::table('attendance')->insertGetId([
                    'class_session_id' => $enroll['session_id'],
                    'date'             => $date->toDateString(),
                    'time_block'       => $enroll['time_block'],
                    'classroom_id'     => $enroll['classroom_id'],
                    'marked_by'        => self::ADMIN_USER_ID,
                    'status'           => 'scheduled',
                    'notes'            => null,
                    'created_at'       => $now,'updated_at'=>$now,
                ]);

                DB::table('attendance_student')->insert([
                    'attendance_id' => $attId,
                    'enrollment_id' => $enroll['id'],
                    'is_present'    => rand(0,4) > 0 ? 1 : 0,
                    'notes'         => $feedbacks[array_rand($feedbacks)],
                    'created_at'    => $now,'updated_at'=>$now,
                ]);

                // Rev-rec: deferred_rev → revenue
                $this->journal(
                    $date->toDateString(),
                    "Revenue Recognition - Enrollment #{$enroll['id']} Meeting " . ($m+1),
                    "REV-REC-{$date->format('Ymd')}-{$enroll['id']}-{$m}",
                    [
                        ['code'=>self::ACC_DEFERRED_REV,    'debit'=>$perMeetingRev,'credit'=>0],
                        ['code'=>self::ACC_REVENUE_TUITION, 'debit'=>0,'credit'=>$perMeetingRev],
                    ],
                    'rev_rec', $enroll['program_id']
                );

                // Tutor fee
                $this->insertAttendanceTutor($attId, $enroll['tutor_id'], $enroll['program_id'], $date, $now);
            }

            // Update remaining_meetings setelah semua attendance di-seed
            $actualDone = DB::table('attendance_student')
                ->where('enrollment_id', $enroll['id'])->count();
            $remaining  = max(0, $enroll['total_meetings'] - $actualDone);

            // Kalau status expired/graduate, remaining harus 0
            if (in_array($enroll['status'], ['expired','graduate'])) $remaining = 0;

            DB::table('enrollments')->where('id',$enroll['id'])->update([
                'remaining_meetings' => $remaining,
            ]);
        }

        // ── Group / Semi-private ─────────────────────────────────────
        foreach ($bySession as $sessionId => $sessionEnrolls) {
            if (empty($sessionEnrolls)) continue;
            $first         = $sessionEnrolls[0];
            $meetingsDone  = $this->meetingsDoneForScenario($first);
            if ($meetingsDone <= 0) continue;

            $perMeetingRev = round($first['total_amount'] / $first['total_meetings'], 2);
            $date          = $first['enroll_date']->copy();

            for ($m = 0; $m < $meetingsDone; $m++) {
                $date->addDays(rand(5, 9));
                if ($date->gt($now)) break;

                $attId = DB::table('attendance')->insertGetId([
                    'class_session_id' => $sessionId,
                    'date'             => $date->toDateString(),
                    'time_block'       => $first['time_block'],
                    'classroom_id'     => $first['classroom_id'],
                    'marked_by'        => self::ADMIN_USER_ID,
                    'status'           => 'scheduled',
                    'notes'            => null,
                    'created_at'       => $now,'updated_at'=>$now,
                ]);

                foreach ($sessionEnrolls as $enroll) {
                    if (in_array($enroll['status'],['cancelled','waitlist'])) continue;
                    $dup = DB::table('attendance_student')
                        ->where('attendance_id',$attId)->where('enrollment_id',$enroll['id'])->exists();
                    if (!$dup) {
                        DB::table('attendance_student')->insert([
                            'attendance_id' => $attId,
                            'enrollment_id' => $enroll['id'],
                            'is_present'    => rand(0,4) > 0 ? 1 : 0,
                            'notes'         => $feedbacks[array_rand($feedbacks)],
                            'created_at'    => $now,'updated_at'=>$now,
                        ]);

                        // Rev-rec per student per meeting
                        $this->journal(
                            $date->toDateString(),
                            "Revenue Recognition - Enrollment #{$enroll['id']} Meeting " . ($m+1),
                            "REV-REC-{$date->format('Ymd')}-{$enroll['id']}-{$m}",
                            [
                                ['code'=>self::ACC_DEFERRED_REV,    'debit'=>$perMeetingRev,'credit'=>0],
                                ['code'=>self::ACC_REVENUE_TUITION, 'debit'=>0,'credit'=>$perMeetingRev],
                            ],
                            'rev_rec', $enroll['program_id']
                        );
                    }
                }

                // Tutor fee: 1x per attendance (bukan per student)
                $this->insertAttendanceTutor($attId, $first['tutor_id'], $first['program_id'], $date, $now);
            }

            // Update remaining_meetings untuk semua enrollment dalam session
            foreach ($sessionEnrolls as $enroll) {
                $actualDone = DB::table('attendance_student')
                    ->where('enrollment_id',$enroll['id'])->count();
                $remaining  = max(0, $enroll['total_meetings'] - $actualDone);
                if (in_array($enroll['status'],['expired','graduate'])) $remaining = 0;
                DB::table('enrollments')->where('id',$enroll['id'])
                    ->update(['remaining_meetings'=>$remaining]);
            }
        }
    }

    private function insertAttendanceTutor(int $attId, int $tutorId, int $programId, Carbon $date, Carbon $now): void
    {
        $rate      = $this->getTutorRateAmount($tutorId, $programId);
        $isPending = $rate === null; // tidak punya rate → pending
        $journalId = null;

        if (!$isPending) {
            // Ada rate → langsung buat journal
            $ref = 'TUTOR-ATT-' . $attId . '-' . $tutorId;
            $journalId = $this->journal(
                $date->toDateString(),
                "Tutor fee - attendance #{$attId}",
                $ref,
                [
                    ['code'=>self::ACC_EXPENSE_TUTOR, 'debit'=>$rate,'credit'=>0],
                    ['code'=>self::ACC_TUTOR_PAYABLE, 'debit'=>0,'credit'=>$rate],
                ],
                'tutor_accrual'
            );
        }

        DB::table('attendance_tutor')->insert([
            'attendance_id'  => $attId,
            'tutor_id'       => $tutorId,
            'payable_amount' => $rate ?? 0,
            'pending_rate'   => $isPending ? 1 : 0,
            'journal_id'     => $journalId,
            'paid_at'        => null, // akan di-set oleh seedPayrollRuns
            'created_at'     => $now,'updated_at'=>$now,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // INSTALLMENTS
    // Seed installment lalu update payment_status enrollment
    // sesuai logika markInstallmentPaid di controller
    // ────────────────────────────────────────────────────────────────────
    private function seedInstallments(array $enrollments): void
    {
        $now = Carbon::now();

        foreach ($enrollments as $enroll) {
            if ($enroll['pay_method'] !== 'installment') continue;

            $total   = $enroll['total_amount'];
            $terms   = rand(2, 4);
            $perTerm = round($total / $terms, -3);
            $base    = $enroll['enroll_date']->copy();
            $cashCode = $enroll['pay_channel'] === 'bank' ? self::ACC_BANK : self::ACC_CASH;

            $paidCount = 0;
            for ($t = 0; $t < $terms; $t++) {
                $due    = $base->copy()->addMonths($t);
                // Cicilan yang jatuh tempo di masa lalu → 80% sudah dibayar
                $isPaid = $due->lt($now) && rand(0, 4) > 0;

                $instId = DB::table('installments')->insertGetId([
                    'enrollment_id'   => $enroll['id'],
                    'amount'          => $perTerm,
                    'payment_channel' => $enroll['pay_channel'],
                    'due_date'        => $due->toDateString(),
                    'paid_at'         => $isPaid ? $due->copy()->addDays(rand(0,5))->toDateString() : null,
                    'created_at'      => $now,'updated_at'=>$now,
                ]);

                if ($isPaid) {
                    $paidCount++;
                    // Journal per installment yang sudah dibayar
                    // (sesuai markInstallmentPaid: cash/bank debit, deferred_rev credit)
                    $this->journal(
                        $due->toDateString(),
                        "Installment Payment - Enrollment #{$enroll['id']}",
                        "INSTALLMENT-{$instId}",
                        [
                            ['code'=>$cashCode,             'debit'=>$perTerm,'credit'=>0],
                            ['code'=>self::ACC_DEFERRED_REV,'debit'=>0,'credit'=>$perTerm],
                        ],
                        'payment', $enroll['program_id']
                    );
                }
            }

            // Update payment_status berdasarkan cicilan yang sudah dibayar
            $unpaid = DB::table('installments')
                ->where('enrollment_id',$enroll['id'])->whereNull('paid_at')->count();
            $payStatus = $unpaid === 0 ? 'full' : ($paidCount > 0 ? 'partial' : 'pending');
            DB::table('enrollments')->where('id',$enroll['id'])
                ->update(['payment_status'=>$payStatus]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // PAYROLL RUNS
    // Approved runs → set paid_at pada attendance_tutor di bulan itu
    // ────────────────────────────────────────────────────────────────────
    private function seedPayrollRuns(): void
    {
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $month    = Carbon::now()->startOfMonth()->subMonths($i);
            $status   = $i > 0 ? 'approved' : 'pending';
            $approver = $status === 'approved' ? self::ADMIN_USER_ID : null;

            if (!DB::table('payroll_runs')->where('month',$month->toDateString())->exists()) {
                DB::table('payroll_runs')->insert([
                    'month'=>$month->toDateString(),'status'=>$status,
                    'approved_by'=>$approver,'created_at'=>$now,'updated_at'=>$now,
                ]);
            }

            // Kalau approved → mark paid_at untuk semua attendance_tutor di bulan itu
            if ($status === 'approved') {
                $start = $month->copy()->startOfMonth()->toDateString();
                $end   = $month->copy()->endOfMonth()->toDateString();

                // Ambil attendance_tutor yang belum dibayar di bulan ini
                $attTutorIds = DB::table('attendance_tutor')
                    ->join('attendance','attendance_tutor.attendance_id','=','attendance.id')
                    ->whereBetween('attendance.date',[$start,$end])
                    ->where('attendance_tutor.pending_rate', 0)
                    ->whereNull('attendance_tutor.paid_at')
                    ->pluck('attendance_tutor.id');

                foreach ($attTutorIds as $id) {
                    DB::table('attendance_tutor')->where('id',$id)->update([
                        'paid_at'    => $month->copy()->endOfMonth()->toDateString(),
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // ROOM BOOKINGS
    // ────────────────────────────────────────────────────────────────────
    private function seedRoomBookings(array $enrollments): void
    {
        $now           = Carbon::now();
        $activeEnrolls = array_filter($enrollments, fn($e) => $e['status'] === 'active');

        foreach ($activeEnrolls as $enroll) {
            // regular_skip: 1-2 per enrollment aktif, tanggal di masa depan sesuai hari jadwal
            for ($s = 0; $s < rand(1,2); $s++) {
                $date = Carbon::now()->addDays(rand(7, 45));
                $dayMap = ['Senin'=>'Monday','Selasa'=>'Tuesday','Rabu'=>'Wednesday','Kamis'=>'Thursday','Jumat'=>'Friday','Sabtu'=>'Saturday','Minggu'=>'Sunday'];
                while ($date->englishDayOfWeek !== ($dayMap[$enroll['day']] ?? $enroll['day'])) $date->addDay();

                $slotTaken = DB::table('room_bookings')
                    ->where('classroom_id', $enroll['classroom_id'])
                    ->where('date', $date->toDateString())
                    ->where('time_block', $enroll['time_block'])
                    ->where('type', 'regular_skip')
                    ->exists();

                if (!$slotTaken) {
                    DB::table('room_bookings')->insert([
                        'classroom_id'  => $enroll['classroom_id'],
                        'schedule_id'   => $enroll['schedule_id'] ?? null,
                        'date'          => $date->toDateString(),
                        'time_block'    => $enroll['time_block'],
                        'type'          => 'regular_skip',
                        'enrollment_id' => $enroll['id'],
                        'tutor_id'      => $enroll['tutor_id'],
                        'notes'         => fake()->randomElement(['Student izin sakit','Libur nasional','Tutor berhalangan',null]),
                        'created_at'    => $now,'updated_at'=>$now,
                    ]);
                }
            }

            // temporary: reschedule ke slot lain dari availability tutor
            if (rand(0,1)) {
                $altSlot = $this->findSlotForTutor($enroll['tutor_id']);
                if ($altSlot) {
                    $altDate = Carbon::now()->addDays(rand(3,21))->toDateString();
                    $altTaken = DB::table('room_bookings')
                        ->where('classroom_id', $enroll['classroom_id'])
                        ->where('date', $altDate)
                        ->where('time_block', $altSlot['time_block'])
                        ->where('type', 'temporary')
                        ->exists();

                    if (!$altTaken) {
                        DB::table('room_bookings')->insert([
                            'classroom_id'  => $enroll['classroom_id'],
                            'schedule_id'   => $enroll['schedule_id'] ?? null,
                            'date'          => $altDate,
                            'time_block'    => $altSlot['time_block'],
                            'type'          => 'temporary',
                            'enrollment_id' => $enroll['id'],
                            'tutor_id'      => $enroll['tutor_id'],
                            'notes'         => 'Reschedule dari jadwal rutin.',
                            'created_at'    => $now,'updated_at'=>$now,
                        ]);
                    }
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // TRACKER ENTRIES
    // ────────────────────────────────────────────────────────────────────
    private function seedTrackerEntries(array $studentIds): void
    {
        $now = Carbon::now();
        foreach ($studentIds as $sid) {
            foreach (self::TRACKER_COLUMN_IDS as $colId) {
                if (!DB::table('tracker_entries')->where('student_id',$sid)->where('tracker_column_id',$colId)->exists()) {
                    DB::table('tracker_entries')->insert([
                        'student_id'=>$sid,'tracker_column_id'=>$colId,
                        'is_done'=>rand(0,1),'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // PRACTICES
    // ────────────────────────────────────────────────────────────────────
    private function seedPractices(array $studentIds): void
    {
        $now    = Carbon::now();
        $titles = [
            'Introduction & Self Presentation','Daily Conversation Practice',
            'IELTS Writing Task 1 - Bar Chart','IELTS Speaking Part 2 - Describe a Place',
            'Pronunciation: TH Sound','Vocabulary: Business English',
            'Grammar: Present Perfect vs Simple Past','Listening: BBC News Summary',
        ];
        $tutorUserIds = [2, 6, 7, 8];

        foreach ($tutorUserIds as $tutorUserId) {
            foreach (array_slice($titles, 0, rand(3,6)) as $idx => $title) {
                $practiceId = DB::table('practices')->insertGetId([
                    'tutor_id'           => $tutorUserId,
                    'title'              => $title,
                    'description'        => 'Latihan untuk meningkatkan kemampuan ' . strtolower($title) . '.',
                    'external_link'      => rand(0,1) ? 'https://drive.google.com/example-'.$idx : null,
                    'estimated_duration' => fake()->randomElement([15,20,30,45,60]),
                    'deadline'           => Carbon::now()->addDays(rand(7,30))->toDateString(),
                    'status'             => fake()->randomElement(['published','published','draft']),
                    'created_at'         => $now,'updated_at'=>$now,
                ]);

                shuffle($studentIds);
                foreach (array_slice($studentIds, 0, rand(3,6)) as $sid) {
                    $userRow = DB::table('students')->where('id',$sid)->first();
                    if (!$userRow) continue;

                    if (DB::table('practice_student')->where('practice_id',$practiceId)->where('student_id',$sid)->exists()) continue;

                    $cs = fake()->randomElement(['assigned','assigned','in_progress','completed']);
                    DB::table('practice_student')->insert([
                        'practice_id'       => $practiceId,
                        'student_id'        => $sid,
                        'completion_status' => $cs,
                        'completed_at'      => $cs === 'completed' ? Carbon::now()->subDays(rand(1,14)) : null,
                        'created_at'        => $now,'updated_at'=>$now,
                    ]);
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // SCENARIO PICKER
    // Generate kombinasi status + payment yang logis
    // ────────────────────────────────────────────────────────────────────
    // ────────────────────────────────────────────────────────────────────
    // FIXED ASSETS
    // ────────────────────────────────────────────────────────────────────
    private function seedFixedAssets(): void
    {
        $now = Carbon::now();
        $expenseAccountId     = DB::table('accounts')->where('code','5108')->value('id');
        $accumulatedAccountId = DB::table('accounts')->where('code','1006')->value('id');

        $assets = [
            ['name'=>'MacBook Pro 14"',          'category'=>'Elektronik',   'acquired_at'=>'2023-01-15', 'cost'=>25000000,  'salvage_value'=>5000000,  'useful_life'=>4],
            ['name'=>'MacBook Air M2',            'category'=>'Elektronik',   'acquired_at'=>'2023-06-01', 'cost'=>18000000,  'salvage_value'=>3000000,  'useful_life'=>4],
            ['name'=>'iPhone 14 Pro',             'category'=>'Elektronik',   'acquired_at'=>'2023-03-10', 'cost'=>16000000,  'salvage_value'=>2000000,  'useful_life'=>3],
            ['name'=>'Smart TV 55"',              'category'=>'Elektronik',   'acquired_at'=>'2022-08-20', 'cost'=>8000000,   'salvage_value'=>1000000,  'useful_life'=>5],
            ['name'=>'Proyektor Epson EB-X51',    'category'=>'Elektronik',   'acquired_at'=>'2022-11-01', 'cost'=>6500000,   'salvage_value'=>500000,   'useful_life'=>5],
            ['name'=>'AC Split 1.5 PK (Harvard)', 'category'=>'Perabot',      'acquired_at'=>'2021-04-01', 'cost'=>4500000,   'salvage_value'=>500000,   'useful_life'=>8],
            ['name'=>'AC Split 1.5 PK (Stanford)','category'=>'Perabot',      'acquired_at'=>'2021-04-01', 'cost'=>4500000,   'salvage_value'=>500000,   'useful_life'=>8],
            ['name'=>'Meja Kelas Set (6 unit)',   'category'=>'Perabot',      'acquired_at'=>'2021-01-10', 'cost'=>12000000,  'salvage_value'=>2000000,  'useful_life'=>8],
            ['name'=>'Kursi Kelas (30 unit)',     'category'=>'Perabot',      'acquired_at'=>'2021-01-10', 'cost'=>9000000,   'salvage_value'=>1000000,  'useful_life'=>8],
            ['name'=>'Whiteboard Magnetic 120x90','category'=>'Perabot',      'acquired_at'=>'2021-02-01', 'cost'=>1800000,   'salvage_value'=>200000,   'useful_life'=>5],
            ['name'=>'Renovasi Ruang Kelas',      'category'=>'Bangunan',     'acquired_at'=>'2020-07-01', 'cost'=>85000000,  'salvage_value'=>10000000, 'useful_life'=>20],
            ['name'=>'Instalasi CCTV (8 titik)',  'category'=>'Elektronik',   'acquired_at'=>'2022-03-15', 'cost'=>7500000,   'salvage_value'=>500000,   'useful_life'=>5],
            ['name'=>'Router WiFi Mesh System',   'category'=>'Elektronik',   'acquired_at'=>'2023-09-01', 'cost'=>3500000,   'salvage_value'=>300000,   'useful_life'=>3],
            ['name'=>'Sistem LMS (lisensi)',      'category'=>'Tak Berwujud', 'acquired_at'=>'2023-01-01', 'cost'=>12000000,  'salvage_value'=>0,        'useful_life'=>3],
        ];

        foreach ($assets as $asset) {
            if (!DB::table('fixed_assets')->where('name', $asset['name'])->exists()) {
                DB::table('fixed_assets')->insert(array_merge($asset, [
                    'depreciation_method'  => 'straight_line',
                    'expense_account_id'   => $expenseAccountId,
                    'accumulated_account_id'=> $accumulatedAccountId,
                    'is_active'            => 1,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]));
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // RAB (Rencana Anggaran Biaya)
    // ────────────────────────────────────────────────────────────────────
    private function seedRab(): void
    {
        $now  = Carbon::now();
        $year = (int) $now->format('Y');

        $rabs = [
            // Divisi: Operasional
            ['division'=>'Operasional', 'account_name'=>'Beban Sewa',                  'account_code'=>'5105', 'activity'=>'Sewa Gedung Tahunan',       'q1'=>15000000, 'q2'=>15000000, 'q3'=>15000000, 'q4'=>15000000],
            ['division'=>'Operasional', 'account_name'=>'Beban Perbaikan dan Perawatan','account_code'=>'5107', 'activity'=>'Perawatan AC & Elektronik', 'q1'=>2000000,  'q2'=>2000000,  'q3'=>2000000,  'q4'=>2000000],
            ['division'=>'Operasional', 'account_name'=>'Beban Operasional Umum',      'account_code'=>'5109', 'activity'=>'ATK dan Perlengkapan',       'q1'=>1500000,  'q2'=>1500000,  'q3'=>1500000,  'q4'=>1500000],
            ['division'=>'Operasional', 'account_name'=>'Beban Penyusutan',            'account_code'=>'5108', 'activity'=>'Penyusutan Aset Tetap',      'q1'=>3000000,  'q2'=>3000000,  'q3'=>3000000,  'q4'=>3000000],
            // Divisi: SDM
            ['division'=>'SDM',         'account_name'=>'Beban Gaji Tutor',            'account_code'=>'5001', 'activity'=>'Honor Tutor Bulanan',        'q1'=>45000000, 'q2'=>45000000, 'q3'=>50000000, 'q4'=>50000000],
            ['division'=>'SDM',         'account_name'=>'Beban Gaji Karyawan',         'account_code'=>'5002', 'activity'=>'Gaji Staff Admin',           'q1'=>18000000, 'q2'=>18000000, 'q3'=>18000000, 'q4'=>20000000],
            ['division'=>'SDM',         'account_name'=>'Beban Training & Development','account_code'=>'5004', 'activity'=>'Pelatihan Tutor',            'q1'=>3000000,  'q2'=>3000000,  'q3'=>5000000,  'q4'=>3000000],
            ['division'=>'SDM',         'account_name'=>'Beban Reward',                'account_code'=>'5003', 'activity'=>'Reward Tutor Berprestasi',   'q1'=>1000000,  'q2'=>1000000,  'q3'=>1000000,  'q4'=>2000000],
            // Divisi: Marketing
            ['division'=>'Marketing',   'account_name'=>'Beban Iklan dan Promosi',     'account_code'=>'5201', 'activity'=>'Iklan Digital (Meta/Google)', 'q1'=>5000000,  'q2'=>7000000,  'q3'=>7000000,  'q4'=>10000000],
            ['division'=>'Marketing',   'account_name'=>'Beban Sponsorship & Event',   'account_code'=>'5202', 'activity'=>'Event Promosi',              'q1'=>2000000,  'q2'=>3000000,  'q3'=>3000000,  'q4'=>5000000],
            ['division'=>'Marketing',   'account_name'=>'Beban Produksi Materi Promosi','account_code'=>'5203','activity'=>'Desain & Cetak Brosur',      'q1'=>1500000,  'q2'=>1500000,  'q3'=>1500000,  'q4'=>2000000],
            ['division'=>'Marketing',   'account_name'=>'Beban Transportasi',          'account_code'=>'5205', 'activity'=>'Transportasi Tim Marketing', 'q1'=>1000000,  'q2'=>1000000,  'q3'=>1000000,  'q4'=>1000000],
            // Divisi: Keuangan
            ['division'=>'Keuangan',    'account_name'=>'Beban Admin Bank & Transfer', 'account_code'=>'5301', 'activity'=>'Biaya Administrasi Bank',    'q1'=>500000,   'q2'=>500000,   'q3'=>500000,   'q4'=>500000],
            ['division'=>'Keuangan',    'account_name'=>'Beban Pajak',                 'account_code'=>'5302', 'activity'=>'PPh & PPN',                  'q1'=>2000000,  'q2'=>2000000,  'q3'=>2000000,  'q4'=>2000000],
            // Divisi: Akademik
            ['division'=>'Akademik',    'account_name'=>'Beban Modul & Materi Ajar',   'account_code'=>'5403', 'activity'=>'Cetak & Update Modul',       'q1'=>2000000,  'q2'=>2000000,  'q3'=>2000000,  'q4'=>2000000],
            ['division'=>'Akademik',    'account_name'=>'Beban Pengembangan Produk',   'account_code'=>'5401', 'activity'=>'Riset Program Baru',         'q1'=>3000000,  'q2'=>3000000,  'q3'=>5000000,  'q4'=>3000000],
        ];

        foreach ($rabs as $rab) {
            if (!DB::table('rabs')->where('year',$year)->where('division',$rab['division'])->where('account_name',$rab['account_name'])->exists()) {
                DB::table('rabs')->insert(array_merge($rab, ['year'=>$year, 'created_at'=>$now, 'updated_at'=>$now]));
            }
        }

        // Realisasi — berdasarkan journal yang sudah ada
        $realisasiItems = [
            ['division'=>'Operasional', 'account_name'=>'Beban Sewa',                   'account_code'=>'5105'],
            ['division'=>'Operasional', 'account_name'=>'Beban Perbaikan dan Perawatan', 'account_code'=>'5107'],
            ['division'=>'Operasional', 'account_name'=>'Beban Operasional Umum',       'account_code'=>'5109'],
            ['division'=>'SDM',         'account_name'=>'Beban Gaji Tutor',             'account_code'=>'5001'],
            ['division'=>'SDM',         'account_name'=>'Beban Gaji Karyawan',          'account_code'=>'5002'],
            ['division'=>'Marketing',   'account_name'=>'Beban Iklan dan Promosi',      'account_code'=>'5201'],
            ['division'=>'Keuangan',    'account_name'=>'Beban Admin Bank & Transfer',  'account_code'=>'5301'],
            ['division'=>'Akademik',    'account_name'=>'Beban Modul & Materi Ajar',    'account_code'=>'5403'],
        ];

        foreach ($realisasiItems as $item) {
            if (!DB::table('rab_realisasi')->where('year',$year)->where('division',$item['division'])->where('account_name',$item['account_name'])->exists()) {
                DB::table('rab_realisasi')->insert(array_merge($item, ['year'=>$year, 'created_at'=>$now, 'updated_at'=>$now]));
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // ADJUSTING JOURNALS (Depreciation)
    // ────────────────────────────────────────────────────────────────────
    private function seedAdjustingJournals(): void
    {
        $now                  = Carbon::now();
        $expenseAccountId     = DB::table('accounts')->where('code','5108')->value('id');
        $accumulatedAccountId = DB::table('accounts')->where('code','1006')->value('id');

        // Seed 6 bulan depreciation journals
        for ($i = 5; $i >= 0; $i--) {
            $period = Carbon::now()->startOfMonth()->subMonths($i);
            $ref    = 'DEP-' . $period->format('Ym');

            if (DB::table('adjusting_journals')->where('reference', $ref)->exists()) continue;

            // Total monthly depreciation dari semua fixed assets
            $assets        = DB::table('fixed_assets')->where('is_active', 1)->get();
            $totalDepreciation = 0;
            foreach ($assets as $asset) {
                $monthlyDep = round(($asset->cost - $asset->salvage_value) / ($asset->useful_life * 12), 2);
                $totalDepreciation += $monthlyDep;
            }

            if ($totalDepreciation <= 0) continue;

            $isPosted = $i > 0;

            $adjId = DB::table('adjusting_journals')->insertGetId([
                'period'       => $period->toDateString(),
                'reference'    => $ref,
                'description'  => 'Penyusutan Aset Tetap ' . $period->isoFormat('MMMM YYYY'),
                'type'         => 'depreciation',
                'status'       => $isPosted ? 'posted' : 'draft',
                'total_amount' => $totalDepreciation,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            // Kalau posted, buat jurnal di tabel journals juga
            $postedJournalId = null;
            if ($isPosted) {
                $postedJournalId = DB::table('journals')->insertGetId([
                    'date'         => $period->endOfMonth()->toDateString(),
                    'description'  => '[AJP] Penyusutan Aset Tetap ' . $period->isoFormat('MMMM YYYY'),
                    'reference'    => $ref,
                    'total_amount' => $totalDepreciation,
                    'type'         => 'adjusting',
                    'approved_by'  => self::ADMIN_USER_ID,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                DB::table('journal_items')->insert([
                    [
                        'journal_id' => $postedJournalId,
                        'account_id' => $expenseAccountId,
                        'debit'      => $totalDepreciation,
                        'credit'     => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'journal_id' => $postedJournalId,
                        'account_id' => $accumulatedAccountId,
                        'debit'      => 0,
                        'credit'     => $totalDepreciation,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);

                DB::table('adjusting_journals')->where('id', $adjId)->update([
                    'posted_journal_id' => $postedJournalId,
                ]);
            }

            DB::table('adjusting_journal_items')->insert([
                [
                    'adjusting_journal_id' => $adjId,
                    'account_id'           => $expenseAccountId,
                    'debit'                => $totalDepreciation,
                    'credit'               => 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ],
                [
                    'adjusting_journal_id' => $adjId,
                    'account_id'           => $accumulatedAccountId,
                    'debit'                => 0,
                    'credit'               => $totalDepreciation,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ],
            ]);
        }
    }

    private function pickScenario(): array
    {
        $payMethods  = ['full upfront','full upfront','installment'];
        $payChannels = ['cash','cash','bank','qris'];

        $scenarios = [
            // active: masih berjalan
            ['status'=>'active',    'expiry_months'=>4, 'pay_method'=>$payMethods[array_rand($payMethods)], 'pay_channel'=>$payChannels[array_rand($payChannels)]],
            ['status'=>'active',    'expiry_months'=>6, 'pay_method'=>$payMethods[array_rand($payMethods)], 'pay_channel'=>$payChannels[array_rand($payChannels)]],
            ['status'=>'active',    'expiry_months'=>3, 'pay_method'=>$payMethods[array_rand($payMethods)], 'pay_channel'=>$payChannels[array_rand($payChannels)]],
            // graduate: selesai (remaining=0, semua installment lunas)
            ['status'=>'graduate',  'expiry_months'=>4, 'pay_method'=>'full upfront',                       'pay_channel'=>$payChannels[array_rand($payChannels)]],
            ['status'=>'graduate',  'expiry_months'=>3, 'pay_method'=>'full upfront',                       'pay_channel'=>$payChannels[array_rand($payChannels)]],
            // expired: kedaluwarsa sebelum selesai
            ['status'=>'expired',   'expiry_months'=>2, 'pay_method'=>'full upfront',                       'pay_channel'=>$payChannels[array_rand($payChannels)]],
            // cancelled: dibatalkan
            ['status'=>'cancelled', 'expiry_months'=>1, 'pay_method'=>$payMethods[array_rand($payMethods)], 'pay_channel'=>$payChannels[array_rand($payChannels)]],
            // waitlist: belum ada slot/tutor
            ['status'=>'waitlist',  'expiry_months'=>3, 'pay_method'=>$payMethods[array_rand($payMethods)], 'pay_channel'=>$payChannels[array_rand($payChannels)]],
        ];

        // Distribusi: 50% active, sisanya spread
        $weights = [0,0,0,0,1,2,3,4,5,6,7]; // index ke $scenarios, weighted ke active
        $pick    = $weights[array_rand($weights)];
        return $scenarios[min($pick, count($scenarios)-1)];
    }

    /**
     * Hitung berapa meeting yang sudah terjadi berdasarkan status enrollment.
     * Ini yang bikin remaining_meetings konsisten dengan attendance.
     */
    private function meetingsDoneForScenario(array $enroll): int
    {
        $total = $enroll['total_meetings'];
        return match($enroll['status']) {
            'graduate'  => $total,                          // semua meeting selesai
            'expired'   => rand(1, max(1, (int)($total * 0.7))), // selesai sebagian
            'active'    => rand(1, max(1, $total - 1)),     // sedang berjalan
            'cancelled' => rand(0, min(2, $total)),         // mungkin sudah sempat 1-2 meeting
            default     => 0,                               // waitlist = belum mulai
        };
    }
}
