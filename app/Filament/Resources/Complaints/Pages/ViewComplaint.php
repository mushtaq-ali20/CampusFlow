<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Concerns\ShowsRecordHeader;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Models\Complaint;
use App\Models\Student;
use App\Models\User;
use App\Services\ComplaintService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A complaint with its history. Its assignee and the staff who manage complaints comment on it and resolve it; the
 * staff who manage complaints also give it to someone else and reopen it.
 */
class ViewComplaint extends ViewRecord
{
    use ShowsRecordHeader;

    protected static string $resource = ComplaintResource::class;

    protected function recordHeader(): array
    {
        $complaint = $this->complaint();

        return [
            'title' => $complaint->subject,
            'subtitle' => collect([$complaint->complaint_number, $complaint->category?->name, $complaint->campus?->name])->filter()->implode(' · '),
            'badge' => ['label' => $complaint->status->getLabel(), 'color' => $complaint->status->getColor()],
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->complaint()->complaint_number.' · '.$this->complaint()->subject;
    }

    protected function complaint(): Complaint
    {
        /** @var Complaint $complaint */
        $complaint = $this->getRecord();

        return $complaint;
    }

    protected function service(): ComplaintService
    {
        return app(ComplaintService::class);
    }

    protected function canManage(): bool
    {
        return auth()->user()?->can('complaints.manage') ?? false;
    }

    protected function canWorkOn(): bool
    {
        return $this->canManage() || (int) $this->complaint()->assigned_to === (int) auth()->id();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('comment')
                ->label(__('Add update'))
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->color('gray')
                ->visible(fn (): bool => $this->canWorkOn() && $this->complaint()->status->isOpen())
                ->schema([
                    Textarea::make('note')->label(__('Update'))->required(),
                ])
                ->action(function (array $data): void {
                    $this->service()->comment($this->complaint(), $data['note'], auth()->user());
                    $this->refreshRelationManagers();
                    Notification::make()->title(__('Update added'))->success()->send();
                }),
            Action::make('assign')
                ->label(__('Assign'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->visible(fn (): bool => $this->canManage() && $this->complaint()->status->isOpen())
                ->schema([
                    Select::make('assigned_to')
                        ->label(__('Assign to'))
                        ->options(fn (): array => ComplaintResource::staffOptions())
                        ->searchable()
                        ->required(),
                    Textarea::make('note')->label(__('Note')),
                ])
                ->action(function (array $data): void {
                    $this->service()->assign($this->complaint(), User::query()->findOrFail($data['assigned_to']), auth()->user(), $data['note'] ?? null);
                    $this->refreshRelationManagers();
                    Notification::make()->title(__('Complaint assigned'))->success()->send();
                }),
            Action::make('resolve')
                ->label(__('Resolve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->canWorkOn() && $this->complaint()->status->isOpen())
                ->schema([
                    Textarea::make('resolution')->label(__('How it was resolved'))->required(),
                ])
                ->action(function (array $data): void {
                    $this->service()->resolve($this->complaint(), $data['resolution'], auth()->user());
                    $this->refreshRelationManagers();
                    Notification::make()->title(__('Complaint resolved'))->success()->send();
                }),
            SendWhatsAppAction::make()
                ->label(__('WhatsApp update to parent'))
                ->student(fn (): ?Student => $this->complaint()->student)
                ->message(fn (): string => $this->parentMessage())
                ->aboutRecord()
                ->visible(fn (): bool => $this->complaint()->student_id !== null && $this->canWorkOn() && (auth()->user()?->can('parents.message') ?? false)),
            ActionGroup::make([
                Action::make('reopen')
                    ->label(__('Reopen'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => $this->canManage() && ! $this->complaint()->status->isOpen())
                    ->schema([
                        Textarea::make('reason')->label(__('Why it is reopened'))->required(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $this->service()->reopen($this->complaint(), $data['reason'], auth()->user());
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        $this->refreshRelationManagers();
                        Notification::make()->title(__('Complaint reopened'))->success()->send();
                    }),
                EditAction::make()
                    ->label(__('Edit details'))
                    ->visible(fn (): bool => $this->canManage()),
            ]),
        ];
    }

    /**
     * The WhatsApp message about the complaint for the parents.
     */
    protected function parentMessage(): string
    {
        $complaint = $this->complaint();

        $lines = [__('Dear parent,')];
        $lines[] = $complaint->status->isOpen()
            ? __('Your complaint :number (":subject") is being handled. We will get back to you by :date.', ['number' => $complaint->complaint_number, 'subject' => $complaint->subject, 'date' => $complaint->due_at->format('d M Y')])
            : __('Your complaint :number (":subject") has been resolved: :resolution', ['number' => $complaint->complaint_number, 'subject' => $complaint->subject, 'resolution' => $complaint->resolution]);
        $lines[] = (string) config('campusflow.company_name');

        return implode("\n\n", $lines);
    }

    protected function refreshRelationManagers(): void
    {
        $this->dispatch('refreshRelationManager');
    }
}
