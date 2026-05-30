<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('enrollments', function (Blueprint $table) {
        $table->string('payment_channel')->nullable()->after('payment_method');
    });

    Schema::table('installments', function (Blueprint $table) {
        $table->string('payment_channel')->nullable()->after('amount');
    });
}

public function down(): void
{
    Schema::table('enrollments', function (Blueprint $table) {
        $table->dropColumn('payment_channel');
    });

    Schema::table('installments', function (Blueprint $table) {
        $table->dropColumn('payment_channel');
    });
}
};
