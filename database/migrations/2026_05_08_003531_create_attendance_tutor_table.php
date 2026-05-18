<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_tutor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->onDelete('cascade');
            $table->foreignId('tutor_id')->constrained();
            $table->decimal('payable_amount', 15, 2);
            $table->foreignId('journal_id')->nullable()->constrained(); // Link to tutor payable journal
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_tutor');
    }
};
