<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\FeeVoucherFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The fee of a student for a month: the fee items, the discounts and the late fine, and what was paid.
 * The branch, class, section and year are copied from the admission to report on them.
 */
class FeeVoucher extends Model
{
    /** @use HasFactory<FeeVoucherFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeVoucherItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    /**
     * The parts of the balance with their own due dates, when the voucher is paid in installments.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(FeeInstallment::class)->orderBy('sequence');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(FeeAdjustment::class);
    }

    /**
     * What is still to pay.
     */
    protected function balance(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->net_amount - $this->paid_amount));
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'due_date' => 'date',
            'status' => VoucherStatus::class,
            'gross_amount' => 'integer',
            'discount_amount' => 'integer',
            'fine_amount' => 'integer',
            'net_amount' => 'integer',
            'paid_amount' => 'integer',
        ];
    }
}
