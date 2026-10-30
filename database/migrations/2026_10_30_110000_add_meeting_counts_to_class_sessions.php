<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah pertemuan per kelas — untuk kelas tanpa enrollment (mis. B2B) yang
 * kontraknya per grup, bukan per paket siswa.
 *   total_meetings        : jumlah pertemuan kontrak kelas (NULL = ikut paket siswa)
 *   meetings_before_start : pertemuan yang sudah berjalan sebelum dicatat di ERP
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_meetings')->nullable()->after('status');
            $table->unsignedSmallInteger('meetings_before_start')->default(0)->after('total_meetings');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['total_meetings', 'meetings_before_start']);
        });
    }
};
