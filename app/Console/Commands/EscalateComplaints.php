<?php

namespace App\Console\Commands;

use App\Services\ComplaintService;
use App\Traits\RunsForEachTenant;
use Illuminate\Console\Command;

class EscalateComplaints extends Command
{
    use RunsForEachTenant;

    protected $signature = 'campusflow:escalate-complaints
        {--tenant=* : Only run for these tenants (ID or name)}';

    protected $description = 'Escalate the open complaints past their time limit to the escalation contact of their category';

    public function handle(ComplaintService $complaints): int
    {
        $escalated = $complaints->escalateOverdue();

        if ($escalated > 0) {
            $this->info("{$escalated} complaint(s) escalated.");
        }

        return Command::SUCCESS;
    }
}
