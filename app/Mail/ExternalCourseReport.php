<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExternalCourseReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $period_start, public $period_end, public $data) {}

    public function build()
    {
        return $this->subject(__('Billing').' '.$this->data['partner_name'])->view('emails.external_courses_report');
    }
}
