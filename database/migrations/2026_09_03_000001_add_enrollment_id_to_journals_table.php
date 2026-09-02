<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hubungkan jurnal ke enrollment sumbernya.
 *
 * Sampai sekarang jurnal hanya terkait ke enrollment lewat string `reference`
 * (mis. PAYMENT-ENROLL-12, INSTALLMENT-5) atau tidak sama sekali (jurnal
 * migrasi MIG-*). Itu rapuh dan tidak bisa dipakai untuk merekonsiliasi buku
 * besar terhadap sub-ledger revenue recognition.
 *
 * Kolom nullable ini menjadikan `journals` bisa di-query per enrollment,
 * sehingga EnrollmentLedgerService bisa menghitung "yang sudah diposting" dan
 * memposting jurnal penyesуaian selisih saat detail enrollment diedit.
 * Jurnal umum (non-enrollment) tetap null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->nullable()->after('reference')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrollment_id');
        });
    }
};
