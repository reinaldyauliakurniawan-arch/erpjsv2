<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Enrollment;
use App\Enums\AccountCode;
use App\Services\AccountingService;
use Carbon\Carbon;

class CheckExpirations extends Command
{
    protected $signature = 'app:check-expirations';
    protected $description = 'Check for expiring enrollments and handle automatic revenue recognition for expired ones.';

    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        parent::__construct();
        $this->accountingService = $accountingService;
    }

    public function handle()
    {
        $today = Carbon::today();

        // 1. Warnings for H-7 and H-3
        $h7 = Enrollment::with(['student.user', 'program'])
            ->where('expiry_date', $today->copy()->addDays(7))
            ->where('status', 'active')
            ->get();

        $h3 = Enrollment::with(['student.user', 'program'])
            ->where('expiry_date', $today->copy()->addDays(3))
            ->where('status', 'active')
            ->get();

        foreach ($h7 as $e) {
            $this->info("H-7 Expiry Warning: Student {$e->student->user->name} ({$e->program->name}) expires on {$e->expiry_date}");
        }
        foreach ($h3 as $e) {
            $this->warn("H-3 Expiry Warning: Student {$e->student->user->name} ({$e->program->name}) expires on {$e->expiry_date}");
        }

        // 2. Auto-recognize revenue for expired enrollments with remaining meetings
        $expired = Enrollment::with(['student.user', 'program', 'installments'])
            ->where('expiry_date', '<', $today)
            ->where('status', 'active')
            ->where('remaining_meetings', '>', 0)
            ->get();

        foreach ($expired as $e) {
            // Gunakan paid_amount, bukan total_amount
            $paidAmount = $e->installments->whereNotNull('paid_at')->sum('amount');

            if ($paidAmount <= 0 || $e->program->total_meetings <= 0) {
                $e->update(['status' => 'expired']);
                $this->warn("Expired enrollment #{$e->id}: tidak ada pembayaran, status diupdate tanpa jurnal.");
                continue;
            }

            $perMeetingPrice   = $paidAmount / $e->program->total_meetings;
            $remainingDeferred = $e->remaining_meetings * $perMeetingPrice;

            if ($remainingDeferred > 0) {
                try {
                    $this->accountingService->createJournal(
                        $today->format('Y-m-d'),
                        "Auto Revenue Recognition on Expiry: Student {$e->student->user->name}",
                        "AUTO-EXPIRY-{$e->id}",
                        [
                            ['account_code' => AccountCode::DEFERRED_REVENUE->value,     'debit' => $remainingDeferred, 'credit' => 0],
                            ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $remainingDeferred],
                        ]
                    );
                    $e->update(['status' => 'expired', 'remaining_meetings' => 0]);
                    $this->info("Expired enrollment #{$e->id}: recognized sisa IDR " . number_format($remainingDeferred));
                } catch (\Exception $ex) {
                    $this->error("Failed to recognize revenue for enrollment #{$e->id}: " . $ex->getMessage());
                }
            }
        }
    }
}
