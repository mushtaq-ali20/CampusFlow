<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\ClassAttendanceStatus;
use App\Enums\DisciplineConsequence;
use App\Enums\DisciplineKind;
use App\Enums\DiscountMode;
use App\Enums\DiscountType;
use App\Enums\ExamType;
use App\Enums\FeeFrequency;
use App\Enums\PaymentMethod;
use App\Models\Admission;
use App\Models\ClassAttendance;
use App\Models\Contact;
use App\Models\DisciplineCategory;
use App\Models\DisciplineRecord;
use App\Models\FeeDiscount;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeeVoucher;
use App\Models\PhoneNumber;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\SubjectResult;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Models\Year;
use App\Services\FeeService;
use App\Services\Settings;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Demo data for the whole school: the structure (SchoolSeeder), 150 admitted students with their guardians, marks
 * and attendance, the next academic year for promotion, the timetable of every section, fee structures, discounts,
 * monthly vouchers and payments, and discipline categories with a few merits and violations.
 */
class SchoolDemoSeeder extends Seeder
{
    private const STUDENTS = 150;

    /**
     * @var list<string>
     */
    private const BOYS = [
        'Muhammad Ahmed', 'Ali Hassan', 'Abdullah', 'Hamza', 'Ibrahim', 'Yusuf', 'Umar', 'Usman', 'Bilal', 'Zaid',
        'Hassan', 'Hussain', 'Talha', 'Saad', 'Arham', 'Rayyan', 'Shayan', 'Haris', 'Musa', 'Zakariya',
        'Ayaan', 'Affan', 'Huzaifa', 'Moiz', 'Abdul Rehman', 'Danish', 'Faizan', 'Junaid', 'Taha', 'Owais',
        'Ismail', 'Yahya', 'Hammad', 'Anas', 'Salman', 'Fahad', 'Waleed', 'Muaz', 'Sufyan', 'Rohaan',
    ];

    /**
     * @var list<string>
     */
    private const GIRLS = [
        'Fatima', 'Ayesha', 'Maryam', 'Zainab', 'Khadija', 'Hafsa', 'Aleena', 'Eman', 'Hoorain', 'Anaya',
        'Inaya', 'Zoya', 'Sara', 'Amna', 'Aiza', 'Mahnoor', 'Haniya', 'Areeba', 'Iqra', 'Laiba',
        'Rida', 'Noor', 'Javeria', 'Sumayya', 'Ruqayya', 'Aminah', 'Hiba', 'Minahil', 'Dua', 'Aroob',
        'Umme Hani', 'Rameen', 'Manahil', 'Abeeha', 'Fizza', 'Hareem', 'Mishal', 'Wajiha', 'Zunaira', 'Kinza',
    ];

    /**
     * @var list<string>
     */
    private const FAMILY_NAMES = [
        'Khan', 'Ahmed', 'Ali', 'Malik', 'Qureshi', 'Siddiqui', 'Sheikh', 'Butt', 'Chaudhry', 'Raza',
        'Hussain', 'Iqbal', 'Akhtar', 'Javed', 'Farooq', 'Mirza', 'Abbasi', 'Hashmi', 'Rana', 'Awan',
    ];

    /**
     * @var list<string>
     */
    private const FATHERS = [
        'Muhammad Asif', 'Tariq Mehmood', 'Khalid Hussain', 'Shahid Iqbal', 'Naveed Akhtar', 'Zahid Ali', 'Imtiaz Ahmed',
        'Abdul Majeed', 'Rashid Mehmood', 'Arshad Javed', 'Nadeem Abbas', 'Sajid Farooq', 'Waseem Akram', 'Kashif Raza',
    ];

    public function run(): void
    {
        $this->call(SchoolSeeder::class);

        $year = Year::current();
        $this->nextYear($year);

        $this->feeSettings($year);
        $heads = $this->feeHeads();
        $this->feeStructures($year, $heads);
        $discounts = $this->discounts();

        $admissions = $this->admissions($year, $discounts);
        $this->results($admissions);
        $this->attendance($admissions);
        $this->timetable($year);
        $this->vouchersAndPayments($admissions, $year);
        $this->discipline($admissions);
    }

    /**
     * The usual merit and violation categories, and a few records for one student in six over the last weeks,
     * recorded by their class teacher.
     *
     * @param  Collection<int, Admission>  $admissions
     */
    private function discipline(Collection $admissions): void
    {
        $categories = collect([
            ['Helped a classmate', DisciplineKind::Merit, 2, null],
            ['Excellent homework', DisciplineKind::Merit, 1, null],
            ['Class monitor duties', DisciplineKind::Merit, 3, null],
            ['Late to class', DisciplineKind::Violation, 1, DisciplineConsequence::Noted],
            ['Uniform incomplete', DisciplineKind::Violation, 1, DisciplineConsequence::Noted],
            ['Homework not done', DisciplineKind::Violation, 2, DisciplineConsequence::WarningToParent],
            ['Disrespectful behaviour', DisciplineKind::Violation, 5, DisciplineConsequence::WarningToParent],
            ['Fighting', DisciplineKind::Violation, 10, DisciplineConsequence::ParentCalledIn],
        ])->map(fn (array $category): DisciplineCategory => DisciplineCategory::query()->firstOrCreate(
            ['name' => $category[0]],
            ['kind' => $category[1], 'points' => $category[2], 'consequence' => $category[3]],
        ));

        foreach ($admissions->filter(fn (Admission $admission, int $index): bool => $index % 6 === 0) as $admission) {
            if ($admission->disciplineRecords()->exists()) {
                continue;
            }

            foreach (range(1, random_int(1, 3)) as $ignored) {
                DisciplineRecord::query()->create([
                    'admission_id' => $admission->id,
                    'discipline_category_id' => $categories->random()->id,
                    'date' => now()->subDays(random_int(0, 30))->toDateString(),
                    'recorded_by' => $admission->section->class_teacher_id,
                ]);
            }
        }
    }

    /**
     * The next academic year with its sections, so that students who passed can be promoted.
     */
    private function nextYear(Year $year): void
    {
        $next = Year::query()->updateOrCreate(
            ['name' => ($year->start->year + 1).'-'.($year->start->year + 2)],
            ['start' => $year->start->copy()->addYear()->toDateString(), 'end' => $year->end->copy()->addYear()->toDateString()],
        );

        foreach (Section::query()->where('year_id', $year->id)->get() as $section) {
            Section::query()->firstOrCreate(
                ['campus_id' => $section->campus_id, 'school_class_id' => $section->school_class_id, 'year_id' => $next->id, 'shift' => $section->shift, 'name' => $section->name],
                ['capacity' => $section->capacity, 'class_teacher_id' => $section->class_teacher_id],
            );
        }
    }

    private function feeSettings(Year $year): void
    {
        app(Settings::class)->set([
            'fees.due_day' => 10,
            'fees.late_fine' => 200,
            'fees.annual_fee_month' => $year->start->month,
        ]);
    }

    /**
     * @return array{tuition: FeeHead, admission: FeeHead, annual: FeeHead, exam: FeeHead}
     */
    private function feeHeads(): array
    {
        return [
            'tuition' => FeeHead::query()->firstOrCreate(['name' => 'Tuition Fee'], ['frequency' => FeeFrequency::Monthly]),
            'admission' => FeeHead::query()->firstOrCreate(['name' => 'Admission Fee'], ['frequency' => FeeFrequency::OneTime]),
            'annual' => FeeHead::query()->firstOrCreate(['name' => 'Annual Fee'], ['frequency' => FeeFrequency::Annual]),
            'exam' => FeeHead::query()->firstOrCreate(['name' => 'Examination Fee'], ['frequency' => FeeFrequency::Annual]),
        ];
    }

    /**
     * Tuition grows with the class, from Rs 3,000 a month in Montessori to Rs 6,500 in Class 10.
     *
     * @param  array{tuition: FeeHead, admission: FeeHead, annual: FeeHead, exam: FeeHead}  $heads
     */
    private function feeStructures(Year $year, array $heads): void
    {
        foreach (SchoolClass::query()->ordered()->get() as $class) {
            $amounts = [
                'tuition' => 3000 + ($class->order - 1) * 250,
                'admission' => 5000,
                'annual' => 3000,
                'exam' => 1500,
            ];

            foreach ($amounts as $head => $amount) {
                FeeStructure::query()->updateOrCreate(
                    ['school_class_id' => $class->id, 'year_id' => $year->id, 'fee_head_id' => $heads[$head]->id, 'campus_id' => null],
                    ['amount' => $amount],
                );
            }
        }
    }

    /**
     * @return Collection<string, FeeDiscount>
     */
    private function discounts(): Collection
    {
        return collect([
            'sibling' => FeeDiscount::query()->firstOrCreate(['name' => 'Sibling discount 10%'], ['type' => DiscountType::Sibling, 'mode' => DiscountMode::Percent, 'value' => 10]),
            'merit' => FeeDiscount::query()->firstOrCreate(['name' => 'Merit scholarship 25%'], ['type' => DiscountType::Scholarship, 'mode' => DiscountMode::Percent, 'value' => 25]),
            'staff' => FeeDiscount::query()->firstOrCreate(['name' => 'Staff child Rs 1,000'], ['type' => DiscountType::Other, 'mode' => DiscountMode::Fixed, 'value' => 1000]),
        ]);
    }

    /**
     * Admits 150 students, spread evenly over the sections of the current academic year, each with a guardian.
     *
     * @param  Collection<string, FeeDiscount>  $discounts
     * @return Collection<int, Admission>
     */
    private function admissions(Year $year, Collection $discounts): Collection
    {
        $sections = Section::query()
            ->where('year_id', $year->id)
            ->with('schoolClass')
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%03d-%03d-%s-%s', $section->schoolClass->order, $section->campus_id, $section->shift->value, $section->name))
            ->values();

        $admissions = collect();

        for ($index = 0; $index < self::STUDENTS; $index++) {
            $section = $sections[$index % $sections->count()];
            $student = $this->student($index, $section->schoolClass->order, $year);

            $admission = Admission::query()->firstOrCreate(
                ['student_id' => $student->id, 'year_id' => $year->id],
                [
                    'campus_id' => $section->campus_id,
                    'school_class_id' => $section->school_class_id,
                    'section_id' => $section->id,
                    'admitted_on' => $year->start->toDateString(),
                    'status' => AdmissionStatus::Active,
                ],
            );

            $roll = random_int(1, 100);

            if ($roll <= 12) {
                $admission->discounts()->syncWithoutDetaching([$discounts['sibling']->id]);
            } elseif ($roll <= 18) {
                $admission->discounts()->syncWithoutDetaching([$discounts['merit']->id]);
            } elseif ($roll <= 21) {
                $admission->discounts()->syncWithoutDetaching([$discounts['staff']->id]);
            }

            $admissions->push($admission->load('schoolClass.subjects'));
        }

        return $admissions;
    }

    /**
     * A student with a Muslim name, of the age of the class, with the father as guardian. Boys and girls alternate.
     */
    private function student(int $index, int $classOrder, Year $year): Student
    {
        $isGirl = $index % 2 === 1;
        $names = $isGirl ? self::GIRLS : self::BOYS;
        $firstname = $names[intdiv($index, 2) % count($names)];
        $lastname = self::FAMILY_NAMES[($index * 7) % count(self::FAMILY_NAMES)];

        $user = User::query()->firstOrCreate(
            ['email' => sprintf('student%03d@campusflow.test', $index + 1)],
            ['firstname' => $firstname, 'lastname' => $lastname, 'password' => bcrypt('secret')],
        );

        $student = Student::query()->firstOrCreate(
            ['id' => $user->id],
            [
                'gender_id' => $isGirl ? 1 : 2,
                'birthdate' => $year->start->copy()->subYears(2 + $classOrder)->subDays(random_int(0, 360))->toDateString(),
            ],
        );

        if ($student->contacts()->doesntExist()) {
            $contact = Contact::query()->create([
                'student_id' => $student->id,
                'firstname' => self::FATHERS[$index % count(self::FATHERS)],
                'lastname' => $lastname,
                'relationship_id' => 1,
            ]);

            PhoneNumber::query()->create([
                'phoneable_id' => $contact->id,
                'phoneable_type' => Contact::class,
                'phone_number' => sprintf('03%02d-%07d', random_int(0, 49), random_int(1000000, 9999999)),
            ]);
        }

        return $student;
    }

    /**
     * Marks of the quarterly and half yearly exams for every student. For the annual exam, which decides the
     * promotion, most students pass every subject, a few fail one, and a few have no marks yet.
     *
     * @param  Collection<int, Admission>  $admissions
     */
    private function results(Collection $admissions): void
    {
        foreach ($admissions as $admission) {
            foreach ([ExamType::Quarterly, ExamType::HalfYearly] as $exam) {
                foreach ($admission->schoolClass->subjects as $subject) {
                    SubjectResult::query()->updateOrCreate(
                        ['admission_id' => $admission->id, 'subject_id' => $subject->id, 'exam' => $exam],
                        ['obtained_marks' => random_int(25, 96)],
                    );
                }
            }

            $roll = random_int(1, 100);

            if ($roll > 92) {
                continue;
            }

            $failing = $roll > 80 ? $admission->schoolClass->subjects->random() : null;

            foreach ($admission->schoolClass->subjects as $subject) {
                SubjectResult::query()->updateOrCreate(
                    ['admission_id' => $admission->id, 'subject_id' => $subject->id, 'exam' => ExamType::Annual],
                    ['obtained_marks' => $failing?->is($subject) ? random_int(8, 30) : random_int(40, 96)],
                );
            }
        }
    }

    /**
     * Attendance of the last 20 school days (Monday to Saturday).
     *
     * @param  Collection<int, Admission>  $admissions
     */
    private function attendance(Collection $admissions): void
    {
        $days = collect();
        $day = now()->startOfDay();

        while ($days->count() < 20) {
            if (! $day->isSunday() && $day->greaterThanOrEqualTo($admissions->first()->year->start)) {
                $days->push($day->copy());
            }

            $day->subDay();
        }

        $rows = [];

        foreach ($admissions as $admission) {
            foreach ($days as $date) {
                $roll = random_int(1, 100);

                $rows[] = [
                    'admission_id' => $admission->id,
                    'section_id' => $admission->section_id,
                    'date' => $date->toDateString(),
                    'status' => match (true) {
                        $roll <= 90 => ClassAttendanceStatus::Present->value,
                        $roll <= 95 => ClassAttendanceStatus::Late->value,
                        $roll <= 98 => ClassAttendanceStatus::Absent->value,
                        default => ClassAttendanceStatus::Leave->value,
                    },
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ClassAttendance::query()->insertOrIgnore($chunk);
        }
    }

    /**
     * The Monday to Saturday timetable of every section, in the periods of its shift. Each subject gets its weekly
     * lessons with the teacher assigned to it, and a teacher is never in two places at once. In each slot, the
     * subject with the most lessons left whose teacher is free is placed, avoiding a second lesson of a subject on
     * the same day where possible. A slot where no teacher is free stays empty.
     */
    private function timetable(Year $year): void
    {
        $periods = TimetablePeriod::query()->where('is_break', false)->ordered()->get()->groupBy(fn (TimetablePeriod $period): string => $period->shift->value);
        $busy = [];

        $sections = Section::query()
            ->where('year_id', $year->id)
            ->with(['schoolClass.subjects', 'teachingAssignments'])
            ->get();

        foreach ($sections as $section) {
            $teacherOf = $section->teachingAssignments->pluck('teacher_id', 'subject_id');
            $remaining = SchoolSeeder::weeklyLessons($section->shift, $section->schoolClass->subjects);

            foreach (SchoolSeeder::DAYS as $day) {
                $taughtToday = [];

                foreach ($periods[$section->shift->value] ?? [] as $period) {
                    $subjectId = collect($remaining)
                        ->filter(fn (int $left, int $subjectId): bool => $left > 0 && $teacherOf->has($subjectId) && ! isset($busy[$teacherOf[$subjectId].'-'.$period->id.'-'.$day]))
                        ->sortByDesc(fn (int $left, int $subjectId): int => $left - (isset($taughtToday[$subjectId]) ? 100 : 0))
                        ->keys()
                        ->first();

                    if ($subjectId === null) {
                        continue;
                    }

                    $teacherId = $teacherOf[$subjectId];
                    $busy[$teacherId.'-'.$period->id.'-'.$day] = true;
                    $remaining[$subjectId]--;
                    $taughtToday[$subjectId] = true;

                    TimetableEntry::query()->updateOrCreate(
                        ['section_id' => $section->id, 'timetable_period_id' => $period->id, 'day_of_week' => $day],
                        ['subject_id' => $subjectId, 'teacher_id' => $teacherId],
                    );
                }
            }
        }
    }

    /**
     * Vouchers from the start of the academic year to the current month. Most old vouchers are paid, some partly,
     * the rest are unpaid and get the late fine. The current month is partly paid.
     *
     * @param  Collection<int, Admission>  $admissions
     */
    private function vouchersAndPayments(Collection $admissions, Year $year): void
    {
        $service = app(FeeService::class);
        $month = $year->start->copy()->startOfMonth();
        $last = now()->startOfMonth();

        $collectors = User::query()->whereNotNull('campus_id')->get()->keyBy('campus_id');
        $methods = [PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::BankTransfer, PaymentMethod::Online, PaymentMethod::Cheque];

        while ($month->lessThanOrEqualTo($last)) {
            $isCurrentMonth = $month->equalTo($last);
            $service->generateForMonth($month);

            FeeVoucher::query()->whereDate('month', $month)->where('paid_amount', 0)->each(function (FeeVoucher $voucher) use ($service, $isCurrentMonth, $collectors, $methods): void {
                $roll = random_int(1, 100);
                $paidShare = $isCurrentMonth ? ($roll <= 35 ? 1 : ($roll <= 45 ? 0.5 : 0)) : ($roll <= 82 ? 1 : ($roll <= 90 ? 0.5 : 0));

                if ($paidShare === 0) {
                    return;
                }

                $paidOn = Carbon::parse($voucher->due_date)->subDays(random_int(0, 8));

                if ($paidOn->isFuture()) {
                    $paidOn = now();
                }

                $service->recordPayment(
                    $voucher,
                    (int) round($voucher->net_amount * $paidShare),
                    $methods[array_rand($methods)],
                    $paidOn,
                    null,
                    $collectors[$voucher->campus_id] ?? null,
                );
            });

            $month->addMonth();
        }

        $service->applyLateFines();
    }
}
