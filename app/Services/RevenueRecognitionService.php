<?php

namespace App\Services;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * GAP #14 fix.
 *
 * Sub-ledger untuk revenue recognition per-enrollment. Semua angka di sini
 * DIHITUNG ULANG setiap kali dari data operasional (installments +
 * attendance_student), bukan disimpan sebagai state terpisah. Ini sengaja:
 * journal_items tidak punya kolom enrollment_id (hanya program_id, yang
 * general per-program, bukan per-enrollment), dan kita menghindari 2
 * pendekatan yang sama-sama buruk:
 *   - parsing journals.reference (string) untuk cari enrollment_id -> rapuh,
 *     silently salah kalau ada developer lain lupa ikuti format reference.
 *   - migration kolom baru -> ditolak oleh user untuk fix ini.
 * installments.enrollment_id dan attendance_student.enrollment_id sudah
 * native (foreign key asli, bukan string), jadi keduanya cukup jadi source
 * of truth untuk posisi piutang/deferred revenue tanpa risiko rapuh di atas.
 *
 * Definisi:
 *   - revenuePerMeeting = total_amount kontrak / total_meetings (FIXED,
 *     tidak lagi ikut paidAmount yang fluktuatif — ini core dari GAP #14).
 *   - totalPaid = akumulasi uang yang benar-benar diterima dari siswa
 *     (installment yang paid_at IS NOT NULL, atau total_amount penuh untuk
 *     full-upfront).
 *   - totalRevenueRecognized = jumlah meeting yang sudah diproses attendance
 *     (baris di attendance_student) dikali revenuePerMeeting.
 *   - Saldo Deferred Revenue tersedia = max(0, totalPaid - totalRevenueRecognized)
 *     -> uang sudah masuk lebih besar dari revenue yang wajib diakui sejauh
 *        ini (siswa bayar duluan, kasus normal).
 *   - Saldo Piutang outstanding = max(0, totalRevenueRecognized - totalPaid)
 *     -> revenue yang wajib diakui sejauh ini lebih besar dari uang yang
 *        masuk (siswa nunggak, revenue tetap diakui di depan / accrual).
 */
class RevenueRecognitionService
{
    // KONTRAK CONCURRENCY: semua method di service ini HARUS dipanggil
    // setelah caller sudah lockForUpdate() row Enrollment yang bersangkutan,
    // di dalam DB::transaction yang sama. Method di sini sendiri tidak
    // lock apa-apa (query installments/attendance_student polos) — mereka
    // mengandalkan lock di parent Enrollment row untuk menjamin request
    // kedua untuk enrollment yang sama diblok sampai transaction pertama
    // commit, sehingga data yang dibaca di sini selalu konsisten.

    /**
     * revenue per meeting = total_amount kontrak / total_meetings. FIXED,
     * tidak berubah mengikuti progress pembayaran cicilan.
     */
    public function revenuePerMeeting(Enrollment $enrollment): string
    {
        $totalMeetings = $enrollment->program->total_meetings;

        return $totalMeetings > 0
            ? bcdiv((string) $enrollment->total_amount, (string) $totalMeetings, 2)
            : '0';
    }

    /**
     * Total uang yang benar-benar sudah diterima dari siswa untuk enrollment ini.
     */
    public function totalPaid(Enrollment $enrollment): string
    {
        if ($enrollment->payment_method === 'full upfront') {
            return (string) $enrollment->total_amount;
        }

        $sum = $enrollment->installments()->whereNotNull('paid_at')->sum('amount');

        return (string) $sum;
    }

    /**
     * Jumlah meeting yang sudah diproses (tercatat di attendance_student)
     * untuk enrollment ini, TERLEPAS dari is_present (konsisten dengan
     * feature yang sudah dikonfirmasi: siswa absen tetap diakui revenue).
     */
    public function meetingsRecognizedSoFar(Enrollment $enrollment): int
    {
        return DB::table('attendance_student')->where('enrollment_id', $enrollment->id)->count();
    }

    /**
     * Total revenue yang WAJIB sudah diakui sejauh ini (berdasarkan jumlah
     * meeting yang sudah diproses attendance, dikali revenue per meeting).
     *
     * @param int|null $meetingsOverride Kalau diisi, dipakai alih-alih query
     *   ulang ke attendance_student. Wajib dipakai oleh caller yang sudah
     *   attach() baris attendance_student untuk meeting yang SEDANG diproses
     *   SEBELUM memanggil method ini — supaya tidak off-by-one (ikut
     *   menghitung meeting yang sedang diproses).
     */
    public function totalRevenueRecognizedSoFar(Enrollment $enrollment, ?int $meetingsOverride = null): string
    {
        $meetings = $meetingsOverride ?? $this->meetingsRecognizedSoFar($enrollment);

        return bcmul((string) $meetings, $this->revenuePerMeeting($enrollment), 2);
    }

    /**
     * Saldo Deferred Revenue yang tersedia (uang masuk yang belum "dipakai"
     * untuk cover revenue recognition). Tidak pernah negatif.
     */
    public function availableDeferredRevenue(Enrollment $enrollment, ?int $meetingsOverride = null): string
    {
        $diff = bcsub($this->totalPaid($enrollment), $this->totalRevenueRecognizedSoFar($enrollment, $meetingsOverride), 2);

        return bccomp($diff, '0', 2) > 0 ? $diff : '0';
    }

    /**
     * Saldo Piutang (Accounts Receivable) yang outstanding (revenue yang
     * sudah wajib diakui tapi uangnya belum diterima). Tidak pernah negatif.
     */
    public function outstandingReceivable(Enrollment $enrollment, ?int $meetingsOverride = null): string
    {
        $diff = bcsub($this->totalRevenueRecognizedSoFar($enrollment, $meetingsOverride), $this->totalPaid($enrollment), 2);

        return bccomp($diff, '0', 2) > 0 ? $diff : '0';
    }

    /**
     * Split porsi revenue SATU meeting yang baru saja terjadi antara
     * Deferred Revenue (sejauh saldo tersedia) dan Piutang (sisanya).
     * Dipanggil SEBELUM baris attendance_student untuk meeting ini dibuat
     * (jadi meetingsRecognizedSoFar() masih menghitung N-1 meeting
     * sebelumnya, belum termasuk meeting yang sedang diproses).
     *
     * @param int|null $meetingsOverride Lihat totalRevenueRecognizedSoFar().
     *   Isi dengan jumlah meeting SEBELUM meeting yang sedang diproses ini,
     *   kalau caller sudah attach() baris attendance_student-nya duluan.
     * @return array{fromDeferredRevenue: string, fromReceivable: string, revenueThisMeeting: string}
     */
    public function splitForNextMeeting(Enrollment $enrollment, ?int $meetingsOverride = null): array
    {
        $revenueThisMeeting = $this->revenuePerMeeting($enrollment);
        $availableDR        = $this->availableDeferredRevenue($enrollment, $meetingsOverride);

        $fromDR = bccomp($availableDR, $revenueThisMeeting, 2) >= 0
            ? $revenueThisMeeting
            : $availableDR;

        $fromReceivable = bcsub($revenueThisMeeting, $fromDR, 2);

        return [
            'fromDeferredRevenue' => $fromDR,
            'fromReceivable'      => $fromReceivable,
            'revenueThisMeeting'  => $revenueThisMeeting,
        ];
    }

    /**
     * Split satu pembayaran yang baru masuk antara pelunasan Piutang
     * outstanding (prioritas pertama) dan sisanya ke Deferred Revenue untuk
     * meeting-meeting masa depan. Dipanggil SEBELUM installment/payment ini
     * di-mark paid_at (jadi outstandingReceivable() & totalPaid() masih
     * dihitung tanpa payment yang sedang diproses).
     *
     * @return array{toReceivable: string, toDeferredRevenue: string}
     */
    public function splitForNewPayment(Enrollment $enrollment, string $paymentAmount): array
    {
        $outstandingReceivable = $this->outstandingReceivable($enrollment);

        $toReceivable = bccomp($outstandingReceivable, $paymentAmount, 2) >= 0
            ? $paymentAmount
            : $outstandingReceivable;

        $toDeferredRevenue = bcsub($paymentAmount, $toReceivable, 2);

        return [
            'toReceivable'      => $toReceivable,
            'toDeferredRevenue' => $toDeferredRevenue,
        ];
    }
}
