<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class GradeType extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $with = ['category'];

    protected $appends = ['complete_name'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GradeTypeCategory::class, 'grade_type_category_id');
    }

    public function presets(): MorphToMany
    {
        return $this->morphToMany(EvaluationType::class, 'presettable', 'evaluation_type_presets');
    }

    public function getCompleteNameAttribute()
    {
        if ($this->category) {
            return '['.$this->category->name.'] '.$this->name;
        }

        return null;
    }
}
