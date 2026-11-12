<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\EnquiryStage;
use App\Enums\EnquiryStatus;
use App\Models\Admission;
use App\Models\AdmissionEnquiry;
use App\Models\Contact;
use App\Models\EnquiryActivity;
use App\Models\FeeVoucher;
use App\Models\PhoneNumber;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The admission funnel. The front desk follows up on an enquiry until the family registers; the registered enquiry
 * then goes through the admission steps in order:
 *
 * 1. Age approval: the age of the child at the start of the academic year is approved (enquiries.approve).
 * 2. Fee defaulter check: the dues of a brother or sister at the school are checked and approved (enquiries.approve).
 * 3. Document review: the required documents are received.
 * 4. Entry test: the child passes the entry test.
 * 5. Ready to admit: the child is admitted to a section with a free seat, which creates the student, their guardian
 *    and the admission in one go.
 */
class AdmissionEnquiryService
{
    public function log(AdmissionEnquiry $enquiry, string $note, ?User $user): EnquiryActivity
    {
        return $enquiry->activities()->create(['note' => $note, 'user_id' => $user?->id]);
    }

    public function register(AdmissionEnquiry $enquiry, ?User $user): AdmissionEnquiry
    {
        if (! $enquiry->status->isOpen()) {
            throw new DomainException(__('Only an enquiry still followed up can be registered.'));
        }

        $enquiry->update(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::AgeApproval]);
        $this->log($enquiry, __('Registered. Next step: :step.', ['step' => EnquiryStage::AgeApproval->getLabel()]), $user);

        return $enquiry;
    }

    /**
     * The age of the child at the start of the academic year, in months, or null without a birthdate.
     */
    public function ageInMonths(AdmissionEnquiry $enquiry): ?int
    {
        if ($enquiry->birthdate === null) {
            return null;
        }

        $at = $enquiry->year?->start ?? now();

        return (int) $enquiry->birthdate->diffInMonths($at);
    }

    /**
     * The age of the child at the start of the academic year, such as "5 years 2 months".
     */
    public function ageLabel(AdmissionEnquiry $enquiry): ?string
    {
        $months = $this->ageInMonths($enquiry);

        return $months === null ? null : __(':years years :months months', ['years' => intdiv($months, 12), 'months' => $months % 12]);
    }

    /**
     * What the family of the brother or sister at the school owes on vouchers past their due date.
     */
    public function siblingDues(AdmissionEnquiry $enquiry): int
    {
        $sibling = $enquiry->siblingAdmission;

        if ($sibling === null) {
            return 0;
        }

        $overdue = FeeVoucher::query()
            ->whereIn('admission_id', Admission::query()->where('student_id', $sibling->student_id)->select('id'))
            ->whereDate('due_date', '<', now()->toDateString());

        return max(0, (int) (clone $overdue)->sum('net_amount') - (int) (clone $overdue)->sum('paid_amount'));
    }

    /**
     * The required documents not received yet.
     *
     * @return list<string>
     */
    public function missingDocuments(AdmissionEnquiry $enquiry): array
    {
        $received = $enquiry->documents_received ?? [];

        return array_values(array_filter(
            array_keys(array_filter(AdmissionEnquiry::DOCUMENTS)),
            fn (string $document): bool => ! in_array($document, $received, true),
        ));
    }

    /**
     * Whether the user may complete the current step of the enquiry.
     */
    public function canCompleteStep(AdmissionEnquiry $enquiry, ?User $user): bool
    {
        if ($user === null || $enquiry->status !== EnquiryStatus::Registered || $enquiry->stage === null || $enquiry->stage === EnquiryStage::ReadyToAdmit) {
            return false;
        }

        return $user->can($enquiry->stage->needsApprover() ? 'enquiries.approve' : 'enquiries.manage');
    }

    /**
     * Why the current step cannot be completed yet, or null when it can.
     */
    public function blockingReason(AdmissionEnquiry $enquiry): ?string
    {
        return match ($enquiry->stage) {
            EnquiryStage::DocumentReview => $this->missingDocuments($enquiry) === []
                ? null
                : __('Documents missing: :documents.', ['documents' => collect($this->missingDocuments($enquiry))->map(fn (string $document): string => AdmissionEnquiry::documentOptions()[$document])->join(', ')]),
            EnquiryStage::EntryTest => $enquiry->test_passed === true ? null : __('The child has not passed the entry test yet.'),
            default => null,
        };
    }

    /**
     * Completes the current step and moves the enquiry to the next one.
     */
    public function completeStep(AdmissionEnquiry $enquiry, ?User $user, ?string $note = null): AdmissionEnquiry
    {
        if (! $this->canCompleteStep($enquiry, $user)) {
            throw new DomainException(__('You cannot complete this step.'));
        }

        if (($reason = $this->blockingReason($enquiry)) !== null) {
            throw new DomainException($reason);
        }

        $step = $enquiry->stage;
        $enquiry->update(['stage' => $step->next()]);

        $this->log($enquiry, trim(__(':step done. Next step: :next.', ['step' => $step->getLabel(), 'next' => $step->next()->getLabel()]).' '.$note), $user);

        return $enquiry;
    }

    public function scheduleTest(AdmissionEnquiry $enquiry, CarbonInterface $at, ?User $user): AdmissionEnquiry
    {
        $enquiry->update(['test_at' => $at, 'test_passed' => null, 'test_remarks' => null]);
        $this->log($enquiry, __('Entry test scheduled on :date.', ['date' => $at->format('d M Y H:i')]), $user);

        return $enquiry;
    }

    public function recordTestResult(AdmissionEnquiry $enquiry, bool $passed, ?string $remarks, ?User $user): AdmissionEnquiry
    {
        $enquiry->update(['test_passed' => $passed, 'test_remarks' => $remarks]);
        $this->log($enquiry, trim(($passed ? __('Passed the entry test.') : __('Did not pass the entry test.')).' '.$remarks), $user);

        return $enquiry;
    }

    /**
     * The seats left in the section, or null when the section has no capacity set.
     */
    public function seatsLeft(Section $section): ?int
    {
        if ($section->capacity === null) {
            return null;
        }

        return max(0, $section->capacity - $section->admissions()->where('status', AdmissionStatus::Active)->count());
    }

    /**
     * Admits the child to a section of the class, branch and academic year of the enquiry: creates the student with
     * their guardian as contact, and the admission.
     */
    public function admit(AdmissionEnquiry $enquiry, Section $section, ?User $user): Admission
    {
        if ($enquiry->status !== EnquiryStatus::Registered || $enquiry->stage !== EnquiryStage::ReadyToAdmit) {
            throw new DomainException(__('The enquiry has not completed the admission steps.'));
        }

        if ($section->campus_id !== $enquiry->campus_id || $section->school_class_id !== $enquiry->school_class_id || $section->year_id !== $enquiry->year_id) {
            throw new DomainException(__('The section is not in the class, branch and academic year of the enquiry.'));
        }

        if ($this->seatsLeft($section) === 0) {
            throw new DomainException(__('This section is full.'));
        }

        return DB::transaction(function () use ($enquiry, $section, $user): Admission {
            $studentUser = User::query()->create([
                'firstname' => $enquiry->student_firstname,
                'lastname' => $enquiry->student_lastname,
                'email' => null,
                'password' => bcrypt(Str::random(16)),
            ]);

            $student = Student::query()->create([
                'id' => $studentUser->id,
                'birthdate' => $enquiry->birthdate?->toDateString(),
                'gender_id' => $enquiry->gender_id,
                'address' => $enquiry->address,
            ]);

            $guardianName = trim($enquiry->guardian_name);
            $lastSpace = strrpos($guardianName, ' ');
            $guardian = Contact::query()->create([
                'student_id' => $student->id,
                'firstname' => $lastSpace === false ? $guardianName : substr($guardianName, 0, $lastSpace),
                'lastname' => $lastSpace === false ? '' : substr($guardianName, $lastSpace + 1),
                'email' => $enquiry->guardian_email,
                'address' => $enquiry->address,
            ]);

            PhoneNumber::query()->create([
                'phoneable_id' => $guardian->id,
                'phoneable_type' => Contact::class,
                'phone_number' => $enquiry->guardian_phone,
            ]);

            $admission = Admission::query()->create([
                'student_id' => $student->id,
                'campus_id' => $section->campus_id,
                'school_class_id' => $section->school_class_id,
                'section_id' => $section->id,
                'year_id' => $section->year_id,
                'admitted_on' => now()->toDateString(),
                'status' => AdmissionStatus::Active,
            ]);

            $enquiry->update(['status' => EnquiryStatus::Admitted, 'stage' => null, 'admission_id' => $admission->id]);
            $this->log($enquiry, __('Admitted to :section with admission number :number.', ['section' => $section->full_name, 'number' => $admission->admission_number]), $user);

            return $admission;
        });
    }

    /**
     * The seats of each class at each branch for the academic year: the capacity of its sections, the students
     * admitted, the registered enquiries still in the funnel, and the seats left. Classes with a section without
     * capacity have no capacity and no seats left.
     *
     * @return Collection<int, array{campus: string, class: string, capacity: ?int, admitted: int, inFunnel: int, seatsLeft: ?int}>
     */
    public function seatPlan(?User $user, ?Year $year): Collection
    {
        if ($year === null) {
            return collect();
        }

        $inFunnel = AdmissionEnquiry::query()
            ->visibleTo($user)
            ->where('year_id', $year->id)
            ->where('status', EnquiryStatus::Registered)
            ->get(['campus_id', 'school_class_id'])
            ->countBy(fn (AdmissionEnquiry $enquiry): string => $enquiry->campus_id.'-'.$enquiry->school_class_id);

        return Section::query()
            ->visibleTo($user)
            ->where('year_id', $year->id)
            ->with(['campus', 'schoolClass'])
            ->withCount(['admissions as active_count' => fn ($admissions) => $admissions->where('status', AdmissionStatus::Active)])
            ->get()
            ->groupBy(fn (Section $section): string => $section->campus_id.'-'.$section->school_class_id)
            ->map(function (Collection $sections, string $key) use ($inFunnel): array {
                $first = $sections->first();
                $capacity = $sections->contains(fn (Section $section): bool => $section->capacity === null) ? null : (int) $sections->sum('capacity');
                $admitted = (int) $sections->sum('active_count');

                return [
                    'campus' => $first->campus->name,
                    'class' => $first->schoolClass->name,
                    'order' => $first->campus->name.str_pad((string) $first->schoolClass->order, 4, '0', STR_PAD_LEFT),
                    'capacity' => $capacity,
                    'admitted' => $admitted,
                    'inFunnel' => (int) ($inFunnel[$key] ?? 0),
                    'seatsLeft' => $capacity === null ? null : max(0, $capacity - $admitted),
                ];
            })
            ->sortBy('order')
            ->map(fn (array $row): array => collect($row)->except('order')->all())
            ->values();
    }

    /**
     * The enquiries of the academic year, how many registered (including those admitted since), how many were
     * admitted, and the share of enquiries that led to an admission.
     *
     * @return array{enquiries: int, registered: int, admitted: int, conversion: ?float}
     */
    public function summary(?User $user, ?Year $year): array
    {
        $statuses = AdmissionEnquiry::query()->visibleTo($user)->where('year_id', $year?->id)->pluck('status');
        $total = $statuses->count();
        $admitted = $statuses->filter(fn (EnquiryStatus $status): bool => $status === EnquiryStatus::Admitted)->count();

        return [
            'enquiries' => $total,
            'registered' => $statuses->filter(fn (EnquiryStatus $status): bool => in_array($status, [EnquiryStatus::Registered, EnquiryStatus::Admitted], true))->count(),
            'admitted' => $admitted,
            'conversion' => $total > 0 ? round($admitted * 100 / $total, 1) : null,
        ];
    }
}
