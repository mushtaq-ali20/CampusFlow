<?php

namespace App\Filament\Resources\Assessments;

use App\Enums\AdmissionStatus;
use App\Enums\AssessmentType;
use App\Enums\ExamType;
use App\Filament\Resources\Assessments\Pages\ManageAssessments;
use App\Models\Admission;
use App\Models\Assessment;
use App\Models\Section;
use App\Models\User;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Class assessments (class tests, quizzes, assignments, projects, observations) of a subject in a section, with the
 * marks of each student. As for the exam marks, staff with the evaluation permissions work on every section, and
 * teachers on the subjects they teach.
 */
class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('evaluation.view') ?? false) || ($user?->isTeacher() ?? false);
    }

    public static function canEnterMarks(): bool
    {
        $user = auth()->user();

        return ($user?->can('evaluation.edit') ?? false) || ($user?->isTeacher() ?? false);
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $allowed = match ($action) {
            'viewAny', 'view' => static::canAccess(),
            default => static::canEnterMarks(),
        };

        return $allowed ? parent::getAuthorizationResponse($action, $record) : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Class assessments');
    }

    public static function getModelLabel(): string
    {
        return __('Class assessment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Class assessments');
    }

    /**
     * Teachers without the evaluation permission only see the assessments of the subjects they teach.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->visibleTo($user)
            ->when($user !== null && ! $user->can('evaluation.view'), fn (Builder $query) => $query->whereExists(fn ($assignments) => $assignments
                ->from('teaching_assignments')
                ->whereColumn('teaching_assignments.section_id', 'assessments.section_id')
                ->whereColumn('teaching_assignments.subject_id', 'assessments.subject_id')
                ->where('teaching_assignments.teacher_id', $user->id)));
    }

    /**
     * The sections of the current academic year where the user records assessments.
     *
     * @return Builder<Section>
     */
    public static function sectionsFor(?User $user): Builder
    {
        return Section::query()
            ->visibleTo($user)
            ->where('year_id', Year::current()?->id)
            ->when($user !== null && ! $user->can('evaluation.view'), fn (Builder $query) => $query->whereHas('teachingAssignments', fn (Builder $assignments) => $assignments->where('teacher_id', $user->id)));
    }

    /**
     * @return array<int, string>
     */
    public static function sectionOptions(): array
    {
        return static::sectionsFor(auth()->user())
            ->with(['schoolClass', 'campus'])
            ->get()
            ->sortBy(fn (Section $section): string => $section->campus->name.str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name])
            ->all();
    }

    /**
     * The subjects of the class of the section; for a teacher, those they teach in it.
     *
     * @return array<int, string>
     */
    public static function subjectOptions(?int $sectionId): array
    {
        $user = auth()->user();
        $section = $sectionId ? static::sectionsFor($user)->with('schoolClass.subjects')->find($sectionId) : null;

        if ($section === null) {
            return [];
        }

        $subjects = $section->schoolClass->subjects;

        if (! $user->can('evaluation.view')) {
            $subjects = $subjects->whereIn('id', $section->teachingAssignments()->where('teacher_id', $user->id)->pluck('subject_id'));
        }

        return $subjects->pluck('name', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        $sectionId = fn (Get $get): ?int => filled($get('section_id')) ? (int) $get('section_id') : null;

        return $schema
            ->components([
                Select::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => static::sectionOptions())
                    ->in(fn (): array => array_keys(static::sectionOptions()))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('subject_id', null)),
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->options(fn (Get $get): array => static::subjectOptions($sectionId($get)))
                    ->in(fn (Get $get): array => array_keys(static::subjectOptions($sectionId($get))))
                    ->required(),
                TextInput::make('title')
                    ->label(__('Title'))
                    ->placeholder(__('Class test 1'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('Type'))
                    ->options(AssessmentType::class)
                    ->default(AssessmentType::ClassTest)
                    ->required(),
                Select::make('exam')
                    ->label(__('Counts towards'))
                    ->options(ExamType::class)
                    ->default(ExamType::Quarterly)
                    ->required(),
                TextInput::make('total_marks')
                    ->label(__('Total marks'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->default(20)
                    ->required(),
                DatePicker::make('held_on')
                    ->label(__('Date'))
                    ->default(now())
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['section.schoolClass', 'subject'])->withCount('marks')->withAvg('marks', 'marks'))
            ->defaultSort('held_on', 'desc')
            ->columns([
                TextColumn::make('held_on')->label(__('Date'))->date()->sortable(),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->description(fn (Assessment $record): string => $record->type->getLabel().' · '.$record->exam->getLabel())
                    ->searchable(),
                TextColumn::make('section.full_name')->label(__('Section')),
                TextColumn::make('subject.name')->label(__('Subject')),
                TextColumn::make('marks_count')->label(__('Marked'))->alignEnd(),
                TextColumn::make('marks_avg_marks')
                    ->label(__('Average'))
                    ->formatStateUsing(fn ($state, Assessment $record): string => round((float) $state, 1).' / '.$record->total_marks)
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => static::sectionOptions()),
                SelectFilter::make('exam')
                    ->label(__('Counts towards'))
                    ->options(ExamType::class),
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(AssessmentType::class),
            ])
            ->recordActions([
                static::marksAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Enters the marks of the active students of the section, out of the total marks of the assessment.
     */
    public static function marksAction(): Action
    {
        return Action::make('marks')
            ->label(__('Enter marks'))
            ->icon(Heroicon::OutlinedTableCells)
            ->visible(fn (): bool => static::canEnterMarks())
            ->modalHeading(fn (Assessment $record): string => $record->title.' · '.$record->section->full_name.' · '.__('out of :total', ['total' => $record->total_marks]))
            ->modalWidth('3xl')
            ->fillForm(function (Assessment $record): array {
                $recorded = $record->marks()->get()->keyBy('admission_id');

                return [
                    'rows' => Admission::query()
                        ->with('student')
                        ->where('section_id', $record->section_id)
                        ->where('status', AdmissionStatus::Active)
                        ->get()
                        ->sortBy(fn (Admission $admission): string => $admission->student->name)
                        ->map(fn (Admission $admission): array => [
                            'admission_id' => $admission->id,
                            'name' => $admission->student->name,
                            'marks' => $recorded[$admission->id]->marks ?? null,
                            'remarks' => $recorded[$admission->id]->remarks ?? null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->schema(fn (Assessment $record): array => [
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('admission_id'),
                        TextInput::make('name')->label(__('Student'))->disabled()->dehydrated(false),
                        TextInput::make('marks')->label(__('Marks'))->numeric()->minValue(0)->maxValue($record->total_marks),
                        TextInput::make('remarks')->label(__('Remarks'))->maxLength(255),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])
            ->action(function (Assessment $record, array $data): void {
                $admissions = Admission::query()->where('section_id', $record->section_id)->where('status', AdmissionStatus::Active)->pluck('id');

                foreach ($data['rows'] as $row) {
                    if (! $admissions->contains((int) $row['admission_id'])) {
                        continue;
                    }

                    if (blank($row['marks'] ?? null)) {
                        $record->marks()->where('admission_id', $row['admission_id'])->delete();

                        continue;
                    }

                    $record->marks()->updateOrCreate(
                        ['admission_id' => $row['admission_id']],
                        ['marks' => $row['marks'], 'remarks' => $row['remarks'] ?? null],
                    );
                }

                Notification::make()->title(__('Marks saved'))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssessments::route('/'),
        ];
    }
}
