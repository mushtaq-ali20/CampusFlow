<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The days a year an employee may take of a leave type, and whether the leave is paid. A leave type without an
 * allowance is paid and has no limit.
 */
class LeaveAllowance extends Model
{
    protected $guarded = ['id'];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    protected function casts(): array
    {
        return [
            'days_per_year' => 'integer',
            'is_paid' => 'boolean',
        ];
    }
}
