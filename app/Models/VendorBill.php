<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\VendorBillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A bill of a vendor that a branch has to pay. Amounts are whole rupees.
 */
class VendorBill extends Model
{
    /** @use HasFactory<VendorBillFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The bills that still have money owed on them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', [BillStatus::Unpaid, BillStatus::Partial]);
    }

    public function getBalanceAttribute(): int
    {
        return $this->status === BillStatus::Cancelled ? 0 : max(0, $this->amount - $this->paid_amount);
    }

    protected function casts(): array
    {
        return [
            'status' => BillStatus::class,
            'bill_date' => 'date',
            'due_date' => 'date',
            'amount' => 'integer',
            'paid_amount' => 'integer',
        ];
    }
}
