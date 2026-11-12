<?php

namespace App\Models;

use App\Services\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An academic year. Its dates are configurable, because the start of the academic year can change.
 */
class Year extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * The academic year chosen in the settings, otherwise the one that includes today, otherwise the latest one.
     */
    public static function current(): ?self
    {
        $selectedYear = static::query()->find(app(Settings::class)->get('current_academic_year'));

        if ($selectedYear) {
            return $selectedYear;
        }

        $today = now()->toDateString();

        return static::query()
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today)
            ->first()
            ?? static::query()->latest('id')->first();
    }

    public function isCurrent(): bool
    {
        return static::current()?->is($this) ?? false;
    }

    protected function casts(): array
    {
        return [
            'start' => 'date',
            'end' => 'date',
        ];
    }
}
