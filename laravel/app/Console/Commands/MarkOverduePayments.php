<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class MarkOverduePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:mark-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark pending payments with a past payment date as overdue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = Payment::where('status', 'pending')
            ->whereDate('payment_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $this->info("Marked {$updated} payment(s) as overdue.");

        return self::SUCCESS;
    }
}
