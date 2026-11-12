<?php

namespace App\Services;

use App\Enums\ApplicantStage;
use App\Enums\EmployeeStatus;
use App\Enums\OpeningStatus;
use App\Models\EmployeeProfile;
use App\Models\JobApplicant;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The steps of a job applicant: applied, shortlisted, interview, offered, hired. An applicant can be rejected at
 * any step before being hired. Hiring makes the person an employee with an employee file.
 */
class RecruitmentService
{
    /**
     * @throws DomainException
     */
    public function shortlist(JobApplicant $applicant): JobApplicant
    {
        $this->assertStage($applicant, [ApplicantStage::Applied]);

        $applicant->update(['stage' => ApplicantStage::Shortlisted]);

        return $applicant;
    }

    /**
     * Plans the interview of a shortlisted applicant, or moves an interview that was planned already.
     *
     * @throws DomainException
     */
    public function scheduleInterview(JobApplicant $applicant, CarbonInterface $at, ?User $interviewer = null): JobApplicant
    {
        $this->assertStage($applicant, [ApplicantStage::Shortlisted, ApplicantStage::Interview]);

        $applicant->update(['stage' => ApplicantStage::Interview, 'interview_at' => $at, 'interviewer_id' => $interviewer?->id]);

        return $applicant;
    }

    /**
     * Records how the interview went: a rating from 1 to 5 and notes.
     *
     * @throws DomainException
     */
    public function recordInterview(JobApplicant $applicant, int $rating, ?string $notes = null): JobApplicant
    {
        $this->assertStage($applicant, [ApplicantStage::Interview]);

        if ($rating < 1 || $rating > 5) {
            throw new DomainException(__('The rating goes from 1 to 5.'));
        }

        $applicant->update(['rating' => $rating, 'interview_notes' => $notes]);

        return $applicant;
    }

    /**
     * Offers the job to an applicant who was interviewed and rated.
     *
     * @throws DomainException
     */
    public function offer(JobApplicant $applicant, int $salary): JobApplicant
    {
        $this->assertStage($applicant, [ApplicantStage::Interview]);

        if ($applicant->rating === null) {
            throw new DomainException(__('Record the interview before making an offer.'));
        }

        if ($salary <= 0) {
            throw new DomainException(__('The salary must be more than zero.'));
        }

        $applicant->update(['stage' => ApplicantStage::Offered, 'offered_salary' => $salary]);

        return $applicant;
    }

    /**
     * @throws DomainException
     */
    public function reject(JobApplicant $applicant, ?string $reason = null): JobApplicant
    {
        if (! $applicant->stage->isOpen()) {
            throw new DomainException(__('This application is closed already.'));
        }

        $applicant->update(['stage' => ApplicantStage::Rejected, 'rejection_reason' => $reason]);

        return $applicant;
    }

    /**
     * Hires an applicant who accepted the offer: the person gets a user account without access to the staff
     * screens, and an employee file with the offered salary. When every position of the opening is filled, the
     * opening closes.
     *
     * @throws DomainException
     */
    public function hire(JobApplicant $applicant, CarbonInterface $joiningOn): EmployeeProfile
    {
        $this->assertStage($applicant, [ApplicantStage::Offered]);

        $opening = $applicant->opening;

        if ($opening->hiredCount() >= $opening->positions) {
            throw new DomainException(__('Every position of this opening is filled already.'));
        }

        if (filled($applicant->email) && User::query()->where('email', Str::lower($applicant->email))->exists()) {
            throw new DomainException(__('A person with the email :email exists already. Change the email of the applicant first.', ['email' => $applicant->email]));
        }

        return DB::transaction(function () use ($applicant, $opening, $joiningOn): EmployeeProfile {
            $user = User::query()->create([
                'firstname' => $applicant->firstname,
                'lastname' => $applicant->lastname,
                'email' => $applicant->email,
                'password' => Hash::make(Str::random(32)),
            ]);

            $profile = EmployeeProfile::query()->create([
                'user_id' => $user->id,
                'campus_id' => $opening->campus_id,
                'designation' => $opening->title,
                'department' => $opening->department,
                'employment_type' => $opening->employment_type,
                'status' => EmployeeStatus::Active,
                'joined_on' => $joiningOn->toDateString(),
                'cnic' => $applicant->cnic,
                'phone' => $applicant->phone,
                'basic_salary' => $applicant->offered_salary,
            ]);

            $applicant->update(['stage' => ApplicantStage::Hired, 'employee_profile_id' => $profile->id]);

            if ($opening->hiredCount() >= $opening->positions) {
                $opening->update(['status' => OpeningStatus::Closed]);
            }

            return $profile;
        });
    }

    /**
     * How many applicants of an opening stand at each step.
     *
     * @return array<string, int>
     */
    public function funnel(?int $openingId = null, ?User $user = null): array
    {
        $counts = JobApplicant::query()
            ->visibleTo($user)
            ->when($openingId !== null, fn ($query) => $query->where('job_opening_id', $openingId))
            ->get(['stage'])
            ->countBy(fn (JobApplicant $applicant): string => $applicant->stage->value);

        return collect(ApplicantStage::cases())
            ->mapWithKeys(fn (ApplicantStage $stage): array => [$stage->value => (int) ($counts[$stage->value] ?? 0)])
            ->all();
    }

    /**
     * @param  list<ApplicantStage>  $allowed
     *
     * @throws DomainException
     */
    private function assertStage(JobApplicant $applicant, array $allowed): void
    {
        if (! in_array($applicant->stage, $allowed, true)) {
            throw new DomainException(__('This step is not possible for an applicant who is :stage.', ['stage' => Str::lower($applicant->stage->getLabel())]));
        }
    }
}
