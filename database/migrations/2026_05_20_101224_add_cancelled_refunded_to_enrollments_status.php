<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Blueprint tidak support alter ENUM di semua driver,
        // pakai raw SQL agar aman
        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('active','waitlist','expired','graduate','cancelled','refunded')
            NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        // Hapus dulu row yang pakai status baru sebelum rollback,
        // atau down() ini akan error kalau ada data dengan status tsb
        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('active','waitlist','expired','graduate')
            NOT NULL DEFAULT 'active'
        ");
    }
};
