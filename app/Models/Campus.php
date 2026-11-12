<?php

namespace App\Models;

use App\Models\Concerns\HasFallbackTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A branch of the school. Students are admitted to a branch, and teachers can work at several branches.
 *
 * NOTE: In the current configuration, the campus with the ID of 1 represent the school itself
 * the campus model with the ID of 2 represents all external courses
 */
class Campus extends Model
{
    use HasFactory, HasFallbackTranslations;
    use SoftDeletes;

    public array $translatable = ['name'];

    public $timestamps = false;

    protected $fillable = ['name', 'code', 'address', 'phone'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class)->withTimestamps();
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
