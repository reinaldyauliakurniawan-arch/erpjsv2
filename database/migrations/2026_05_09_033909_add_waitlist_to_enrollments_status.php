<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-database: SQLite doesn't support MODIFY COLUMN or ENUM.
        // On SQLite the column is already a plain string (no ENUM constraint),
        // so 'waitlist' values can be inserted without schema changes.
        // On MySQL we still issue the ALTER to tighten the ENUM check.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE enrollments MODIFY COLUMN status ENUM('active','waitlist','expired','graduate') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE enrollments MODIFY COLUMN status ENUM('active','expired','graduate') NOT NULL DEFAULT 'active'");
        }
    }
};
