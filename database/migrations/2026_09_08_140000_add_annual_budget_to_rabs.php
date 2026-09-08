<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `rabs.annual_budget` — anggaran tahunan (kolom "Anggaran Tahun" di
 * spreadsheet CFO). Berbeda dari q1+q2+q3+q4 (yang merupakan rencana
 * pembagian bertahap per kuartal). Realisasi & persen serapan per akun
 * dihitung terhadap annual_budget; status per kuartal terhadap q1..q4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rabs', function (Blueprint $table) {
            $table->bigInteger('annual_budget')->default(0)->after('rab_prev');
        });

        // Default: samakan dengan q1+q2+q3+q4 untuk baris yang sudah ada.
        DB::table('rabs')->update(['annual_budget' => DB::raw('q1 + q2 + q3 + q4')]);
    }

    public function down(): void
    {
        Schema::table('rabs', function (Blueprint $table) {
            $table->dropColumn('annual_budget');
        });
    }
};
