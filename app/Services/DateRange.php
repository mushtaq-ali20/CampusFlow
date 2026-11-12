<?php

namespace App\Services;

use DateTime;

class DateRange
{
    public function __construct(
        public DateTime $start,
        public DateTime $end,
    ) {}
}
