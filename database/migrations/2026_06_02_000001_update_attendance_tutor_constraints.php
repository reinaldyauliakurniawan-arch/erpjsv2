<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->boolean('is_team_teaching')->default(false)->after('is_replacement');
            $table->unique(['attendance_id', 'tutor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->dropUnique(['attendance_id', 'tutor_id']);
            $table->dropColumn('is_team_teaching');
        });
    }
};
