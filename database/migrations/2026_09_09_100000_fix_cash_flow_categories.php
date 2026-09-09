<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaiki `accounts.cash_flow_category` yang KOSONG/NULL di produksi.
 *
 * Impor bagan akun kustom meninggalkan sejumlah akun kunci tanpa kategori arus
 * kas — antara lain Pendapatan Diterima Dimuka (2002), Utang Tutor (2003),
 * Akumulasi Penyusutan (1006), dan Beban Penyusutan (5108). Akibatnya Laporan
 * Arus Kas melewatkan mutasi akun-akun itu (pembayaran siswa yang menaikkan
 * Pendapatan Diterima Dimuka tidak terhitung sebagai arus kas masuk, dst).
 *
 * Aturan (standar):
 *   - Kas & Bank            → cash (dikecualikan dari perhitungan)
 *   - Ekuitas               → financing
 *   - Aset tetap + Akum. Penyusutan → investing
 *   - Beban Penyusutan/Amortisasi (non-kas) → investing
 *   - Sisanya (aset/liabilitas operasional, pendapatan, beban operasi) → operating
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->whereIn('code', ['1001', '1002'])->update(['cash_flow_category' => 'cash']);

        DB::table('accounts')->where('type', 'Equity')->update(['cash_flow_category' => 'financing']);

        DB::table('accounts')->where('type', 'Asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%penyusutan%')
                    ->orWhere('name', 'like', '%aset tetap%')
                    ->orWhere('name', 'like', '%aktiva tetap%')
                    ->orWhere('name', 'like', '%peralatan%')
                    ->orWhere('name', 'like', '%kendaraan%')
                    ->orWhere('name', 'like', '%gedung%')
                    ->orWhere('name', 'like', '%bangunan%')
                    ->orWhereIn('code', ['1005', '1006', '1101', '1102', '1103', '1104']);
            })
            ->update(['cash_flow_category' => 'investing']);

        DB::table('accounts')->where('type', 'Expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%penyusutan%')
                    ->orWhere('name', 'like', '%depresiasi%')
                    ->orWhere('name', 'like', '%amortisasi%')
                    ->orWhereIn('code', ['5108', '5110']);
            })
            ->update(['cash_flow_category' => 'investing']);

        DB::table('accounts')
            ->where(function ($q) {
                $q->whereNull('cash_flow_category')->orWhere('cash_flow_category', '');
            })
            ->whereIn('type', ['Asset', 'Liability', 'Revenue', 'Expense'])
            ->update(['cash_flow_category' => 'operating']);
    }

    public function down(): void
    {
        // Tidak dibalik — kategori arus kas yang benar tidak perlu dikembalikan
        // ke keadaan kosong.
    }
};
