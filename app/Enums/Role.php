<?php

namespace App\Enums;

/**
 * Empat peran pengguna di ERP Just Speak. Ini daftar resminya — jangan
 * memakai teks lepas ('admin', 'cfo', ...) di tempat lain, pakai enum ini
 * atau helper di App\Models\User (isAdmin(), isCfo(), isBackOffice(), dst).
 *
 * Pemetaan area:
 *   - admin   -> /admin/*     operasional (enrollment, jadwal, absensi, aset)
 *   - cfo     -> /finance/*   keuangan (jurnal, laporan, payroll, akun)
 *   - tutor   -> /tutor/*
 *   - student -> /student/*
 *
 * admin dan cfo TIDAK sama: cfo peran tersendiri dengan areanya sendiri.
 * Satu-satunya tempat keduanya digabung adalah fitur back-office bersama
 * (kotak pencarian topbar) — lihat User::isBackOffice().
 */
enum Role: string
{
    case ADMIN = 'admin';
    case CFO = 'cfo';
    case TUTOR = 'tutor';
    case STUDENT = 'student';

    /** Semua nilai peran sebagai array string — untuk aturan validasi `in:`. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Label tampilan berbahasa Indonesia. */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::CFO => 'CFO (Keuangan)',
            self::TUTOR => 'Tutor',
            self::STUDENT => 'Siswa',
        };
    }

    /** Prefix rute + nama dashboard untuk peran ini. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::ADMIN => 'admin.dashboard',
            self::CFO => 'finance.index',
            self::TUTOR => 'tutor.dashboard',
            self::STUDENT => 'student.dashboard',
        };
    }

    /**
     * Staf back-office: admin + cfo. Keduanya berbagi kotak pencarian global
     * di topbar. Ini SATU-SATUNYA penggabungan admin/cfo yang disengaja.
     */
    public function isBackOffice(): bool
    {
        return $this === self::ADMIN || $this === self::CFO;
    }
}
