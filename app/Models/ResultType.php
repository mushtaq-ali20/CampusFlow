<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResultType extends Model
{
    use HasFactory, HasFallbackTranslations;

    protected $guarded = ['id'];

    public array $translatable = ['name', 'description'];

    protected $appends = ['translated_name', 'translated_description'];

    public function getTranslatedNameAttribute()
    {
        return $this->name;
    }

    public function getTranslatedDescriptionAttribute()
    {
        return $this->description;
    }
}
