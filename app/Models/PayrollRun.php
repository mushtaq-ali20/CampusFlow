<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The salary of the employees of a branch for a month. It is a draft until it is finalized, then its slips are fixed.
 */
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function slips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    /**
     * The month as a date, such as the 1st of March 2026.
     */
    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    public function monthLabel(): string
    {
        return $this->monthStart()->translatedFormat('F Y');
    }

    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'finalized_at' => 'datetime',
        ];
    }
}
