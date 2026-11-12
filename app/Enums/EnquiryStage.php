<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The admission step a registered enquiry waits for, in order: the age of the child is approved, then the fee
 * dues of the family, then the documents are checked, then the child passes the entry test, and the child is
 * ready to be admitted.
 */
enum EnquiryStage: string implements HasColor, HasLabel
{
    case AgeApproval = 'age_approval';
    case DefaulterApproval = 'defaulter_approval';
    case DocumentReview = 'document_review';
    case EntryTest = 'entry_test';
    case ReadyToAdmit = 'ready_to_admit';

    public function getLabel(): string
    {
        return match ($this) {
            self::AgeApproval => __('Age approval'),
            self::DefaulterApproval => __('Fee defaulter check'),
            self::DocumentReview => __('Document review'),
            self::EntryTest => __('Entry test'),
            self::ReadyToAdmit => __('Ready to admit'),
        };
    }

    public function getColor(): string
    {
        return $this === self::ReadyToAdmit ? 'success' : 'warning';
    }

    /**
     * The step that follows this one, or null after the last.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::AgeApproval => self::DefaulterApproval,
            self::DefaulterApproval => self::DocumentReview,
            self::DocumentReview => self::EntryTest,
            self::EntryTest => self::ReadyToAdmit,
            self::ReadyToAdmit => null,
        };
    }

    /**
     * Whether the step is a policy decision taken by staff with the approval permission.
     */
    public function needsApprover(): bool
    {
        return in_array($this, [self::AgeApproval, self::DefaulterApproval], true);
    }
}
