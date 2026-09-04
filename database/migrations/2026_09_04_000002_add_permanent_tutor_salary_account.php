<?php

use App\Enums\AccountCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Akun beban baru untuk gaji tutor tetap, terpisah dari 5001 "Beban Gaji
 * Tutor" (yang dipakai fee freelance per meeting) supaya di laba-rugi
 * gaji tetap kelihatan sebagai baris sendiri.
 *
 * Kode 5006 dipilih karena kosong di chart of accounts produksi
 * (5001..5005 terpakai, 5101 berikutnya) dan sudah diantisipasi di
 * ChartOfAccountsSeeder::$categoryMap sebagai 'operating'.
 *
 * updateOrInsert supaya aman di DB produksi maupun DB test yang fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['code' => AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value],
            [
                'name' => 'Beban Gaji Tutor Tetap',
                'type' => 'Expense',
                'cash_flow_category' => 'operating',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('accounts')
            ->where('code', AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value)
            ->delete();
    }
};
