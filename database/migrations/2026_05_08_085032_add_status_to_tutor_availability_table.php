<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_availability', function (Blueprint $table) {
            $table->enum('status', ['available', 'not_available', 'occupied'])->default('available')->after('time_block');
        });
    }

    public function down(): void
    {
        Schema::table('tutor_availability', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
