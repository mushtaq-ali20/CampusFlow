<?php

namespace App\Models;

use App\Enums\PayItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fixed allowance or a fixed deduction of an employee. The same amount goes on the salary slip every month.
 */
class EmployeePayItem extends Model
{
    protected $guarded = ['id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => PayItemKind::class,
            'amount' => 'integer',
        ];
    }
}
