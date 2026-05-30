<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_student', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('completion_status');
            $table->text('reflection')->nullable()->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('practice_student', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'reflection']);
        });
    }
};
