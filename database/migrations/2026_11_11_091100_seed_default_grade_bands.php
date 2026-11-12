<?php

use App\Models\GradeBand;
use Illuminate\Database\Migrations\Migration;

/**
 * The usual grading scale of Pakistani schools, when the institution has none yet. It can be changed under
 * School → Grading scale. The permissions of this part already exist: evaluation.* for the exams and the class
 * assessments, school.manage for the grading scale, fees.* for installments, adjustments and refunds.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: int, 1: string}>
     */
    private array $bands = [
        'A+' => [80, 'Outstanding'],
        'A' => [70, 'Excellent'],
        'B' => [60, 'Very good'],
        'C' => [50, 'Good'],
        'D' => [40, 'Satisfactory'],
        'E' => [33, 'Pass'],
        'F' => [0, 'Fail'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (GradeBand::query()->exists()) {
            return;
        }

        foreach ($this->bands as $grade => [$minimum, $remarks]) {
            GradeBand::query()->create(['grade' => $grade, 'min_percentage' => $minimum, 'remarks' => $remarks]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        GradeBand::query()->whereIn('grade', array_keys($this->bands))->delete();
    }
};
