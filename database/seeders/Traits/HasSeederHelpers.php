<?php

namespace Database\Seeders\Traits;
use IlluminateSupportFacadesDB;
use CarbonCarbon;


trait HasSeederHelpers
{
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

}
