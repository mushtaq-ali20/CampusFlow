<?php

namespace App\Filament\Resources\Appraisals;

use App\Enums\AppraisalStatus;
use App\Filament\Resources\Appraisals\Pages\ManageAppraisals;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCriterion;
use App\Services\AppraisalService;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The evaluations of the employees. Staff who manage the evaluations score each employee on the criteria and
 * submit; the employee then sees their own evaluation and acknowledges it.
 */
class AppraisalResource extends Resource
{
    protected static ?string $model = Appraisal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 560;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('appraisals.view') || EmployeeProfile::query()->where('user_id', $user->id)->exists());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        return in_array($action, ['viewAny', 'view'], true) && static::canAccess()
            ? parent::getAuthorizationResponse($action, $record)
            : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Evaluation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Evaluations');
    }

    public static function manages(): bool
    {
        return auth()->user()?->can('appraisals.manage') ?? false;
    }

    /**
     * Staff who see the evaluations see those of the branch; everybody else sees their own once submitted.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(
                $user?->can('appraisals.view'),
                fn (Builder $query) => $query->visibleTo($user),
                fn (Builder $query) => $query
                    ->where('status', '!=', AppraisalStatus::Draft)
                    ->whereHas('employee', fn (Builder $employee) => $employee->where('user_id', $user?->id)),
            );
    }

    /**
     * The form of an evaluation: a score and remarks for each criterion in use, then the overall comments.
     *
     * @return array<int, mixed>
     */
    public static function evaluationForm(bool $readOnly = false): array
    {
        $scale = [5 => __('5 - Excellent'), 4 => __('4 - Good'), 3 => __('3 - Satisfactory'), 2 => __('2 - Weak'), 1 => __('1 - Poor')];

        return [
            ...EvaluationCriterion::query()->active()->get()->map(fn (EvaluationCriterion $criterion): Fieldset => Fieldset::make($criterion->name.($criterion->weight > 1 ? ' (×'.$criterion->weight.')' : ''))
                ->columns(2)
                ->schema([
                    Select::make("scores.{$criterion->id}.score")->label(__('Score'))->options($scale)->helperText($criterion->description),
                    TextInput::make("scores.{$criterion->id}.remarks")->label(__('Remarks'))->maxLength(255),
                ]))->all(),
            Textarea::make('strengths')->label(__('Strengths'))->rows(2),
            Textarea::make('improvements')->label(__('To improve'))->rows(2),
            Toggle::make('submit')
                ->label(__('Submit the evaluation'))
                ->helperText(__('The employee then sees it. Leave off to save a draft.'))
                ->hidden($readOnly),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function evaluationData(Appraisal $appraisal): array
    {
        return [
            'scores' => $appraisal->scores->mapWithKeys(fn ($score): array => [$score->evaluation_criterion_id => ['score' => $score->score, 'remarks' => $score->remarks]])->all(),
            'strengths' => $appraisal->strengths,
            'improvements' => $appraisal->improvements,
            'submit' => false,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee.user', 'cycle', 'evaluator', 'scores']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('employee.user.name')
                    ->label(__('Employee'))
                    ->description(fn (Appraisal $record): string => $record->employee->designation)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('employee.user', fn (Builder $users) => $users->where('firstname', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('cycle.name')->label(__('Cycle')),
                TextColumn::make('evaluator.name')->label(__('Evaluated by'))->placeholder('—'),
                TextColumn::make('score')
                    ->label(__('Score'))
                    ->formatStateUsing(fn (float $state): string => rtrim(rtrim(number_format($state, 1), '0'), '.').'%')
                    ->description(fn (Appraisal $record): ?string => AppraisalService::ratingFor($record->score))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->description(fn (Appraisal $record): ?string => $record->employee_comment),
            ])
            ->filters([
                SelectFilter::make('appraisal_cycle_id')->label(__('Cycle'))->options(fn (): array => AppraisalCycle::query()->orderByDesc('from_date')->pluck('name', 'id')->all()),
                SelectFilter::make('status')->label(__('Status'))->options(AppraisalStatus::class),
            ])
            ->recordActions([
                Action::make('evaluate')
                    ->label(__('Evaluate'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Appraisal $record): bool => $record->status === AppraisalStatus::Draft && ! $record->cycle->is_closed && static::manages())
                    ->modalHeading(fn (Appraisal $record): string => __('Evaluation of :name', ['name' => $record->employee->user->name]))
                    ->modalWidth('3xl')
                    ->fillForm(fn (Appraisal $record): array => static::evaluationData($record))
                    ->schema(fn (): array => static::evaluationForm())
                    ->action(fn (Appraisal $record, array $data) => static::attempt(
                        fn () => app(AppraisalService::class)->evaluate($record, $data['scores'] ?? [], $data['strengths'] ?? null, $data['improvements'] ?? null, auth()->user(), (bool) ($data['submit'] ?? false)),
                        ($data['submit'] ?? false) ? __('Evaluation submitted') : __('Draft saved'),
                    )),
                Action::make('read')
                    ->label(__('Read'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (Appraisal $record): bool => $record->status !== AppraisalStatus::Draft)
                    ->modalHeading(fn (Appraisal $record): string => __('Evaluation of :name', ['name' => $record->employee->user->name]))
                    ->modalWidth('3xl')
                    ->fillForm(fn (Appraisal $record): array => static::evaluationData($record))
                    ->schema(fn (): array => static::evaluationForm(readOnly: true))
                    ->disabledForm()
                    ->modalSubmitAction(false),
                Action::make('acknowledge')
                    ->label(__('Acknowledge'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Appraisal $record): bool => $record->status === AppraisalStatus::Submitted && $record->employee->user_id === auth()->id())
                    ->modalDescription(__('You confirm that you have read your evaluation. You can add a comment.'))
                    ->schema([Textarea::make('comment')->label(__('Your comment'))->rows(3)])
                    ->action(fn (Appraisal $record, array $data) => static::attempt(fn () => app(AppraisalService::class)->acknowledge($record, auth()->user(), $data['comment'] ?? null), __('Evaluation acknowledged'))),
                Action::make('reopen')
                    ->label(__('Reopen'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (Appraisal $record): bool => $record->status === AppraisalStatus::Submitted && ! $record->cycle->is_closed && static::manages())
                    ->requiresConfirmation()
                    ->action(fn (Appraisal $record) => static::attempt(fn () => app(AppraisalService::class)->reopen($record), __('Evaluation reopened'))),
            ]);
    }

    public static function attempt(Closure $callback, string $success): void
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
            'index' => ManageAppraisals::route('/'),
        ];
    }
}
