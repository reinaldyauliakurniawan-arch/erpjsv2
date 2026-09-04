<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tutor tetap (salaried) support.
 *
 * `employment_type` = 'freelance' (default, dibayar per meeting sesuai
 * tutor_rates — perilaku lama) atau 'permanent' (gaji bulanan tetap,
 * tidak diakru per meeting).
 *
 * `monthly_salary` = nominal gaji bulanan untuk tutor permanent.
 *
 * `salaried_since` = tanggal mulai berlaku status permanent. Meeting
 * SEBELUM tanggal ini tetap diperlakukan sebagai freelance (tetap
 * mengakru fee per meeting) — penting untuk migrasi absensi historis
 * seorang tutor yang baru diangkat jadi tetap di tengah kuartal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->string('employment_type')->default('freelance')->after('status');
            $table->decimal('monthly_salary', 15, 2)->nullable()->after('employment_type');
            $table->date('salaried_since')->nullable()->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->dropColumn(['employment_type', 'monthly_salary', 'salaried_since']);
        });
    }
};
