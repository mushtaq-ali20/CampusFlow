<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceType extends Model
{
    use HasFactory, HasFallbackTranslations;

    public array $translatable = ['name'];

    public $timestamps = false;

    protected $appends = ['translated_name'];

    protected $fillable = ['color'];

    public function getTranslatedNameAttribute()
    {
        return $this->name;
    }
}
