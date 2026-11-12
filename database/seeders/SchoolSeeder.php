<?php

namespace Database\Seeders;

use App\Enums\Shift;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Models\Year;
use App\Services\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Demo data for a school with two branches and two shifts: classes from Montessori to Class 10 with their subjects,
 * the periods of each shift, the sections of each branch, and 30 subject teachers, some of whom teach at both
 * branches. Every subject of every section gets a teacher who has time for its weekly lessons.
 */
class SchoolSeeder extends Seeder
{
    /**
     * School days, Monday to Saturday.
     */
    public const DAYS = [1, 2, 3, 4, 5, 6];

    /**
     * @var array<string, list<string>>
     */
    private const SUBJECTS_BY_STAGE = [
        'montessori' => ['Practical Life', 'Sensorial', 'Language', 'Numbers'],
        'early' => ['English', 'Urdu', 'Mathematics', 'General Knowledge', 'Drawing'],
        'kindergarten' => ['English', 'Urdu', 'Mathematics', 'General Knowledge', 'Drawing', 'Islamiat'],
        'primary' => ['English', 'Urdu', 'Mathematics', 'Science', 'Islamiat', 'Social Studies', 'Computer', 'Nazra Quran'],
        'middle' => ['English', 'Urdu', 'Mathematics', 'General Science', 'Islamiat', 'History & Geography', 'Computer', 'Arabic'],
        'secondary' => ['English', 'Urdu', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Islamiat', 'Pakistan Studies', 'Computer Science'],
    ];

    /**
     * @var array<string, string>
     */
    private const SUBJECT_CODES = [
        'Practical Life' => 'PL', 'Sensorial' => 'SEN', 'Language' => 'LANG', 'Numbers' => 'NUM',
        'English' => 'ENG', 'Urdu' => 'URD', 'Mathematics' => 'MATH', 'General Knowledge' => 'GK', 'Drawing' => 'DRW',
        'Islamiat' => 'ISL', 'Science' => 'SCI', 'Social Studies' => 'SST', 'Computer' => 'COMP', 'Nazra Quran' => 'NQ',
        'General Science' => 'GSCI', 'History & Geography' => 'HG', 'Arabic' => 'ARB', 'Physics' => 'PHY',
        'Chemistry' => 'CHEM', 'Biology' => 'BIO', 'Pakistan Studies' => 'PST', 'Computer Science' => 'CS',
    ];

    /**
     * Each class with its stage and the shifts it runs in at every branch, each shift with its sections:
     * the youngest children study in the morning, Classes 1 to 5 have sections A and B in both shifts,
     * and Classes 6 to 10 study in the afternoon.
     *
     * @var list<array{0: string, 1: string, 2: list<Shift>, 3: list<string>}>
     */
    private const CLASSES = [
        ['Montessori', 'montessori', [Shift::Morning], ['A']],
        ['Pre Nursery', 'early', [Shift::Morning], ['A']],
        ['Nursery', 'early', [Shift::Morning], ['A']],
        ['KG I', 'kindergarten', [Shift::Morning], ['A']],
        ['KG II', 'kindergarten', [Shift::Morning], ['A']],
        ['Class 1', 'primary', [Shift::Morning, Shift::Afternoon], ['A', 'B']],
        ['Class 2', 'primary', [Shift::Morning, Shift::Afternoon], ['A', 'B']],
        ['Class 3', 'primary', [Shift::Morning, Shift::Afternoon], ['A', 'B']],
        ['Class 4', 'primary', [Shift::Morning, Shift::Afternoon], ['A', 'B']],
        ['Class 5', 'primary', [Shift::Morning, Shift::Afternoon], ['A', 'B']],
        ['Class 6', 'middle', [Shift::Afternoon], ['A', 'B']],
        ['Class 7', 'middle', [Shift::Afternoon], ['A', 'B']],
        ['Class 8', 'middle', [Shift::Afternoon], ['A', 'B']],
        ['Class 9', 'secondary', [Shift::Afternoon], ['A', 'B']],
        ['Class 10', 'secondary', [Shift::Afternoon], ['A', 'B']],
    ];

    /**
     * The periods of each shift: morning 7:30 AM to 12:00 PM, afternoon 12:00 PM to 5:00 PM.
     *
     * @var list<array{0: Shift, 1: string, 2: string, 3: string, 4: bool}>
     */
    private const PERIODS = [
        [Shift::Morning, 'Period 1', '07:30', '08:10', false],
        [Shift::Morning, 'Period 2', '08:10', '08:50', false],
        [Shift::Morning, 'Period 3', '08:50', '09:30', false],
        [Shift::Morning, 'Break', '09:30', '10:00', true],
        [Shift::Morning, 'Period 4', '10:00', '10:40', false],
        [Shift::Morning, 'Period 5', '10:40', '11:20', false],
        [Shift::Morning, 'Period 6', '11:20', '12:00', false],
        [Shift::Afternoon, 'Period 1', '12:00', '12:40', false],
        [Shift::Afternoon, 'Period 2', '12:40', '13:20', false],
        [Shift::Afternoon, 'Period 3', '13:20', '14:00', false],
        [Shift::Afternoon, 'Break', '14:00', '14:20', true],
        [Shift::Afternoon, 'Period 4', '14:20', '15:00', false],
        [Shift::Afternoon, 'Period 5', '15:00', '15:40', false],
        [Shift::Afternoon, 'Period 6', '15:40', '16:20', false],
        [Shift::Afternoon, 'Period 7', '16:20', '17:00', false],
    ];

    /**
     * The subjects each kind of teacher teaches.
     *
     * @var array<string, list<string>>
     */
    private const SPECIALITIES = [
        'early_years' => ['Practical Life', 'Sensorial', 'Language', 'Numbers', 'General Knowledge', 'Drawing', 'English', 'Urdu', 'Mathematics'],
        'english' => ['English'],
        'urdu' => ['Urdu'],
        'mathematics' => ['Mathematics'],
        'science' => ['Science', 'General Science', 'Physics', 'Chemistry', 'Biology'],
        'islamiat' => ['Islamiat', 'Nazra Quran', 'Arabic'],
        'social' => ['Social Studies', 'History & Geography', 'Pakistan Studies', 'General Knowledge'],
        'computer' => ['Computer', 'Computer Science', 'Drawing'],
    ];

    /**
     * The teachers based at each branch (23 at the first, 22 at the second), with their speciality. Those marked
     * true teach the morning shift at their own branch and the afternoon shift at the other one.
     *
     * @var list<list<array{0: string, 1: string, 2: string, 3: bool}>>
     */
    private const TEACHERS = [
        [
            ['Ayesha', 'Khan', 'early_years', false], ['Fatima', 'Ahmed', 'early_years', false], ['Maryam', 'Siddiqui', 'early_years', false],
            ['Muhammad', 'Ali', 'english', true], ['Sana', 'Malik', 'english', false], ['Tahir', 'Saeed', 'english', false],
            ['Usman', 'Tariq', 'urdu', false], ['Hira', 'Iqbal', 'urdu', false], ['Shazia', 'Parveen', 'urdu', false],
            ['Bilal', 'Hussain', 'mathematics', true], ['Zainab', 'Raza', 'mathematics', false], ['Adeel', 'Akram', 'mathematics', false],
            ['Imran', 'Qureshi', 'science', true], ['Mehwish', 'Butt', 'science', false], ['Rizwan', 'Haider', 'science', false], ['Samina', 'Kausar', 'science', false],
            ['Hafiz Abdullah', 'Sheikh', 'islamiat', false], ['Nadia', 'Aslam', 'islamiat', false], ['Maulana Yasir', 'Hameed', 'islamiat', false],
            ['Kamran', 'Javed', 'social', false], ['Uzma', 'Shaheen', 'social', false],
            ['Amna', 'Farooq', 'computer', false], ['Junaid', 'Aziz', 'computer', false],
        ],
        [
            ['Khadija', 'Rehman', 'early_years', false], ['Sumaira', 'Nawaz', 'early_years', false], ['Rabia', 'Anwar', 'early_years', false],
            ['Saad', 'Mirza', 'english', true], ['Iqra', 'Shahid', 'english', false], ['Nabeel', 'Ashraf', 'english', false],
            ['Asad', 'Chaudhry', 'urdu', false], ['Hafsa', 'Yousaf', 'urdu', false], ['Farah', 'Naz', 'urdu', false],
            ['Faisal', 'Abbasi', 'mathematics', true], ['Sadia', 'Haider', 'mathematics', false], ['Shoaib', 'Rafiq', 'mathematics', false],
            ['Hamza', 'Zafar', 'science', true], ['Areeba', 'Saleem', 'science', false], ['Lubna', 'Tariq', 'science', false], ['Arslan', 'Nadeem', 'science', false],
            ['Qari Ibrahim', 'Ansari', 'islamiat', false], ['Ayesha', 'Bashir', 'islamiat', false], ['Hafiz Usman', 'Ghani', 'islamiat', false],
            ['Waqas', 'Mahmood', 'social', false],
            ['Noor', 'Fatima', 'computer', false], ['Bushra', 'Aziz', 'computer', false],
        ],
    ];

    public function run(): void
    {
        app(Settings::class)->set([
            'campusflow.currency_code' => 'PKR',
            'campusflow.currency_symbol' => 'Rs',
            'campusflow.currency_position' => 'before',
            'settings.courses_timezone' => 'Asia/Karachi',
        ]);

        $year = $this->academicYear();
        $branches = $this->branches();
        $classes = $this->classes($this->subjects());
        $this->periods();
        $teachers = $this->teachers($branches);
        $this->branchSecretaries($branches);

        $this->sectionsAndAssignments($year, $branches, $classes, $teachers);
    }

    /**
     * The number of lessons a week of each subject of a section, spread evenly over the periods of its shift.
     *
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, int> lessons by subject id
     */
    public static function weeklyLessons(Shift $shift, Collection $subjects): array
    {
        $slots = count(self::DAYS) * collect(self::PERIODS)->filter(fn (array $period): bool => $period[0] === $shift && ! $period[4])->count();
        $base = intdiv($slots, $subjects->count());
        $extra = $slots % $subjects->count();

        return $subjects->values()->mapWithKeys(fn (Subject $subject, int $index): array => [
            $subject->id => $base + ($index < $extra ? 1 : 0),
        ])->all();
    }

    /**
     * Sections of every class at both branches, with a teacher for each subject. A teacher is chosen among the
     * specialists of the subject who work at that branch in that shift, the least busy first, as long as they
     * have periods left in the shift; otherwise the least busy teacher of the branch and shift.
     *
     * @param  Collection<int, Campus>  $branches
     * @param  Collection<int, array{class: SchoolClass, shifts: list<Shift>, sections: list<string>}>  $classes
     * @param  Collection<int, array{teacher: Teacher, speciality: string, shifts: array<int, list<Shift>>}>  $teachers
     */
    private function sectionsAndAssignments(Year $year, Collection $branches, Collection $classes, Collection $teachers): void
    {
        $capacity = [
            Shift::Morning->value => count(self::DAYS) * 6,
            Shift::Afternoon->value => count(self::DAYS) * 7,
        ];
        $load = [];

        $sections = $branches->crossJoin($classes)->flatMap(fn (array $pair): array => collect($pair[1]['shifts'])
            ->crossJoin($pair[1]['sections'])
            ->map(fn (array $shiftAndName): array => [$pair[0], $pair[1]['class'], $shiftAndName[0], $shiftAndName[1]])
            ->all());

        foreach ($sections as [$branch, $class, $shift, $sectionName]) {
            $section = Section::query()->updateOrCreate(
                ['campus_id' => $branch->id, 'school_class_id' => $class->id, 'year_id' => $year->id, 'shift' => $shift, 'name' => $sectionName],
                ['capacity' => 30],
            );

            $available = $teachers->filter(fn (array $entry): bool => in_array($shift, $entry['shifts'][$branch->id] ?? [], true));
            $lessonsByTeacher = [];

            foreach (self::weeklyLessons($shift, $class->subjects) as $subjectId => $lessons) {
                $subjectName = $class->subjects->firstWhere('id', $subjectId)->name;
                $loadOf = fn (array $entry): int => $load[$entry['teacher']->id][$shift->value] ?? 0;
                $hasTime = fn (array $entry): bool => $loadOf($entry) + $lessons <= $capacity[$shift->value];

                $chosen = $available
                    ->filter(fn (array $entry): bool => in_array($subjectName, self::SPECIALITIES[$entry['speciality']], true))
                    ->filter($hasTime)
                    ->sortBy($loadOf)
                    ->first()
                    ?? $available->filter($hasTime)->sortBy($loadOf)->first()
                    ?? $available->sortBy($loadOf)->first();

                $teacherId = $chosen['teacher']->id;
                $load[$teacherId][$shift->value] = ($load[$teacherId][$shift->value] ?? 0) + $lessons;
                $lessonsByTeacher[$teacherId] = ($lessonsByTeacher[$teacherId] ?? 0) + $lessons;

                TeachingAssignment::query()->updateOrCreate(
                    ['section_id' => $section->id, 'subject_id' => $subjectId],
                    ['teacher_id' => $teacherId],
                );
            }

            arsort($lessonsByTeacher);
            $section->update(['class_teacher_id' => array_key_first($lessonsByTeacher)]);
        }
    }

    /**
     * One secretary per branch, who only works with the records of that branch.
     *
     * @param  Collection<int, Campus>  $branches
     */
    private function branchSecretaries(Collection $branches): void
    {
        foreach ($branches as $branch) {
            $user = User::query()->firstOrCreate(
                ['email' => strtolower($branch->code).'.secretary@campusflow.test'],
                ['firstname' => $branch->name, 'lastname' => 'Secretary', 'password' => bcrypt('secret')],
            );

            $user->update(['campus_id' => $branch->id]);
            $user->syncRoles(['secretary']);
        }
    }

    private function academicYear(): Year
    {
        $start = now()->month >= 4 ? now()->startOfYear()->month(4) : now()->subYear()->startOfYear()->month(4);

        $year = Year::query()->updateOrCreate(
            ['name' => $start->year.'-'.($start->year + 1)],
            ['start' => $start->toDateString(), 'end' => $start->copy()->addYear()->subDay()->toDateString()],
        );

        app(Settings::class)->set(['current_academic_year' => $year->id]);

        return $year;
    }

    /**
     * @return Collection<int, Campus>
     */
    private function branches(): Collection
    {
        $firstBranch = Campus::query()->find(1) ?? new Campus;
        $firstBranch->fill(['name' => 'Branch 1', 'code' => 'B1'])->save();

        return collect([
            $firstBranch,
            Campus::query()->updateOrCreate(['code' => 'B2'], ['name' => 'Branch 2']),
        ]);
    }

    /**
     * @return Collection<string, Subject>
     */
    private function subjects(): Collection
    {
        return collect(self::SUBJECTS_BY_STAGE)
            ->flatten()
            ->unique()
            ->mapWithKeys(fn (string $name): array => [
                $name => Subject::query()->firstOrCreate(['name' => $name], ['code' => self::SUBJECT_CODES[$name]]),
            ]);
    }

    /**
     * Classes with their subjects, the shifts they run in and the sections of each shift.
     *
     * @param  Collection<string, Subject>  $subjects
     * @return Collection<int, array{class: SchoolClass, shifts: list<Shift>, sections: list<string>}>
     */
    private function classes(Collection $subjects): Collection
    {
        return collect(self::CLASSES)->map(function (array $definition, int $index) use ($subjects): array {
            [$name, $stage, $shifts, $sections] = $definition;

            $class = SchoolClass::query()->updateOrCreate(['name' => $name], ['order' => $index + 1]);

            $class->subjects()->syncWithoutDetaching(
                collect(self::SUBJECTS_BY_STAGE[$stage])
                    ->mapWithKeys(fn (string $subject): array => [$subjects[$subject]->id => ['total_marks' => 100, 'passing_marks' => 33]])
                    ->all()
            );

            return ['class' => $class->load('subjects'), 'shifts' => $shifts, 'sections' => $sections];
        });
    }

    /**
     * The periods of both shifts. Afternoon periods are ordered after the morning ones.
     */
    private function periods(): void
    {
        foreach (self::PERIODS as $index => [$shift, $name, $start, $end, $isBreak]) {
            TimetablePeriod::query()->updateOrCreate(
                ['shift' => $shift, 'name' => $name],
                ['start_time' => $start, 'end_time' => $end, 'is_break' => $isBreak, 'order' => $index + 1],
            );
        }
    }

    /**
     * The teachers based at each branch, who teach both shifts there. A few teach the morning shift at
     * their own branch and the afternoon shift at the other one.
     *
     * @param  Collection<int, Campus>  $branches
     * @return Collection<int, array{teacher: Teacher, speciality: string, shifts: array<int, list<Shift>>}>
     */
    private function teachers(Collection $branches): Collection
    {
        $definitions = collect(self::TEACHERS)->flatMap(fn (array $teachers, int $branchIndex): array => array_map(
            fn (array $teacher): array => [...$teacher, $branches[$branchIndex]],
            $teachers,
        ));

        return $definitions->values()->map(function (array $definition, int $index) use ($branches): array {
            [$firstname, $lastname, $speciality, $teachesAtBothBranches, $home] = $definition;

            $other = $branches->first(fn (Campus $branch): bool => $branch->isNot($home));

            $user = User::query()->firstOrCreate(
                ['email' => strtolower(str_replace(' ', '', $firstname).'.'.$lastname).'@campusflow.test'],
                ['firstname' => $firstname, 'lastname' => $lastname, 'password' => bcrypt('secret')],
            );

            $teacher = Teacher::query()->firstOrCreate(['id' => $user->id], ['hired_at' => now()->subYears(($index % 8) + 1)]);

            $shifts = $teachesAtBothBranches
                ? [$home->id => [Shift::Morning], $other->id => [Shift::Afternoon]]
                : [$home->id => [Shift::Morning, Shift::Afternoon]];

            $teacher->campuses()->syncWithoutDetaching(array_keys($shifts));

            return ['teacher' => $teacher, 'speciality' => $speciality, 'shifts' => $shifts];
        });
    }
}
