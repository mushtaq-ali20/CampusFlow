<?php

namespace App\Filament\Resources\AdmissionEnquiries\Pages;

use App\Enums\EnquiryStage;
use App\Enums\EnquiryStatus;
use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\AdmissionEnquiry;
use App\Models\Section;
use App\Services\AdmissionEnquiryService;
use App\Services\FeeService;
use App\Services\WhatsAppService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/**
 * An enquiry with its details, and the actions of the admission funnel in the header: follow-up, registration,
 * the admission steps, the entry test and the admission.
 */
class EditAdmissionEnquiry extends EditRecord
{
    protected static string $resource = AdmissionEnquiryResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->enquiry()->student_name.' · '.$this->enquiry()->enquiry_number;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $enquiry = $this->enquiry();

        return $enquiry->status === EnquiryStatus::Registered
            ? __('Registered · waiting for: :step', ['step' => $enquiry->stage?->getLabel()])
            : $enquiry->status->getLabel();
    }

    protected function enquiry(): AdmissionEnquiry
    {
        /** @var AdmissionEnquiry $enquiry */
        $enquiry = $this->getRecord();

        return $enquiry;
    }

    protected function service(): AdmissionEnquiryService
    {
        return app(AdmissionEnquiryService::class);
    }

    protected function canManage(): bool
    {
        return auth()->user()?->can('enquiries.manage') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->followUpAction(),
            $this->registerAction(),
            $this->completeStepAction(),
            $this->scheduleTestAction(),
            $this->testResultAction(),
            $this->admitAction(),
            ActionGroup::make([
                Action::make('whatsappGuardian')
                    ->label(__('WhatsApp guardian'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (): string => app(WhatsAppService::class)->link($this->enquiry()->guardian_phone, __('Dear :name,', ['name' => $this->enquiry()->guardian_name])), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => app(WhatsAppService::class)->normalize($this->enquiry()->guardian_phone) !== null),
                Action::make('viewAdmission')
                    ->label(__('Open admission'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->url(fn (): ?string => $this->enquiry()->admission_id ? AdmissionResource::getUrl('edit', ['record' => $this->enquiry()->admission_id]) : null)
                    ->visible(fn (): bool => $this->enquiry()->admission_id !== null),
                DeleteAction::make(),
            ]),
        ];
    }

    protected function followUpAction(): Action
    {
        return Action::make('followUp')
            ->label(__('Log follow-up'))
            ->icon(Heroicon::OutlinedPhone)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage() && $this->enquiry()->status !== EnquiryStatus::Admitted)
            ->schema([
                Textarea::make('note')
                    ->label(__('What was said'))
                    ->required(),
                Select::make('status')
                    ->label(__('Status'))
                    ->options(EnquiryStatus::followUpOptions())
                    ->default(fn (): string => $this->enquiry()->status->value)
                    ->visible(fn (): bool => $this->enquiry()->status->isOpen() || $this->enquiry()->status === EnquiryStatus::NotInterested),
                DatePicker::make('next_follow_up_on')
                    ->label(__('Next follow-up'))
                    ->minDate(today()),
            ])
            ->action(function (array $data): void {
                $enquiry = $this->enquiry();
                $enquiry->update(array_filter([
                    'status' => $data['status'] ?? null,
                    'next_follow_up_on' => $data['next_follow_up_on'] ?? null,
                ], fn ($value): bool => $value !== null));
                $this->service()->log($enquiry, $data['note'], auth()->user());

                $this->refreshFormData(['status', 'next_follow_up_on']);
                Notification::make()->title(__('Follow-up saved'))->success()->send();
            });
    }

    protected function registerAction(): Action
    {
        return Action::make('register')
            ->label(__('Register'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (): bool => $this->canManage() && $this->enquiry()->status->isOpen())
            ->requiresConfirmation()
            ->modalDescription(__('The family has registered: the enquiry goes through the admission steps, starting with the age approval.'))
            ->action(function (): void {
                $this->service()->register($this->enquiry(), auth()->user());
                Notification::make()->title(__('Enquiry registered'))->success()->send();
            });
    }

    protected function completeStepAction(): Action
    {
        return Action::make('completeStep')
            ->label(fn (): string => match ($this->enquiry()->stage) {
                EnquiryStage::AgeApproval => __('Approve age'),
                EnquiryStage::DefaulterApproval => __('Approve fee check'),
                EnquiryStage::DocumentReview => __('Documents complete'),
                EnquiryStage::EntryTest => __('Test passed'),
                default => __('Complete step'),
            })
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('success')
            ->visible(fn (): bool => $this->service()->canCompleteStep($this->enquiry(), auth()->user()))
            ->modalHeading(fn (): string => $this->enquiry()->stage?->getLabel() ?? '')
            ->modalDescription(fn (): string => $this->stepDescription())
            ->schema([
                TextInput::make('note')
                    ->label(__('Note'))
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                try {
                    $this->service()->completeStep($this->enquiry(), auth()->user(), $data['note'] ?? null);
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Next step: :step', ['step' => $this->enquiry()->stage->getLabel()]))->success()->send();
            });
    }

    /**
     * What the approver needs to know about the current step.
     */
    protected function stepDescription(): string
    {
        $enquiry = $this->enquiry();
        $service = $this->service();

        return match ($enquiry->stage) {
            EnquiryStage::AgeApproval => __('Age at the start of the academic year: :age.', ['age' => $service->ageLabel($enquiry) ?? __('date of birth not given')]),
            EnquiryStage::DefaulterApproval => $enquiry->sibling_admission_id === null
                ? __('No brother or sister at the school.')
                : __('Overdue fees of the brother or sister: :amount.', ['amount' => FeeService::money($service->siblingDues($enquiry))]),
            default => $service->blockingReason($enquiry) ?? __('Everything needed for this step is in.'),
        };
    }

    protected function scheduleTestAction(): Action
    {
        return Action::make('scheduleTest')
            ->label(fn (): string => $this->enquiry()->test_at ? __('Reschedule test') : __('Schedule test'))
            ->icon(Heroicon::OutlinedCalendar)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage() && $this->enquiry()->stage === EnquiryStage::EntryTest)
            ->schema([
                DateTimePicker::make('test_at')
                    ->label(__('Test date and time'))
                    ->seconds(false)
                    ->required(),
            ])
            ->fillForm(fn (): array => ['test_at' => $this->enquiry()->test_at])
            ->action(function (array $data): void {
                $this->service()->scheduleTest($this->enquiry(), Carbon::parse($data['test_at']), auth()->user());
                Notification::make()->title(__('Entry test scheduled'))->success()->send();
            });
    }

    protected function testResultAction(): Action
    {
        return Action::make('testResult')
            ->label(__('Record test result'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage() && $this->enquiry()->stage === EnquiryStage::EntryTest && $this->enquiry()->test_at !== null)
            ->schema([
                Toggle::make('passed')
                    ->label(__('Passed'))
                    ->default(true),
                TextInput::make('remarks')
                    ->label(__('Remarks'))
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->service()->recordTestResult($this->enquiry(), (bool) $data['passed'], $data['remarks'] ?? null, auth()->user());
                Notification::make()->title(__('Test result saved'))->success()->send();
            });
    }

    protected function admitAction(): Action
    {
        return Action::make('admit')
            ->label(__('Admit'))
            ->icon(Heroicon::OutlinedAcademicCap)
            ->color('success')
            ->visible(fn (): bool => ($this->canManage() && (auth()->user()?->can('admissions.manage') ?? false)) && $this->enquiry()->stage === EnquiryStage::ReadyToAdmit)
            ->modalDescription(__('Creates the student with their guardian as contact, and the admission.'))
            ->schema([
                Select::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => $this->sectionOptions())
                    ->disableOptionWhen(fn (string $label): bool => str_contains($label, __('full')))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $section = Section::query()->visibleTo(auth()->user())->findOrFail($data['section_id']);

                try {
                    $admission = $this->service()->admit($this->enquiry(), $section, auth()->user());
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Admitted with admission number :number', ['number' => $admission->admission_number]))->success()->send();
                $this->redirect(AdmissionResource::getUrl('edit', ['record' => $admission]));
            });
    }

    /**
     * The sections of the class, branch and academic year of the enquiry, with their seats left.
     *
     * @return array<int, string>
     */
    protected function sectionOptions(): array
    {
        $enquiry = $this->enquiry();

        return Section::query()
            ->visibleTo(auth()->user())
            ->where('campus_id', $enquiry->campus_id)
            ->where('school_class_id', $enquiry->school_class_id)
            ->where('year_id', $enquiry->year_id)
            ->with('schoolClass')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Section $section): array {
                $seats = $this->service()->seatsLeft($section);

                return [$section->id => $section->full_name.' · '.match (true) {
                    $seats === null => __('no seat limit'),
                    $seats === 0 => __('full'),
                    default => __(':count seats left', ['count' => $seats]),
                }];
            })
            ->all();
    }
}
