<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\RefundRequest;
use App\Enums\AccountCode;
use App\Enums\ClassType;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DomainException;

class RefundService
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Admin request refund → buat RefundRequest status pending, belum ada jurnal.
     */
    public function requestRefund(int $enrollmentId, int $requestedBy): RefundRequest
    {
        $enrollment = Enrollment::with(['program', 'student.user'])->findOrFail($enrollmentId);

        // Cek apakah sudah ada request pending
        $existing = RefundRequest::where('enrollment_id', $enrollmentId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            throw new DomainException("Refund request untuk enrollment ini sudah ada (status: {$existing->status}).");
        }

        $refundAmount = 0;
        $adminFee     = 0;

        if ($enrollment->program->type === ClassType::GROUP->value) {
            $refundAmount = $this->calculateGroupRefund($enrollment);
        } else {
            $refundAmount = $this->calculatePrivateRefund($enrollment, $adminFee);
        }

        if ($refundAmount <= 0) {
            throw new DomainException("No refund available based on the rules.");
        }

        return RefundRequest::create([
            'enrollment_id' => $enrollmentId,
            'amount'        => $refundAmount,
            'status'        => 'pending',
            'requested_by'  => $requestedBy,
        ]);
    }

    /**
     * CFO approve refund → buat jurnal + update enrollment.
     */
    public function approveRefund(int $refundRequestId, int $approvedBy): RefundRequest
    {
        $refundRequest = RefundRequest::with(['enrollment.program', 'enrollment.student.user'])
            ->findOrFail($refundRequestId);

        if ($refundRequest->status === 'approved') {
            throw new DomainException("Refund request ini sudah di-approve sebelumnya.");
        }

        $enrollment   = $refundRequest->enrollment;
        $refundAmount = $refundRequest->amount;
        $adminFee     = 0;

        // Recalculate adminFee untuk private (tidak disimpan di tabel, hitung ulang)
        if ($enrollment->program->type !== ClassType::GROUP->value) {
            $meetingsDone = $enrollment->program->total_meetings - $enrollment->remaining_meetings;
            if ($meetingsDone === 0) {
                $adminFee = $enrollment->total_amount * 0.1;
            }
        }

        $reference = "REFUND-{$refundRequest->id}";

        return DB::transaction(function () use ($enrollment, $refundRequest, $refundAmount, $adminFee, $approvedBy, $reference) {
            $date                   = now()->toDateString();
            $perMeetingPrice        = $enrollment->total_amount / $enrollment->program->total_meetings;
            $currentDeferredRevenue = $enrollment->remaining_meetings * $perMeetingPrice;

            // Selisih deferred yang tidak dikembalikan → langsung diakui sebagai revenue
            $recognizedNow = $currentDeferredRevenue - $refundAmount - $adminFee;

            $items = [
                ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => $currentDeferredRevenue, 'credit' => 0],
                ['account_code' => AccountCode::CASH_BANK->value,        'debit' => 0, 'credit' => $refundAmount],
            ];

            if ($adminFee > 0) {
                $items[] = ['account_code' => AccountCode::REVENUE_ADMIN_FEE->value,    'debit' => 0, 'credit' => $adminFee];
            }

            if ($recognizedNow > 0.001) {
                $items[] = ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $recognizedNow];
            }

            $this->accountingService->createJournal(
                $date,
                "Refund for Student: {$enrollment->student->user->name}",
                $reference,
                $items
            );

            $enrollment->update(['status' => 'refunded', 'remaining_meetings' => 0]);

            $refundRequest->update([
                'status'      => 'approved',
                'approved_by' => $approvedBy,
            ]);

            return $refundRequest;
        });
    }

    protected function calculateGroupRefund(Enrollment $enrollment): float
    {
        $meetingsDone = $enrollment->program->total_meetings - $enrollment->remaining_meetings;

        if ($meetingsDone === 0) {
            return $enrollment->total_amount;
        }

        if ($meetingsDone > 12) {
            return 0;
        }

        $monthsDone   = ceil($meetingsDone / 4);
        $costPerMonth = $enrollment->total_amount / ceil($enrollment->program->total_meetings / 4);
        $totalCost    = max($monthsDone * $costPerMonth, 400000);
        $refund       = $enrollment->total_amount - $totalCost;

        return $refund > 0 ? $refund : 0;
    }

    protected function calculatePrivateRefund(Enrollment $enrollment, float &$adminFee): float
    {
        $meetingsDone = $enrollment->program->total_meetings - $enrollment->remaining_meetings;

        if ($meetingsDone === 0) {
            $adminFee = $enrollment->total_amount * 0.1;
            return $enrollment->total_amount - $adminFee;
        }

        return 0;
    }
}
