<?php

namespace App\Models;

use Database\Factories\GradeBandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A band of the grading scale: a percentage from its minimum gets its grade, such as A+ from 90%.
 */
class GradeBand extends Model
{
    /** @use HasFactory<GradeBandFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected function casts(): array
    {
        return [
            'min_percentage' => 'float',
        ];
    }
}
