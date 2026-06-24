<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Initial data seeder — creates the foundational records that DatabaseSeeder
 * (and the application itself) depend on:
 *
 *   1. An admin user (id=1, role=admin) — required by DatabaseSeeder for
 *      journal `approved_by` references and for logging into the app.
 *   2. Programs (IDs 1-33) — DatabaseSeeder references these by ID directly
 *      (see PROGRAM_MEETINGS, PROGRAM_TYPE, PROGRAM_PRICES constants).
 *   3. Classrooms (IDs 1-9) — DatabaseSeeder references CLASSROOMS_SMALL
 *      = [5,6,7], CLASSROOMS_LARGE = [2,3,4,8], CLASSROOM_ONLINE = 9.
 *   4. Tutors (5-8 tutors with users) — DatabaseSeeder attaches enrollments
 *      and rates to existing tutors; without any tutors the seeder produces
 *      no enrollment data.
 *
 * Idempotent: each insert uses updateOrInsert or checks existence first,
 * so running this seeder multiple times is safe.
 *
 * Run via: php artisan db:seed --class=InitialDataSeeder
 * Or automatically via: php artisan db:seed (called by DatabaseSeeder)
 */
class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ──────────────────────────────────────────────────────────────
        // 1. Admin user (id=1)
        // ──────────────────────────────────────────────────────────────
        // DatabaseSeeder::ADMIN_USER_ID = 1, so the admin MUST be id=1.
        // We use a fixed email so the credentials are documented.
        $adminExists = DB::table('users')->where('id', 1)->exists();
        if (!$adminExists) {
            DB::table('users')->insert([
                'id'                => 1,
                'name'              => 'Admin Just Speak',
                'email'             => 'admin@justspeak.test',
                'email_verified_at' => $now,
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'phone'             => '081200000001',
                'remember_token'    => Str::random(10),
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $this->command->info('  Created admin user (id=1, email=admin@justspeak.test, password=password)');
        }

        // ──────────────────────────────────────────────────────────────
        // 2. CFO user (so finance routes are accessible after seed)
        // ──────────────────────────────────────────────────────────────
        $cfoExists = DB::table('users')->where('email', 'cfo@justspeak.test')->exists();
        if (!$cfoExists) {
            DB::table('users')->insert([
                'name'              => 'CFO Just Speak',
                'email'             => 'cfo@justspeak.test',
                'email_verified_at' => $now,
                'password'          => Hash::make('password'),
                'role'              => 'cfo',
                'phone'             => '081200000002',
                'remember_token'    => Str::random(10),
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $this->command->info('  Created CFO user (email=cfo@justspeak.test, password=password)');
        }

        // ──────────────────────────────────────────────────────────────
        // 3. Tutors (with users) — DatabaseSeeder attaches rates and
        //    enrollments to existing tutors. Without these, no enrollments
        //    would be created. We create 8 tutors so there's variety.
        // ──────────────────────────────────────────────────────────────
        $tutorCount = DB::table('tutors')->count();
        if ($tutorCount < 5) {
            $tutorNames = [
                'Sarah Johnson', 'Michael Chen', 'Emily Davis', 'David Wilson',
                'Jessica Brown', 'Daniel Martinez', 'Laura Anderson', 'James Taylor',
            ];
            $personas = ['Native Speaker', 'S1 TESOL', 'S2 Linguistics', 'CELTA Certified',
                         'Native Speaker', 'S1 English', 'TEFL Certified', 'S2 Education'];

            foreach ($tutorNames as $i => $name) {
                $email = 'tutor' . ($i + 1) . '@justspeak.test';
                if (DB::table('users')->where('email', $email)->exists()) {
                    continue;
                }

                $userId = DB::table('users')->insertGetId([
                    'name'              => $name,
                    'email'             => $email,
                    'email_verified_at' => $now,
                    'password'          => Hash::make('password'),
                    'role'              => 'tutor',
                    'phone'             => '0812000000' . ($i + 10),
                    'remember_token'    => Str::random(10),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);

                DB::table('tutors')->insert([
                    'user_id'    => $userId,
                    'persona'    => $personas[$i] ?? 'Tutor',
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->command->info('  Created ' . (count($tutorNames) - $tutorCount) . ' tutors');
        }

        // ──────────────────────────────────────────────────────────────
        // 4. Classrooms (IDs 1-9)
        //    DatabaseSeeder references specific IDs:
        //      - CLASSROOMS_SMALL = [5,6,7]   (capacity ~6, for private/semi-private)
        //      - CLASSROOMS_LARGE = [2,3,4,8] (capacity ~20, for group)
        //      - CLASSROOM_ONLINE = 9          (online classes)
        // ──────────────────────────────────────────────────────────────
        $classrooms = [
            ['id' => 1, 'name' => 'Studio 1',        'capacity' => 6,  'is_at_just_speak' => true],
            ['id' => 2, 'name' => 'Room A',          'capacity' => 20, 'is_at_just_speak' => true],
            ['id' => 3, 'name' => 'Room B',          'capacity' => 20, 'is_at_just_speak' => true],
            ['id' => 4, 'name' => 'Room C',          'capacity' => 18, 'is_at_just_speak' => true],
            ['id' => 5, 'name' => 'Private Room 1',  'capacity' => 4,  'is_at_just_speak' => true],
            ['id' => 6, 'name' => 'Private Room 2',  'capacity' => 4,  'is_at_just_speak' => true],
            ['id' => 7, 'name' => 'Private Room 3',  'capacity' => 6,  'is_at_just_speak' => true],
            ['id' => 8, 'name' => 'Hall',            'capacity' => 25, 'is_at_just_speak' => true],
            ['id' => 9, 'name' => 'Online',          'capacity' => 99, 'is_at_just_speak' => false],
        ];

        foreach ($classrooms as $c) {
            DB::table('classrooms')->updateOrInsert(
                ['id' => $c['id']],
                array_merge($c, ['created_at' => $now, 'updated_at' => $now])
            );
        }
        $this->command->info('  Ensured ' . count($classrooms) . ' classrooms exist (IDs 1-9)');

        // ──────────────────────────────────────────────────────────────
        // 5. Programs (IDs 1-33)
        //    These MUST match the constants in DatabaseSeeder:
        //      - PROGRAM_MEETINGS, PROGRAM_TYPE, PROGRAM_PRICES
        //    The names are realistic English course names grouped by type.
        // ──────────────────────────────────────────────────────────────
        $programs = $this->getProgramDefinitions();

        foreach ($programs as $p) {
            DB::table('programs')->updateOrInsert(
                ['id' => $p['id']],
                [
                    'name'           => $p['name'],
                    'type'           => $p['type'],
                    'price'          => $p['price'],
                    'total_meetings' => $p['total_meetings'],
                    'min_quota'      => $p['min_quota'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            );
        }
        $this->command->info('  Ensured ' . count($programs) . ' programs exist (IDs 1-33)');

        // ──────────────────────────────────────────────────────────────
        // 6. Tracker columns (IDs 4,5,6)
        //    DatabaseSeeder::TRACKER_COLUMN_IDS = [4,5,6], referenced in
        //    seedTrackerEntries(). Without these rows, the FK constraint on
        //    tracker_entries.tracker_column_id would fail.
        // ──────────────────────────────────────────────────────────────
        $trackerColumns = [
            ['id' => 4, 'name' => 'Placement Test', 'order' => 1],
            ['id' => 5, 'name' => 'Mid-Term Assessment', 'order' => 2],
            ['id' => 6, 'name' => 'Final Assessment', 'order' => 3],
        ];

        foreach ($trackerColumns as $col) {
            DB::table('tracker_columns')->updateOrInsert(
                ['id' => $col['id']],
                array_merge($col, ['created_at' => $now, 'updated_at' => $now])
            );
        }
        $this->command->info('  Ensured ' . count($trackerColumns) . ' tracker columns exist (IDs 4-6)');
    }

    /**
     * Program definitions — IDs, names, types, prices, total_meetings must
     * stay in sync with DatabaseSeeder's PROGRAM_MEETINGS, PROGRAM_TYPE,
     * and PROGRAM_PRICES constants.
     */
    private function getProgramDefinitions(): array
    {
        // Source of truth: DatabaseSeeder constants
        $meetings = DatabaseSeeder::PROGRAM_MEETINGS;
        $types    = DatabaseSeeder::PROGRAM_TYPE;
        $prices   = DatabaseSeeder::PROGRAM_PRICES;

        // Names — realistic English course names. The 'Online' suffix on
        // IDs 14-15 is checked by DatabaseSeeder (str_contains 'online')
        // to route those enrollments to the online classroom.
        $names = [
            1  => 'Private Conversation Beginner',
            2  => 'Private Conversation Intermediate',
            3  => 'Private Conversation Advanced',
            4  => 'Private Grammar Beginner',
            5  => 'Private Grammar Intermediate',
            6  => 'Private Grammar Advanced',
            7  => 'Private IELTS Prep Basic',
            8  => 'Private IELTS Prep Standard',
            9  => 'Private IELTS Prep Intensive',
            10 => 'Private TOEFL Prep',
            11 => 'Private Business English',
            12 => 'Private Academic Writing',
            13 => 'Private Speaking Pro',
            14 => 'Private Online Course A',
            15 => 'Private Online Course B',
            16 => 'Trial Session',
            17 => 'ESP Semi-Private Morning',
            18 => 'ESP Semi-Private Weekend',
            19 => 'Semi-Private Beginner',
            20 => 'ESP Semi-Private Morning B',
            21 => 'Semi-Private Intermediate',
            22 => 'Semi-Private Grammar',
            23 => 'Scholarship Prep Semi-Private',
            24 => 'Semi-Private IELTS',
            25 => 'Semi-Private Speaking',
            26 => 'IELTS Semi-Private Afternoon',
            27 => 'IELTS Semi-Private Weekend',
            28 => 'Semi-Private Advanced',
            29 => 'EDC Group Class',
            30 => 'Speedy English Group',
            31 => 'Group Trial',
            32 => 'Group Workshop',
            33 => 'Group Conversation',
        ];

        $result = [];
        for ($i = 1; $i <= 33; $i++) {
            $type = $types[$i];
            $result[] = [
                'id'             => $i,
                'name'           => $names[$i] ?? ('Program ' . $i),
                'type'           => $type,
                'price'          => $prices[$i] ?? 1000000,
                'total_meetings' => $meetings[$i] ?? 10,
                'min_quota'      => $type === 'private' ? 1 : ($type === 'semi-private' ? 2 : 3),
            ];
        }

        return $result;
    }
}
