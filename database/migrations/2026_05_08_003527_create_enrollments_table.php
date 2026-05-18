<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained();
            $table->date('enrollment_date');
            $table->date('expiry_date');
            $table->string('payment_method'); // full upfront, installment, ala carte
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_status')->default('pending'); // pending, partial, full
            $table->string('status')->default('active'); // active, expired, graduate
            $table->integer('remaining_meetings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
