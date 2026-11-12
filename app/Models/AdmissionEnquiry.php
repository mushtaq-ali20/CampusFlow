<?php

namespace App\Models;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStage;
use App\Enums\EnquiryStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\AdmissionEnquiryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A family asking for a seat for their child in a class at a branch for an academic year, from the first contact
 * to the admission. See App\Services\AdmissionEnquiryService for the admission steps.
 */
class AdmissionEnquiry extends Model
{
    /** @use HasFactory<AdmissionEnquiryFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    /**
     * The documents checked in the document review, and whether each one is required to go on.
     *
     * @var array<string, bool>
     */
    public const DOCUMENTS = [
        'birth_certificate' => true,
        'photographs' => true,
        'guardian_cnic' => true,
        'previous_result' => false,
        'leaving_certificate' => false,
    ];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (AdmissionEnquiry $enquiry): void {
            $enquiry->enquiry_number ??= static::nextEnquiryNumber($enquiry->campus_id, $enquiry->year_id);
        });
    }

    /**
     * The number is made of the branch code, the start of the academic year and a sequence: ENQ-MAIN-2026-0001.
     */
    public static function nextEnquiryNumber(int $campusId, int $yearId): string
    {
        $prefix = 'ENQ-'.(Campus::query()->find($campusId)?->code ?? 'ADM').'-'.(Year::query()->find($yearId)?->start?->year ?? now()->year);
        $sequence = static::query()->where('campus_id', $campusId)->where('year_id', $yearId)->count() + 1;

        while (static::query()->where('enquiry_number', $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT))->exists()) {
            $sequence++;
        }

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    public static function documentOptions(): array
    {
        return [
            'birth_certificate' => __('B-Form or birth certificate'),
            'photographs' => __('Photographs'),
            'guardian_cnic' => __('Guardian CNIC copy'),
            'previous_result' => __('Previous result card'),
            'leaving_certificate' => __('School leaving certificate'),
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * The admission of a brother or sister already at the school, whose fee dues are checked.
     */
    public function siblingAdmission(): BelongsTo
    {
        return $this->belongsTo(Admission::class, 'sibling_admission_id');
    }

    /**
     * The admission the enquiry led to.
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EnquiryActivity::class);
    }

    protected function studentName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->student_firstname.' '.$this->student_lastname));
    }

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'source' => EnquirySource::class,
            'status' => EnquiryStatus::class,
            'stage' => EnquiryStage::class,
            'documents_received' => 'array',
            'test_at' => 'datetime',
            'test_passed' => 'boolean',
            'next_follow_up_on' => 'date',
            'gender_id' => 'integer',
        ];
    }
}
