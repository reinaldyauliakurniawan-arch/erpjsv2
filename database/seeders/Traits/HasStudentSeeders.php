<?php

namespace Database\Seeders\Traits;
use IlluminateSupportFacadesDB;
use CarbonCarbon;
use IlluminateSupportFacadesHash;


trait HasStudentSeeders
{
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

}
