<?php

namespace App\Listeners;

use App\Events\MonthlyReportEvent;
use App\Mail\MonthlyReport;
use App\Traits\ReportsErrors;
use Illuminate\Support\Facades\Mail;

class SendMonthlyReport
{
    use ReportsErrors;

    public function handle(MonthlyReportEvent $event): void
    {
        try {
            $recipients = [];

            if (config('settings.reports_email') !== null) {
                $recipients[] = ['email' => explode(',', config('settings.reports_email'))];
            }

            Mail::to($recipients)->queue(new MonthlyReport);
        } catch (\Throwable $e) {
            $this->reportError($e, 'SendMonthlyReport::handle');
        }
    }
}
