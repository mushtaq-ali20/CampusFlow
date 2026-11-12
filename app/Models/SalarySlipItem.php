<?php

namespace App\Models;

use App\Enums\PayItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a salary slip. Lines copied from the employee file are fixed; manual lines are added to one slip, such
 * as a bonus or a one-off deduction.
 */
class SalarySlipItem extends Model
{
    protected $guarded = ['id'];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => PayItemKind::class,
            'amount' => 'integer',
            'is_manual' => 'boolean',
        ];
    }
}
