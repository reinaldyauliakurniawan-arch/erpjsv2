<?php

namespace Database\Seeders\Traits;
use IlluminateSupportFacadesDB;
use CarbonCarbon;


trait HasAttendanceSeeders
{
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

}
