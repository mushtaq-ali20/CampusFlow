<?php

namespace App\Services;

use App\Models\GradeBand;
use Illuminate\Support\Collection;

/**
 * The grading scale, and the weight of the class assessments in the result of an exam term.
 *
 * Setting: exams.formative_weight, the share of the class assessments in the percentage of a subject, from 0 (the
 * exam alone, the default) to 50. The promotion is not affected: it still needs the passing marks in the annual exam.
 */
class GradingService
{
    /** @var Collection<int, GradeBand>|null */
    private ?Collection $bands = null;

    public function __construct(private readonly Settings $settings) {}

    /**
     * The bands of the scale, from the highest minimum.
     *
     * @return Collection<int, GradeBand>
     */
    public function bands(): Collection
    {
        return $this->bands ??= GradeBand::query()->orderByDesc('min_percentage')->get();
    }

    /**
     * The band a percentage falls in, or null when there is no scale or no percentage.
     */
    public function bandFor(?float $percentage): ?GradeBand
    {
        if ($percentage === null) {
            return null;
        }

        return $this->bands()->first(fn (GradeBand $band): bool => $percentage >= $band->min_percentage);
    }

    public function gradeFor(?float $percentage): ?string
    {
        return $this->bandFor($percentage)?->grade;
    }

    /**
     * The share of the class assessments in the percentage of a subject, from 0 to 50.
     */
    public function formativeWeight(): int
    {
        return max(0, min(50, (int) $this->settings->get('exams.formative_weight', 0)));
    }

    public function setFormativeWeight(int $weight): void
    {
        $this->settings->set(['exams.formative_weight' => max(0, min(50, $weight))]);
    }

    /**
     * Forgets the scale read so far, after it changed.
     */
    public function refresh(): void
    {
        $this->bands = null;
    }
}
