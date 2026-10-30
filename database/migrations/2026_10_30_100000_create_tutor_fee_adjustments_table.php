<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian honor tutor di luar absensi per pertemuan: kelas B2B yang tidak
 * punya absen siswa, tunjangan prestasi, koreksi, dan saldo awal utang honor.
 * Ikut dibayar oleh payroll run sesuai periode 26–25 (kolom `date`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 20); // honor | tunjangan | saldo_awal
            $table->decimal('amount', 15, 2); // boleh negatif (koreksi kelebihan klaim)
            $table->string('description');
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tutor_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_fee_adjustments');
    }
};
