<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\FeeRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Money given back to a family from a payment. It is requested, then approved, which records the date it was given.
 */
class FeeRefund extends Model
{
    /** @use HasFactory<FeeRefundFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
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
            'method' => PaymentMethod::class,
            'status' => RequestStatus::class,
            'refunded_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }
}
