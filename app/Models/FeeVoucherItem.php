<?php

namespace App\Models;

use App\Enums\VoucherItemKind;
use Database\Factories\FeeVoucherItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeVoucherItem extends Model
{
    /** @use HasFactory<FeeVoucherItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FeeVoucher::class, 'fee_voucher_id');
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => VoucherItemKind::class,
            'amount' => 'integer',
        ];
    }
}
