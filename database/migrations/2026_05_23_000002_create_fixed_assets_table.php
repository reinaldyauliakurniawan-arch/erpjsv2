<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // Peralatan, Kendaraan, Bangunan, dll
            $table->date('acquired_at');
            $table->decimal('cost', 15, 2);           // Harga perolehan
            $table->decimal('salvage_value', 15, 2)->default(0); // Nilai sisa
            $table->unsignedInteger('useful_life');    // Umur ekonomis dalam bulan
            $table->string('depreciation_method')->default('straight_line'); // straight_line
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
