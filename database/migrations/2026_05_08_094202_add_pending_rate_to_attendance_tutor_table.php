<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->boolean('pending_rate')->default(false)->after('payable_amount');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->dropColumn('pending_rate');
        });
    }
};
