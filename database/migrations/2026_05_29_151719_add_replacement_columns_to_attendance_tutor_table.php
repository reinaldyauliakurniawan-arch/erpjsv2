<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->boolean('is_replacement')->default(false)->after('journal_id');
            $table->foreignId('replaced_tutor_id')->nullable()->constrained('tutors')->nullOnDelete()->after('is_replacement');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->dropForeign(['replaced_tutor_id']);
            $table->dropColumn(['is_replacement', 'replaced_tutor_id']);
        });
    }
};
