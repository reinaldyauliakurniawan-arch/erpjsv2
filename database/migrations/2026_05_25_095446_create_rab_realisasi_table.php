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
        Schema::create('rab_realisasi', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('division');
            $table->string('account_name');
            $table->string('account_code')->nullable(); // link ke COA
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rab_realisasi');
    }
};
