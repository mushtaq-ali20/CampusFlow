<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeTypeCategory extends Model
{
    use HasFactory, HasFallbackTranslations;

    protected $table = 'grade_type_categories';

    public $timestamps = false;

    protected $guarded = ['id'];

    public array $translatable = ['name'];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute()
    {
        return $this->name;
    }
}
