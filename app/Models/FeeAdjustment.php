<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\FeeAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A change of the amount of a voucher: positive to charge more, negative to reduce it. Once approved, it becomes a
 * fee or a discount line of the voucher.
 */
class FeeAdjustment extends Model
{
    /** @use HasFactory<FeeAdjustmentFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FeeVoucher::class, 'fee_voucher_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
