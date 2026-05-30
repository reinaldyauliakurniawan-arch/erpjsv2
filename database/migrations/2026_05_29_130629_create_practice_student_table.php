<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('practice_student', function (Blueprint $table) {
    $table->id();
    $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->enum('completion_status', ['assigned', 'in_progress', 'completed'])->default('assigned');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_student');
    }
};
