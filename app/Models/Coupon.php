<?php

namespace App\Models;

use App\Traits\PriceTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory, PriceTrait;

    protected $guarded = ['id'];
}
