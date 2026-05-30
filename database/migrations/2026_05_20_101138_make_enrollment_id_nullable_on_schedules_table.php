<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Drop foreign key dulu sebelum ubah kolom
            $table->dropForeign(['enrollment_id']);

            $table->unsignedBigInteger('enrollment_id')->nullable()->change();

            // Re-attach foreign key, dengan nullOnDelete karena sekarang nullable
            $table->foreign('enrollment_id')
                  ->references('id')
                  ->on('enrollments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);

            $table->unsignedBigInteger('enrollment_id')->nullable(false)->change();

            $table->foreign('enrollment_id')
                  ->references('id')
                  ->on('enrollments')
                  ->cascadeOnDelete();
        });
    }
};
