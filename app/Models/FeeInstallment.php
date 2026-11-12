<?php

namespace App\Models;

use Database\Factories\FeeInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part of the balance of a voucher with its own due date. Payments of the voucher pay the installments in order.
 */
class FeeInstallment extends Model
{
    /** @use HasFactory<FeeInstallmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FeeVoucher::class, 'fee_voucher_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'sequence' => 'integer',
            'due_date' => 'date',
        ];
    }
}
