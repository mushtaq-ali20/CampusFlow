<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\ExamType;
use App\Models\Admission;
use App\Models\AssessmentMark;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The results of a section in an exam term: the marks of each student in each subject, their class assessments,
 * their percentage, grade and position, and the analysis of each subject. A subject is passed with its passing
 * marks in the exam, as for the promotion. When the class assessments carry a weight (see GradingService), the
 * percentage of a subject mixes the exam and the average of the assessments of the term.
 */
class ResultService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly StudentOverviewService $overview,
    ) {}

    /**
     * @return array{
     *     section: Section,
     *     exam: ExamType,
     *     weight: int,
     *     subjects: list<array{id: int, name: string, total: int, passing: int}>,
     *     rows: list<array{admission: Admission, name: string, subjects: array<int, array{marks: ?float, formative: ?float, percentage: ?float, passed: ?bool}>, obtained: float, total: int, percentage: ?float, grade: ?string, result: string, position: ?int}>,
     *     summary: array{students: int, withMarks: int, average: ?float, passed: int, failed: int, top: ?string, weakest: ?string},
     *     analysis: list<array{name: string, average: ?float, passed: int, failed: int, highest: ?float}>,
     * }
     */
    public function sheet(Section $section, ExamType $exam): array
    {
        $section->loadMissing('schoolClass.subjects');
        $weight = (float) ($this->grading->formativeWeight() / 100);

        $subjects = $section->schoolClass->subjects->map(fn (Subject $subject): array => [
            'id' => $subject->id,
            'name' => $subject->name,
            'total' => (int) $subject->pivot->total_marks,
            'passing' => (int) $subject->pivot->passing_marks,
        ])->values();

        $admissions = Admission::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where('status', '!=', AdmissionStatus::Left)
            ->get()
            ->sortBy(fn (Admission $admission): string => $admission->student->name)
            ->values();

        $marks = SubjectResult::query()
            ->whereIn('admission_id', $admissions->pluck('id'))
            ->where('exam', $exam)
            ->get()
            ->groupBy('admission_id')
            ->map(fn (Collection $results) => $results->pluck('obtained_marks', 'subject_id'));

        $formative = $this->formative($section, $exam);

        $rows = $admissions->map(function (Admission $admission) use ($subjects, $marks, $formative, $weight): array {
            $cells = [];
            $weighted = 0.0;
            $weightedTotal = 0;
            $obtained = 0.0;
            $failed = false;
            $missing = false;

            foreach ($subjects as $subject) {
                $mark = $marks[$admission->id][$subject['id']] ?? null;
                $assessments = $formative[$admission->id][$subject['id']] ?? null;
                $examPercentage = $mark !== null && $subject['total'] > 0 ? $mark * 100 / $subject['total'] : null;
                $percentage = match (true) {
                    $examPercentage === null => null,
                    $assessments === null || $weight === 0.0 => $examPercentage,
                    default => (1 - $weight) * $examPercentage + $weight * $assessments,
                };

                $cells[$subject['id']] = [
                    'marks' => $mark !== null ? (float) $mark : null,
                    'formative' => $assessments !== null ? round($assessments, 1) : null,
                    'percentage' => $percentage !== null ? round($percentage, 1) : null,
                    'passed' => $mark !== null ? $mark >= $subject['passing'] : null,
                ];

                if ($mark === null) {
                    $missing = true;

                    continue;
                }

                $obtained += $mark;
                $weighted += $percentage * $subject['total'];
                $weightedTotal += $subject['total'];
                $failed = $failed || $mark < $subject['passing'];
            }

            $percentage = $weightedTotal > 0 ? round($weighted / $weightedTotal, 1) : null;

            return [
                'admission' => $admission,
                'name' => $admission->student->name,
                'subjects' => $cells,
                'obtained' => $obtained,
                'total' => (int) $subjects->sum('total'),
                'percentage' => $percentage,
                'grade' => $this->grading->gradeFor($percentage),
                'result' => match (true) {
                    $percentage === null => 'no_marks',
                    $failed => 'fail',
                    $missing => 'incomplete',
                    default => 'pass',
                },
                'position' => null,
            ];
        });

        $rows = $this->withPositions($rows);
        $withMarks = $rows->whereNotNull('percentage');
        $analysis = $subjects->map(function (array $subject) use ($rows): array {
            $cells = $rows->map(fn (array $row): array => $row['subjects'][$subject['id']])->filter(fn (array $cell): bool => $cell['marks'] !== null);

            return [
                'name' => $subject['name'],
                'average' => $cells->isNotEmpty() ? round($cells->avg('percentage'), 1) : null,
                'passed' => $cells->where('passed', true)->count(),
                'failed' => $cells->where('passed', false)->count(),
                'highest' => $cells->max('marks'),
            ];
        })->values();

        return [
            'section' => $section,
            'exam' => $exam,
            'weight' => (int) round($weight * 100),
            'subjects' => $subjects->all(),
            'rows' => $rows->all(),
            'summary' => [
                'students' => $rows->count(),
                'withMarks' => $withMarks->count(),
                'average' => $withMarks->isNotEmpty() ? round($withMarks->avg('percentage'), 1) : null,
                'passed' => $rows->where('result', 'pass')->count(),
                'failed' => $rows->where('result', 'fail')->count(),
                'top' => $withMarks->sortByDesc('percentage')->first()['name'] ?? null,
                'weakest' => $analysis->whereNotNull('average')->sortBy('average')->first()['name'] ?? null,
            ],
            'analysis' => $analysis->all(),
        ];
    }

    /**
     * The label of a result: pass, fail, incomplete (marks missing in a subject) or no marks.
     */
    public static function resultLabel(string $result): string
    {
        return match ($result) {
            'pass' => __('Pass'),
            'fail' => __('Fail'),
            'incomplete' => __('Incomplete'),
            default => __('No marks'),
        };
    }

    /**
     * The result cards of the students of the section, one page each, or of one student.
     */
    public function cards(Section $section, ExamType $exam, ?Admission $only = null): StreamedResponse
    {
        $sheet = $this->sheet($section, $exam);
        $rows = collect($sheet['rows'])
            ->when($only !== null, fn (Collection $rows) => $rows->filter(fn (array $row): bool => $row['admission']->is($only)))
            ->map(fn (array $row): array => [...$row, 'attendance' => $this->overview->attendance($row['admission'])])
            ->values();

        $logo = config('campusflow.logo');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => storage_path('framework/cache/mpdf'),
        ]);

        $mpdf->WriteHTML(view('pdf.result-card', [
            ...$sheet,
            'rows' => $rows,
            'grading' => $this->grading,
            'institution' => config('campusflow.company_name'),
            'logo' => $logo && Storage::disk('public')->exists($logo) ? Storage::disk('public')->path($logo) : null,
        ])->render());

        $pdf = $mpdf->Output('', Destination::STRING_RETURN);
        $name = 'result-card-'.Str::slug($exam->getLabel()).'-'.Str::slug($only ? $only->student->name : $section->full_name).'.pdf';

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $name, ['Content-Type' => 'application/pdf']);
    }

    /**
     * The average percentage of each student in the class assessments of each subject in the term: the marks
     * obtained out of the total marks of the assessments they have marks for.
     *
     * @return array<int, array<int, float>>
     */
    public function formative(Section $section, ExamType $exam): array
    {
        $averages = [];

        AssessmentMark::query()
            ->whereHas('assessment', fn ($assessments) => $assessments->where('section_id', $section->id)->where('exam', $exam))
            ->with('assessment')
            ->get()
            ->groupBy(fn (AssessmentMark $mark): string => $mark->admission_id.'-'.$mark->assessment->subject_id)
            ->each(function (Collection $marks) use (&$averages): void {
                $first = $marks->first();
                $total = $marks->sum(fn (AssessmentMark $mark): int => $mark->assessment->total_marks);

                if ($total > 0) {
                    $averages[$first->admission_id][$first->assessment->subject_id] = $marks->sum('marks') * 100 / $total;
                }
            });

        return $averages;
    }

    /**
     * Positions from the highest percentage; equal percentages share a position and the next one is skipped.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function withPositions(Collection $rows): Collection
    {
        $ranked = $rows->whereNotNull('percentage')->sortByDesc('percentage')->values();
        $positions = [];
        $previous = null;
        $position = 0;

        foreach ($ranked as $index => $row) {
            if ($row['percentage'] !== $previous) {
                $position = $index + 1;
                $previous = $row['percentage'];
            }

            $positions[$row['admission']->id] = $position;
        }

        return $rows->map(fn (array $row): array => [...$row, 'position' => $positions[$row['admission']->id] ?? null]);
    }
}
