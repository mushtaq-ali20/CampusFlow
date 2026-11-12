<?php

namespace App\Filament\Resources\JobApplicants;

use App\Enums\ApplicantStage;
use App\Enums\OpeningStatus;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\JobApplicants\Pages\ManageJobApplicants;
use App\Models\JobApplicant;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\FeeService;
use App\Services\RecruitmentService;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The people who applied for a job. The front desk enters the application; the applicant is then shortlisted,
 * interviewed, offered the job and hired, or rejected. Hiring makes the employee file.
 */
class JobApplicantResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'recruitment.manage';

    protected static string $deletePermission = 'recruitment.manage';

    protected static ?string $model = JobApplicant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 555;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('recruitment.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Job applicant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Job applicants');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('stage', ApplicantStage::Applied)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    protected static function manages(): bool
    {
        return auth()->user()?->can('recruitment.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('job_opening_id')
                    ->label(__('Job opening'))
                    ->options(fn (?JobApplicant $record): array => JobOpening::query()
                        ->visibleTo(auth()->user())
                        ->where(fn (Builder $query) => $query->where('status', OpeningStatus::Open)->when($record, fn (Builder $q) => $q->orWhere('id', $record->job_opening_id)))
                        ->with('campus')
                        ->get()
                        ->mapWithKeys(fn (JobOpening $opening): array => [$opening->id => $opening->title.' · '.$opening->campus->name])
                        ->all())
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('firstname')->label(__('First name'))->required()->maxLength(255),
                TextInput::make('lastname')->label(__('Last name'))->required()->maxLength(255),
                TextInput::make('phone')->label(__('Phone'))->tel()->required()->maxLength(30),
                TextInput::make('email')->label(__('Email'))->email()->maxLength(255),
                TextInput::make('cnic')->label(__('CNIC'))->placeholder('35202-1234567-1')->maxLength(20),
                TextInput::make('qualification')->label(__('Qualification'))->placeholder('M.Sc. Mathematics')->maxLength(255),
                TextInput::make('experience_years')->label(__('Experience (years)'))->integer()->minValue(0)->maxValue(60)->default(0)->required(),
                TextInput::make('expected_salary')->label(__('Expected salary'))->integer()->minValue(0)->prefix(config('campusflow.currency_symbol')),
                SpatieMediaLibraryFileUpload::make('cv')
                    ->label(__('CV'))
                    ->collection('cv')
                    ->disk('local')
                    ->visibility('private')
                    ->preserveFilenames()
                    ->maxSize(10 * 1024)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['opening.campus', 'interviewer', 'media']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('firstname')
                    ->label(__('Applicant'))
                    ->formatStateUsing(fn (JobApplicant $record): string => $record->name)
                    ->description(fn (JobApplicant $record): string => $record->phone)
                    ->searchable(['firstname', 'lastname', 'phone']),
                TextColumn::make('opening.title')->label(__('Job opening'))->description(fn (JobApplicant $record): string => $record->opening->campus->name),
                TextColumn::make('qualification')
                    ->label(__('Qualification'))
                    ->description(fn (JobApplicant $record): string => trans_choice('{0} No experience|{1} :count year|[2,*] :count years', $record->experience_years, ['count' => $record->experience_years]))
                    ->placeholder('—'),
                TextColumn::make('expected_salary')
                    ->label(__('Salary'))
                    ->state(fn (JobApplicant $record): ?string => match (true) {
                        $record->offered_salary !== null => FeeService::money($record->offered_salary),
                        $record->expected_salary !== null => FeeService::money($record->expected_salary),
                        default => null,
                    })
                    ->description(fn (JobApplicant $record): ?string => $record->offered_salary !== null ? __('Offered') : ($record->expected_salary !== null ? __('Expected') : null))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('interview_at')
                    ->label(__('Interview'))
                    ->dateTime('d M Y, g:i A')
                    ->description(fn (JobApplicant $record): ?string => $record->rating !== null ? str_repeat('★', $record->rating).str_repeat('☆', 5 - $record->rating) : $record->interviewer?->name)
                    ->placeholder('—'),
                TextColumn::make('stage')->label(__('Step'))->badge()->description(fn (JobApplicant $record): ?string => $record->rejection_reason),
            ])
            ->filters([
                SelectFilter::make('stage')->label(__('Step'))->options(ApplicantStage::class),
                SelectFilter::make('job_opening_id')
                    ->label(__('Job opening'))
                    ->options(fn (): array => JobOpening::query()->visibleTo(auth()->user())->orderByDesc('id')->pluck('title', 'id')->all()),
            ])
            ->recordActions([
                static::step('shortlist', __('Shortlist'), Heroicon::OutlinedCheck, [ApplicantStage::Applied])
                    ->requiresConfirmation()
                    ->action(fn (JobApplicant $record) => static::attempt(fn () => app(RecruitmentService::class)->shortlist($record), __('Applicant shortlisted'))),
                static::step('scheduleInterview', __('Interview'), Heroicon::OutlinedCalendarDays, [ApplicantStage::Shortlisted])
                    ->schema(static::interviewForm())
                    ->action(fn (JobApplicant $record, array $data) => static::scheduleInterview($record, $data)),
                static::step('recordInterview', __('Record interview'), Heroicon::OutlinedStar, [ApplicantStage::Interview])
                    ->visible(fn (JobApplicant $record): bool => $record->stage === ApplicantStage::Interview && $record->rating === null && static::manages())
                    ->schema([
                        Select::make('rating')
                            ->label(__('Rating'))
                            ->options([5 => __('5 - Excellent'), 4 => __('4 - Good'), 3 => __('3 - Average'), 2 => __('2 - Weak'), 1 => __('1 - Poor')])
                            ->required(),
                        Textarea::make('notes')->label(__('Interview notes'))->rows(3),
                    ])
                    ->action(fn (JobApplicant $record, array $data) => static::attempt(fn () => app(RecruitmentService::class)->recordInterview($record, (int) $data['rating'], $data['notes'] ?? null), __('Interview recorded'))),
                static::step('offer', __('Offer'), Heroicon::OutlinedHandThumbUp, [ApplicantStage::Interview])
                    ->visible(fn (JobApplicant $record): bool => $record->stage === ApplicantStage::Interview && $record->rating !== null && static::manages())
                    ->fillForm(fn (JobApplicant $record): array => ['salary' => $record->expected_salary])
                    ->schema([
                        TextInput::make('salary')->label(__('Basic salary offered'))->integer()->minValue(1)->required()->prefix(config('campusflow.currency_symbol')),
                    ])
                    ->action(fn (JobApplicant $record, array $data) => static::attempt(fn () => app(RecruitmentService::class)->offer($record, (int) $data['salary']), __('Offer recorded'))),
                static::step('hire', __('Hire'), Heroicon::OutlinedIdentification, [ApplicantStage::Offered])
                    ->color('success')
                    ->visible(fn (JobApplicant $record): bool => $record->stage === ApplicantStage::Offered && (auth()->user()?->can('employees.manage') ?? false))
                    ->modalDescription(__('Makes the employee file of this person with the offered salary. Give them a role under Staff, or a teacher profile, when they need to sign in.'))
                    ->schema([
                        DatePicker::make('joining_on')->label(__('Joining on'))->default(now())->required(),
                    ])
                    ->action(fn (JobApplicant $record, array $data) => static::attempt(fn () => app(RecruitmentService::class)->hire($record, Carbon::parse($data['joining_on'])), __('Applicant hired'))),
                ActionGroup::make([
                    static::step('reschedule', __('Move interview'), Heroicon::OutlinedCalendarDays, [ApplicantStage::Interview])
                        ->fillForm(fn (JobApplicant $record): array => ['interview_at' => $record->interview_at, 'interviewer_id' => $record->interviewer_id])
                        ->schema(static::interviewForm())
                        ->action(fn (JobApplicant $record, array $data) => static::scheduleInterview($record, $data)),
                    Action::make('cv')
                        ->label(__('Download CV'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn (JobApplicant $record): bool => $record->getFirstMedia('cv') !== null)
                        ->action(function (JobApplicant $record): StreamedResponse {
                            $media = $record->getFirstMedia('cv');

                            return response()->streamDownload(function () use ($media): void {
                                echo stream_get_contents($media->stream());
                            }, $media->file_name);
                        }),
                    EditAction::make()->visible(fn (JobApplicant $record): bool => $record->stage->isOpen() && static::manages()),
                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->visible(fn (JobApplicant $record): bool => $record->stage->isOpen() && static::manages())
                        ->schema([TextInput::make('reason')->label(__('Reason'))->maxLength(255)])
                        ->action(fn (JobApplicant $record, array $data) => static::attempt(fn () => app(RecruitmentService::class)->reject($record, $data['reason'] ?? null), __('Applicant rejected'))),
                ]),
            ]);
    }

    /**
     * An action that takes the applicant to the next step, shown at the steps it is possible from.
     *
     * @param  list<ApplicantStage>  $from
     */
    private static function step(string $name, string $label, Heroicon $icon, array $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (JobApplicant $record): bool => in_array($record->stage, $from, true) && static::manages());
    }

    /**
     * @return array<int, DateTimePicker|Select>
     */
    private static function interviewForm(): array
    {
        return [
            DateTimePicker::make('interview_at')->label(__('Interview on'))->seconds(false)->required(),
            Select::make('interviewer_id')
                ->label(__('Interviewer'))
                ->options(fn (): array => User::query()
                    ->where(fn (Builder $query) => $query->whereHas('roles')->orWhereHas('teacher'))
                    ->orderBy('firstname')
                    ->get()
                    ->pluck('name', 'id')
                    ->all())
                ->searchable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function scheduleInterview(JobApplicant $record, array $data): void
    {
        static::attempt(fn () => app(RecruitmentService::class)->scheduleInterview(
            $record,
            Carbon::parse($data['interview_at']),
            filled($data['interviewer_id'] ?? null) ? User::query()->find($data['interviewer_id']) : null,
        ), __('Interview planned'));
    }

    private static function attempt(Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJobApplicants::route('/'),
        ];
    }
}
