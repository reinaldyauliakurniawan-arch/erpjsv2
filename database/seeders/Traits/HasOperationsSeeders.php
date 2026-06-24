<?php

namespace Database\Seeders\Traits;
use IlluminateSupportFacadesDB;
use CarbonCarbon;


trait HasOperationsSeeders
{
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

}
