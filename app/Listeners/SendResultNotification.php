<?php

namespace App\Listeners;

use App\Events\ResultSavedEvent;
use App\Mail\ResultNotification;
use App\Traits\ReportsErrors;
use Illuminate\Support\Facades\Mail;

class SendResultNotification
{
    use ReportsErrors;

    public function handle(ResultSavedEvent $event): void
    {
        if (! config('campusflow.send_emails_for_results')) {
            return;
        }

        try {
            $result = $event->result;

            Mail::to($result->enrollment->student->user->email)
                ->locale($result->enrollment->student->user->locale)
                ->queue(new ResultNotification($result->enrollment->course, $result->enrollment->student->user));
        } catch (\Throwable $e) {
            $this->reportError($e, 'SendResultNotification::handle', [
                'result_id' => $event->result->id ?? null,
            ]);
        }
    }
}
