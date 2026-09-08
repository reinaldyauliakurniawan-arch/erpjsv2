<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tutors.persona` (S1, S2, dst) opsional: dibuat lewat menu Pengaturan / ganti
 * peran user tanpa mengisi persona. Sebelumnya kolomnya NOT NULL, jadi mengubah
 * peran user jadi "tutor" selalu gagal diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->string('persona')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->string('persona')->nullable(false)->change();
        });
    }
};
