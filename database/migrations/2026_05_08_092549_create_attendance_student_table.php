<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->onDelete('cascade');
            $table->foreignId('enrollment_id')->constrained()->onDelete('cascade');
            $table->boolean('is_present')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_student');
    }
};
