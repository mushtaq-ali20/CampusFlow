<?php

namespace App\Models;

use App\Enums\AppraisalStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\AppraisalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The evaluation of an employee in a cycle: a score from 1 to 5 on each criterion, which gives an overall
 * percentage once the evaluation is submitted. The employee then reads and acknowledges it.
 */
class Appraisal extends Model
{
    /** @use HasFactory<AppraisalFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AppraisalScore::class);
    }

    protected function casts(): array
    {
        return [
            'status' => AppraisalStatus::class,
            'score' => 'float',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }
}
