<?php

namespace App\Filament\Actions;

use App\Models\Student;
use App\Services\WhatsAppService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Prepares a WhatsApp message to the parents of a student. Staff choose the number among the student's contacts and
 * check the message; CampusFlow keeps a copy of it and gives a button that opens the chat in WhatsApp with the message
 * written, to send it from the school's WhatsApp.
 *
 * On a student, it messages their parents. On another record, tell it how to find the student and what to write:
 *
 *     SendWhatsAppAction::make()
 *         ->student(fn (DisciplineRecord $record): Student => $record->admission->student)
 *         ->message(fn (DisciplineRecord $record): string => '...')
 *         ->aboutRecord()
 */
class SendWhatsAppAction extends Action
{
    protected ?Closure $resolveStudentUsing = null;

    protected ?Closure $resolveMessageUsing = null;

    protected bool $isAboutRecord = false;

    protected ?Closure $resolveAboutUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'whatsappParent';
    }

    public function student(Closure $callback): static
    {
        $this->resolveStudentUsing = $callback;

        return $this;
    }

    public function message(Closure $callback): static
    {
        $this->resolveMessageUsing = $callback;

        return $this;
    }

    /**
     * Keeps the record the action is on as what the message was about.
     */
    public function aboutRecord(bool $condition = true): static
    {
        $this->isAboutRecord = $condition;

        return $this;
    }

    /**
     * Keeps the model the callback returns as what the message was about, for records that are not models, such as
     * the rows of a report.
     */
    public function aboutUsing(Closure $callback): static
    {
        $this->resolveAboutUsing = $callback;

        return $this;
    }

    public function getAbout(): ?Model
    {
        if ($this->resolveAboutUsing !== null) {
            return $this->evaluate($this->resolveAboutUsing);
        }

        return $this->isAboutRecord && $this->getRecord() instanceof Model ? $this->getRecord() : null;
    }

    public function getStudent(): ?Student
    {
        if ($this->resolveStudentUsing !== null) {
            return $this->evaluate($this->resolveStudentUsing);
        }

        $record = $this->getRecord();

        return $record instanceof Student ? $record : null;
    }

    public function getDefaultMessage(): string
    {
        if ($this->resolveMessageUsing !== null) {
            return (string) $this->evaluate($this->resolveMessageUsing);
        }

        return __('Dear parent of :student,', ['student' => $this->getStudent()?->name])."\n\n\n".config('campusflow.company_name');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('WhatsApp parent'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('parents.message') ?? false)
            ->modalHeading(fn (): string => __('WhatsApp message to the parents of :student', ['student' => $this->getStudent()?->name]))
            ->modalDescription(__('The message opens in WhatsApp, ready to send from the school\'s number. A copy is kept on the student\'s profile.'))
            ->modalSubmitActionLabel(__('Prepare message'))
            ->schema(fn (): array => [
                Select::make('phone')
                    ->label(__('Send to'))
                    ->options(fn (): array => $this->recipients())
                    ->helperText(fn (): ?string => $this->recipients() === [] ? __('This student has no phone number. Add one to a contact of the student first.') : null)
                    ->required(),
                Textarea::make('body')
                    ->label(__('Message'))
                    ->rows(7)
                    ->maxLength(1000)
                    ->required(),
            ])
            ->fillForm(fn (): array => [
                'phone' => array_key_first($this->recipients()),
                'body' => $this->getDefaultMessage(),
            ])
            ->action(function (array $data): void {
                $student = $this->getStudent();
                $recipients = $this->recipients();

                abort_unless($student !== null && isset($recipients[$data['phone']]), 404);

                $service = app(WhatsAppService::class);
                $about = $this->getAbout();

                $service->record($student, $data['phone'], $data['body'], $recipients[$data['phone']], $about, auth()->user());

                Notification::make()
                    ->title(__('Message ready'))
                    ->body(__('Open WhatsApp to send it.'))
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('open')
                            ->label(__('Open WhatsApp'))
                            ->button()
                            ->url($service->link($data['phone'], $data['body']), shouldOpenInNewTab: true)
                            ->close(),
                    ])
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    protected function recipients(): array
    {
        $student = $this->getStudent();

        return $student ? app(WhatsAppService::class)->recipientsFor($student) : [];
    }
}
