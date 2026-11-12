<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PayItemKind;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The HR file of a user who works for the school, staff or teacher, with the basic salary and the fixed allowances
 * and deductions that go on the salary slip every month.
 */
class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (blank($profile->employee_code)) {
                $profile->employee_code = 'EMP-'.str_pad((string) ((int) static::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function payItems(): HasMany
    {
        return $this->hasMany(EmployeePayItem::class);
    }

    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWorking(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active);
    }

    /**
     * The fixed allowances or deductions of the employee, in the order they were set up.
     *
     * @return list<array{name: string, amount: int}>
     */
    public function fixedLines(PayItemKind $kind): array
    {
        return $this->payItems
            ->where('kind', $kind)
            ->map(fn (EmployeePayItem $item): array => ['name' => $item->name, 'amount' => $item->amount])
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'joined_on' => 'date',
            'left_on' => 'date',
            'basic_salary' => 'integer',
        ];
    }
}
