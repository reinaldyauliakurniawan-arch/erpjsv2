<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking sementara bisa ditautkan ke sebuah class session — dipakai untuk
 * "pindah ruang": pertemuan reguler di-skip di ruang aslinya, lalu dibuat
 * booking sementara di ruang lain untuk kelas yang sama. Dengan tautan ini,
 * dashboard siswa/tutor/admin bisa menampilkan ruang penggantinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->foreignId('class_session_id')->nullable()->after('schedule_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_session_id');
        });
    }
};
