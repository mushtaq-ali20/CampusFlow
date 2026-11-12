<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory, HasFallbackTranslations;

    protected $guarded = ['id'];

    public $timestamps = false;

    public array $translatable = ['name'];
}
