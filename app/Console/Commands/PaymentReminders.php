<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Installment;
use Carbon\Carbon;

class PaymentReminders extends Command
{
    protected $signature = 'app:payment-reminders';
    protected $description = 'Send reminders for upcoming or overdue installments.';

    public function handle()
    {
        $today = Carbon::today();

        // Overdue
        $overdue = Installment::where('due_date', '<', $today)
            ->whereNull('paid_at')
            ->with('enrollment.student.user')
            ->get();

        foreach ($overdue as $i) {
            $this->error("OVERDUE: Student {$i->enrollment->student->user->name} owes IDR " . number_format($i->amount) . " since {$i->due_date}");
        }

        // Upcoming (Due in 3 days)
        $upcoming = Installment::where('due_date', $today->copy()->addDays(3))
            ->whereNull('paid_at')
            ->with('enrollment.student.user')
            ->get();

        foreach ($upcoming as $i) {
            $this->warn("UPCOMING: Student {$i->enrollment->student->user->name} due for IDR " . number_format($i->amount) . " on {$i->due_date}");
        }
    }
}
