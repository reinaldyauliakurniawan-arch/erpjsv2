<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautkan tiap baris presensi tutor ke payroll run yang membayarnya.
 *
 * Sebelum ini, reverse payroll menandai ulang "belum dibayar" SEMUA presensi di
 * bulan itu berdasarkan tanggal — bukan hanya yang dibayar run tsb. Jadi kalau
 * ada pembayaran susulan (honor yang telat masuk setelah payroll bulan itu
 * di-approve), reverse salah satu run bisa merusak status pembayaran run lain.
 * Kolom ini membuat reverse presisi: hanya baris milik run itu yang dikembalikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->foreignId('payroll_run_id')->nullable()->after('journal_id')
                ->constrained('payroll_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_run_id');
        });
    }
};
