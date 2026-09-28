<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan "tanggal mulai pembukuan" (lihat App\Support\LedgerStart).
 *
 * - `enrollments.opening_meetings_used`: jumlah pertemuan yang SUDAH terpakai
 *   sebelum tanggal mulai pembukuan tapi tidak punya baris absensi di ERP
 *   (riwayat dari sistem lama). Ikut dihitung sebagai pertemuan yang
 *   pendapatannya sudah diakui, supaya harga per pertemuan & sisa uang
 *   titipan siswa tetap benar.
 * - Akun 3003 "Ekuitas Saldo Awal": lawan jurnal saldo awal per enrollment
 *   (dan tempat CFO mencatat saldo kas awal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('opening_meetings_used')->default(0)->after('remaining_meetings');
        });

        if (! DB::table('accounts')->where('code', '3003')->exists()) {
            DB::table('accounts')->insert([
                'code' => '3003',
                'name' => 'Ekuitas Saldo Awal',
                'type' => 'Equity',
                'cash_flow_category' => 'financing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('opening_meetings_used');
        });
    }
};
