<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceType extends Model
{
    use HasFactory, HasFallbackTranslations;

    public array $translatable = ['description'];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute()
    {
        return $this->description;
    }
}
