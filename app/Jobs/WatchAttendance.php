<?php

namespace App\Jobs;

use App\Mail\AbsenceNotification;
use App\Models\Attendance;
use App\Traits\ReportsErrors;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class WatchAttendance implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsErrors;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(protected Attendance $attendance) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->attendance->attendance_type_id == 4) {
            try {
                $student = $this->attendance->student;
                $teacher = $this->attendance->event?->teacher;

                $otherRecipients = [];

                if ($teacher?->email !== null) {
                    $otherRecipients[] = ['email' => $teacher->email];
                }

                if (config('settings.manager_email') !== null) {
                    $otherRecipients[] = ['email' => explode(',', config('settings.manager_email'))];
                }

                foreach ($this->attendance->student->contacts as $contact) {
                    $otherRecipients[] = ['email' => $contact->email];
                }

                Mail::to($student->user->email)
                    ->locale($student->user->locale)
                    ->cc($otherRecipients)
                    ->queue(new AbsenceNotification($this->attendance->event, $student->user));
            } catch (\Throwable $e) {
                $this->reportError($e, 'WatchAttendance::handle', [
                    'attendance_id' => $this->attendance->id,
                ]);
            }
        }
    }
}
