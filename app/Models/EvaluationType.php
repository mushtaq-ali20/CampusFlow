<?php

namespace App\Models;

use App\Models\Skills\Skill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class EvaluationType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $with = ['gradeTypes', 'skills'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function gradeTypes(): MorphToMany
    {
        return $this->morphedByMany(GradeType::class, 'presettable', 'evaluation_type_presets');
    }

    public function skills(): MorphToMany
    {
        return $this->morphedByMany(Skill::class, 'presettable', 'evaluation_type_presets');
    }
}
