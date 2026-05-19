<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->string('class_type')->default('private')->after('program_id');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('class_session_id')
                ->nullable()
                ->constrained('class_sessions')
                ->nullOnDelete()
                ->after('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropColumn('class_session_id');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('class_type');
        });
    }
};
