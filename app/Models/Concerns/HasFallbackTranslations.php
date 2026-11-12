<?php

namespace App\Models\Concerns;

use Spatie\Translatable\HasTranslations;

/**
 * Simplified translatable trait that reads fr > es > en
 * and always writes to the 'fr' locale.
 *
 * Keeps compatibility with the existing JSON column structure
 * from Spatie's HasTranslations without requiring per-locale management.
 */
trait HasFallbackTranslations
{
    use HasTranslations {
        HasTranslations::setTranslation as baseSetTranslation;
    }

    protected static array $localePriority = ['fr', 'es', 'en'];

    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed
    {
        $translations = $this->getTranslations($key);

        foreach (static::$localePriority as $fallbackLocale) {
            if (isset($translations[$fallbackLocale]) && $translations[$fallbackLocale] !== '') {
                return $translations[$fallbackLocale];
            }
        }

        // Last resort: return any non-empty value
        foreach ($translations as $value) {
            if ($value !== '' && $value !== null) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Override attributesToArray so that toArray() returns the resolved
     * translated string instead of the full translations array.
     * The 'array' cast added by Spatie would otherwise return the raw
     * JSON object, causing Filament forms to display [object Object].
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach ($this->getTranslatableAttributes() as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->getTranslation($key, $this->getLocale());
            }
        }

        return $attributes;
    }

    public function setTranslation(string $key, string $locale, mixed $value): self
    {
        return $this->baseSetTranslation($key, 'fr', $value);
    }
}
