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
        Schema::create('rabs', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('division');
            $table->string('account_name');
            $table->string('activity')->nullable();
            $table->bigInteger('q1')->default(0);
            $table->bigInteger('q2')->default(0);
            $table->bigInteger('q3')->default(0);
            $table->bigInteger('q4')->default(0);
            $table->bigInteger('total')->storedAs('q1 + q2 + q3 + q4');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rabs');
    }
};
