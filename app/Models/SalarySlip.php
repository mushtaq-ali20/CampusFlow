<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\SalarySlipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The salary slip (receipt) of an employee for a month: the basic salary, the fixed allowances and deductions set
 * up for the employee, the deduction for unpaid leave, and the net salary paid out.
 */
class SalarySlip extends Model
{
    /** @use HasFactory<SalarySlipFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalarySlipItem::class)->orderBy('id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    protected function casts(): array
    {
        return [
            'basic_salary' => 'integer',
            'allowances_total' => 'integer',
            'deductions_total' => 'integer',
            'unpaid_days' => 'integer',
            'unpaid_deduction' => 'integer',
            'net_salary' => 'integer',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
        ];
    }
}
