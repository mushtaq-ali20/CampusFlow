<?php

namespace App\Services;

use App\Enums\AppraisalStatus;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCriterion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The evaluation of the employees. Each criterion is scored from 1 to 5; the overall score is the percentage of
 * the highest possible score, each criterion counting for its weight.
 */
class AppraisalService
{
    public const MAX_SCORE = 5;

    /**
     * Starts the evaluation of every working employee of a branch (or of all branches) who has none in the cycle.
     * Returns how many were started.
     *
     * @throws DomainException
     */
    public function startForBranch(AppraisalCycle $cycle, ?int $campusId, User $evaluator): int
    {
        $this->assertOpen($cycle);

        $employees = EmployeeProfile::query()
            ->working()
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->whereDoesntHave('appraisals', fn ($query) => $query->where('appraisal_cycle_id', $cycle->id))
            ->get();

        foreach ($employees as $employee) {
            $this->start($cycle, $employee, $evaluator);
        }

        return $employees->count();
    }

    /**
     * @throws DomainException
     */
    public function start(AppraisalCycle $cycle, EmployeeProfile $employee, User $evaluator): Appraisal
    {
        $this->assertOpen($cycle);

        if (Appraisal::query()->where('appraisal_cycle_id', $cycle->id)->where('employee_profile_id', $employee->id)->exists()) {
            throw new DomainException(__('This employee has an evaluation in this cycle already.'));
        }

        return Appraisal::query()->create([
            'appraisal_cycle_id' => $cycle->id,
            'employee_profile_id' => $employee->id,
            'campus_id' => $employee->campus_id,
            'evaluator_id' => $evaluator->id,
            'status' => AppraisalStatus::Draft,
        ]);
    }

    /**
     * Saves the scores of a draft evaluation, and submits it when asked. A submitted evaluation needs a score on
     * every active criterion.
     *
     * @param  array<int, array{score?: int|string|null, remarks?: ?string}>  $scores  keyed by criterion id
     *
     * @throws DomainException
     */
    public function evaluate(Appraisal $appraisal, array $scores, ?string $strengths, ?string $improvements, User $evaluator, bool $submit = false): Appraisal
    {
        $this->assertOpen($appraisal->cycle);

        if ($appraisal->status !== AppraisalStatus::Draft) {
            throw new DomainException(__('This evaluation has been submitted already.'));
        }

        $criteria = EvaluationCriterion::query()->active()->get()->keyBy('id');

        if ($criteria->isEmpty()) {
            throw new DomainException(__('Set up the evaluation criteria first.'));
        }

        $given = collect($scores)
            ->only($criteria->keys()->all())
            ->filter(fn (array $line): bool => filled($line['score'] ?? null));

        foreach ($given as $line) {
            if ((int) $line['score'] < 1 || (int) $line['score'] > self::MAX_SCORE) {
                throw new DomainException(__('The scores go from 1 to :max.', ['max' => self::MAX_SCORE]));
            }
        }

        if ($submit && $given->count() < $criteria->count()) {
            throw new DomainException(__('Give a score on every criterion before submitting.'));
        }

        return DB::transaction(function () use ($appraisal, $given, $criteria, $strengths, $improvements, $evaluator, $submit): Appraisal {
            $appraisal->scores()->whereNotIn('evaluation_criterion_id', $given->keys()->all())->delete();

            foreach ($given as $criterionId => $line) {
                $appraisal->scores()->updateOrCreate(
                    ['evaluation_criterion_id' => $criterionId],
                    ['score' => (int) $line['score'], 'weight' => $criteria[$criterionId]->weight, 'remarks' => $line['remarks'] ?? null],
                );
            }

            $appraisal->update([
                'evaluator_id' => $evaluator->id,
                'strengths' => $strengths,
                'improvements' => $improvements,
                'score' => $this->percentage($appraisal),
                'status' => $submit ? AppraisalStatus::Submitted : AppraisalStatus::Draft,
                'submitted_at' => $submit ? now() : null,
            ]);

            return $appraisal->refresh();
        });
    }

    /**
     * The employee confirms having read the evaluation, with a comment if they wish.
     *
     * @throws DomainException
     */
    public function acknowledge(Appraisal $appraisal, User $employee, ?string $comment = null): Appraisal
    {
        if ($appraisal->employee->user_id !== $employee->id) {
            throw new DomainException(__('Only the employee can acknowledge their evaluation.'));
        }

        if ($appraisal->status !== AppraisalStatus::Submitted) {
            throw new DomainException(__('This evaluation is not waiting to be acknowledged.'));
        }

        $appraisal->update(['status' => AppraisalStatus::Acknowledged, 'employee_comment' => $comment, 'acknowledged_at' => now()]);

        return $appraisal->refresh();
    }

    /**
     * Gives a submitted evaluation back to the evaluator to correct it, as long as it is not acknowledged.
     *
     * @throws DomainException
     */
    public function reopen(Appraisal $appraisal): Appraisal
    {
        $this->assertOpen($appraisal->cycle);

        if ($appraisal->status !== AppraisalStatus::Submitted) {
            throw new DomainException(__('Only a submitted evaluation that is not acknowledged yet can be reopened.'));
        }

        $appraisal->update(['status' => AppraisalStatus::Draft, 'submitted_at' => null]);

        return $appraisal->refresh();
    }

    /**
     * The overall score of an evaluation as a percentage, or null without scores.
     */
    public function percentage(Appraisal $appraisal): ?float
    {
        $scores = $appraisal->scores()->get();
        $weights = (int) $scores->sum('weight');

        if ($weights === 0) {
            return null;
        }

        $earned = $scores->sum(fn ($score): int => $score->score * $score->weight);

        return round($earned / ($weights * self::MAX_SCORE) * 100, 2);
    }

    /**
     * The rating that goes with an overall score.
     */
    public static function ratingFor(?float $percentage): ?string
    {
        return match (true) {
            $percentage === null => null,
            $percentage >= 90 => __('Outstanding'),
            $percentage >= 75 => __('Exceeds expectations'),
            $percentage >= 60 => __('Meets expectations'),
            $percentage >= 40 => __('Needs improvement'),
            default => __('Unsatisfactory'),
        };
    }

    /**
     * @throws DomainException
     */
    private function assertOpen(AppraisalCycle $cycle): void
    {
        if ($cycle->is_closed) {
            throw new DomainException(__('This evaluation cycle is closed.'));
        }
    }
}
