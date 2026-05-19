<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Schedule::class);
            $table->dropColumn('schedule_id');
        });
    }
};
