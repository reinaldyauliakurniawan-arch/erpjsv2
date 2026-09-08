<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis ruangan: physical / online / offsite (lihat App\Enums\ClassroomKind).
 *
 * Sebelumnya cuma ada boolean `is_at_just_speak` yang menggabungkan "online"
 * dan "di luar Just Speak (B2B)". Keduanya sama-sama TIDAK dihitung untuk
 * okupansi ruangan, tapi maknanya beda dan B2B akan makin sering dipakai.
 *
 * `is_at_just_speak` tetap ada (dipakai template import/export) dan otomatis
 * diturunkan dari `kind` lewat Classroom::saving().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('kind')->default('physical')->after('capacity');
        });

        // Backfill:
        //  - nama mengandung "online"          -> online (sekaligus koreksi data
        //    lama yang keliru is_at_just_speak = true)
        //  - selain itu, is_at_just_speak = 0  -> offsite
        //  - sisanya                           -> physical
        DB::table('classrooms')->whereRaw('LOWER(name) LIKE ?', ['%online%'])->update(['kind' => 'online']);
        DB::table('classrooms')->where('is_at_just_speak', false)->where('kind', 'physical')->update(['kind' => 'offsite']);

        // Samakan is_at_just_speak dengan kind.
        DB::table('classrooms')->where('kind', 'physical')->update(['is_at_just_speak' => true]);
        DB::table('classrooms')->where('kind', '!=', 'physical')->update(['is_at_just_speak' => false]);
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
