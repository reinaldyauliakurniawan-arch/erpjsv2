<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akhir masa tutor tetap.
 *
 * `salaried_until` = hari terakhir tutor berstatus gaji tetap. NULL berarti
 * masih berlaku (open-ended). Kalau tutor dikembalikan ke freelance atau
 * dipecat, kolom ini diisi — BUKAN menghapus `salaried_since`/`monthly_salary`,
 * supaya perhitungan historis (absen & payroll bulan-bulan lampau saat dia
 * masih tetap) tetap benar dan tidak merusak dependency.
 *
 * Periode tetap = [salaried_since, salaried_until] inklusif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->date('salaried_until')->nullable()->after('salaried_since');
        });
    }

    public function down(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->dropColumn('salaried_until');
        });
    }
};
