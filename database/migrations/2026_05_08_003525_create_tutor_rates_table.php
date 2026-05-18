<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->decimal('rate', 15, 2);
            $table->timestamps();
            $table->unique(['tutor_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_rates');
    }
};
