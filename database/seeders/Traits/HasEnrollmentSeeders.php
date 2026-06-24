<?php

namespace Database\Seeders\Traits;
use IlluminateSupportFacadesDB;
use CarbonCarbon;


trait HasEnrollmentSeeders
{
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

}
