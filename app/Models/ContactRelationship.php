<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactRelationship extends Model
{
    use HasFactory, HasFallbackTranslations;

    public $timestamps = false;

    public array $translatable = ['name'];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute()
    {
        return $this->name;
    }
}
