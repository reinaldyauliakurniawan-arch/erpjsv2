<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Realisasi RAB per bulan per akun beban — dicatat manual oleh CFO.
 *
 * Selama masa transisi dari pencatatan cash basis ke accrual, jurnal di ERP
 * belum lengkap, jadi tracker RAB butuh sumber realisasi bulanan yang bisa
 * diisi tangan (persis seperti spreadsheet "Tracker RAB" milik CFO). Nanti
 * kalau jurnal sudah lengkap, angka ini bisa disinkronkan dari journal_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_monthly_actuals', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('account_code', 20);
            $table->unsignedTinyInteger('month'); // 1-12
            $table->bigInteger('amount')->default(0);
            $table->timestamps();

            $table->unique(['year', 'account_code', 'month'], 'rab_monthly_actual_unique');
        });

        // "RAB sebelum" — anggaran tahun lalu untuk pembanding (opsional).
        Schema::table('rabs', function (Blueprint $table) {
            $table->bigInteger('rab_prev')->default(0)->after('activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_monthly_actuals');
        Schema::table('rabs', function (Blueprint $table) {
            $table->dropColumn('rab_prev');
        });
    }
};
