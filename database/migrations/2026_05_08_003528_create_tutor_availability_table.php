<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained()->onDelete('cascade');
            $table->string('day'); // Monday, etc.
            $table->string('time_block'); // 09.00-10.30, etc.
            $table->timestamps();
            $table->unique(['tutor_id', 'day', 'time_block']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_availability');
    }
};
