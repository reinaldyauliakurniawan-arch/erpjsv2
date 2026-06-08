<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->dropForeign(['enrollment_id']);
        $table->dropColumn('enrollment_id');
        $table->foreignId('class_session_id')->nullable()->constrained()->onDelete('cascade');
    });
}

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropColumn('class_session_id');
            $table->foreignId('enrollment_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('feedback')->nullable();
        });
    }
};
