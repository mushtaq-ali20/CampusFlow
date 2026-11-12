<?php

namespace App\Models;

use App\Models\Interfaces\InvoiceableModel;
use App\Traits\PriceTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fee extends Model implements InvoiceableModel
{
    use HasFactory, PriceTrait;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $appends = ['price_with_currency', 'type'];

    public function getTypeAttribute(): string
    {
        return 'fee';
    }
}
