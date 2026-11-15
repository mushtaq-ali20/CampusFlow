<?php

namespace App\Filament\Resources\Admissions;

use App\Enums\AdmissionStatus;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\Admissions\Pages\CreateAdmission;
use App\Filament\Resources\Admissions\Pages\EditAdmission;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\Admissions\RelationManagers\DiscountsRelationManager;
use App\Filament\Resources\Admissions\RelationManagers\ResultsRelationManager;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Admission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Year;
use App\Services\PromotionService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class AdmissionResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'admissions.manage';

    protected static string $deletePermission = 'admissions.manage';

    protected static ?string $model = Admission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('admissions.view') ?? false;
    }

    /**
     * Admissions carry the fee vouchers of the student: they are closed with a status, never deleted.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getModelLabel(): string
    {
        return __('Admission');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Admissions');
    }

    /**
     * Staff attached to a branch only see the admissions of their branch.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    /**
     * The sections where the student can be admitted, for the chosen branch, class and academic year.
     *
     * @return array<int, string>
     */
    public static function sectionOptions(Get $get): array
    {
        return Section::query()
            ->visibleTo(auth()->user())
            ->where('campus_id', $get('campus_id'))
            ->where('school_class_id', $get('school_class_id'))
            ->where('year_id', $get('year_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchStudents(string $search): array
    {
        return Student::query()
            ->whereHas('user', fn (Builder $users) => $users
                ->where('firstname', 'like', "%{$search}%")
                ->orWhere('lastname', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->limit(50)
            ->get()
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make(__('Student'))
                    ->columnSpanFull()
                    ->schema([static::studentField()]),

                FormSection::make(__('Placement'))
                    ->description(__('Choose the branch, class and academic year first, then the section.'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema(static::placementFields()),

                FormSection::make(__('Admission'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema(static::admissionFields()),
            ]);
    }

    private static function studentField(): Select
    {
        return Select::make('student_id')
            ->label(__('Student'))
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => static::searchStudents($search))
            ->getOptionLabelUsing(fn (int|string|null $value): ?string => Student::query()->find($value)?->name)
            ->required()
            ->disabledOn('edit')
            ->unique(
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('year_id', $get('year_id')),
            )
            ->validationMessages([
                'unique' => __('This student is already admitted in this academic year.'),
            ]);
    }

    /**
     * @return list<Select>
     */
    private static function placementFields(): array
    {
        return [
            Select::make('campus_id')
                ->label(__('Branch'))
                ->options(fn (): array => SectionResource::branchOptions())
                ->default(fn (): ?int => auth()->user()?->activeBranchId())
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('section_id', null)),
            Select::make('school_class_id')
                ->label(__('Class'))
                ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('section_id', null)),
            Select::make('year_id')
                ->label(__('Academic Year'))
                ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                ->default(fn (): ?int => Year::current()?->id)
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('section_id', null)),
            Select::make('section_id')
                ->label(__('Section'))
                ->options(fn (Get $get): array => static::sectionOptions($get))
                ->required()
                ->helperText(__('Choose the branch, class and academic year first.')),
        ];
    }

    /**
     * @return list<DatePicker|Select>
     */
    private static function admissionFields(): array
    {
        return [
            DatePicker::make('admitted_on')
                ->label(__('Admission date'))
                ->default(now())
                ->required(),
            Select::make('status')
                ->label(__('Status'))
                ->options(AdmissionStatus::class)
                ->default(AdmissionStatus::Active)
                ->required()
                ->visibleOn('edit'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'campus', 'schoolClass', 'section', 'year']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('admission_number')
                    ->label(__('Admission No.'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('student.user', fn (Builder $users) => $users
                        ->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('section.full_name')
                    ->label(__('Class / Section'))
                    ->state(fn (Admission $record): ?string => $record->section?->full_name ?? $record->schoolClass?->name),
                TextColumn::make('campus.name')
                    ->label(__('Branch')),
                TextColumn::make('year.name')
                    ->label(__('Academic Year')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('result')
                    ->label(__('Result'))
                    ->state(function (Admission $record): string {
                        $evaluation = app(PromotionService::class)->evaluate($record);

                        return match (true) {
                            $evaluation['passed'] => __('Pass'),
                            $evaluation['failedSubjects']->isNotEmpty() => __('Fail'),
                            default => __('Pending'),
                        };
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        __('Pass') => 'success',
                        __('Fail') => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => SectionResource::branchOptions()),
                SelectFilter::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => Section::query()->visibleTo(auth()->user())->with('schoolClass')->get()->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name])->all()),
                SelectFilter::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Year::current()?->id),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AdmissionStatus::class)
                    ->default(AdmissionStatus::Active->value),
            ])
            ->recordActions([
                EditAction::make()->button()->color('gray'),
                ActionGroup::make([
                    static::promoteAction(),
                    Action::make('markLeft')
                        ->label(__('Mark as left'))
                        ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (Admission $record): bool => $record->status === AdmissionStatus::Active && static::canEdit($record))
                        ->action(fn (Admission $record) => $record->update(['status' => AdmissionStatus::Left])),
                ]),
            ])
            ->toolbarActions([
                static::bulkPromoteAction(),
            ]);
    }

    /**
     * Promotes one student to a section of the next class.
     */
    public static function promoteAction(): Action
    {
        return Action::make('promote')
            ->label(__('Promote'))
            ->icon(Heroicon::OutlinedArrowTrendingUp)
            ->color('success')
            ->visible(fn (Admission $record): bool => static::canEdit($record) && app(PromotionService::class)->canPromote($record))
            ->schema(fn (Admission $record): array => [
                Select::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->whereKeyNot($record->year_id)->latest('id')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Year::query()->whereKeyNot($record->year_id)->latest('id')->value('id'))
                    ->required()
                    ->live(),
                Select::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (Get $get): array => Section::query()
                        ->where('campus_id', $record->campus_id)
                        ->where('school_class_id', $record->schoolClass->nextClass()?->id)
                        ->where('year_id', $get('year_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
            ])
            ->modalHeading(fn (Admission $record): string => __('Promote :student to :class', ['student' => $record->student->name, 'class' => $record->schoolClass->nextClass()?->name]))
            ->action(function (Admission $record, array $data): void {
                try {
                    app(PromotionService::class)->promote($record, Section::query()->findOrFail($data['section_id']));
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Student promoted'))->success()->send();
            });
    }

    /**
     * Promotes the selected students who passed to the next class in another academic year. Each student goes to
     * the section with the same name as their current section, or to the first section of the class.
     */
    public static function bulkPromoteAction(): BulkAction
    {
        return BulkAction::make('promoteSelected')
            ->label(__('Promote selected students'))
            ->icon(Heroicon::OutlinedArrowTrendingUp)
            ->visible(fn (): bool => auth()->user()?->can('admissions.manage') ?? false)
            ->schema([
                Select::make('year_id')
                    ->label(__('Promote to academic year'))
                    ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $service = app(PromotionService::class);
                $promoted = 0;
                $skipped = 0;

                foreach ($records as $admission) {
                    $sections = Section::query()
                        ->where('campus_id', $admission->campus_id)
                        ->where('school_class_id', $admission->schoolClass->nextClass()?->id)
                        ->where('year_id', $data['year_id'])
                        ->orderBy('name')
                        ->get();

                    $target = $sections->firstWhere('name', $admission->section->name) ?? $sections->first();

                    try {
                        if ($target === null) {
                            throw new DomainException(__('No section'));
                        }

                        $service->promote($admission, $target);
                        $promoted++;
                    } catch (DomainException) {
                        $skipped++;
                    }
                }

                Notification::make()
                    ->title(__(':promoted students promoted, :skipped skipped (not passed, no next class or no section).', ['promoted' => $promoted, 'skipped' => $skipped]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            ResultsRelationManager::class,
            DiscountsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissions::route('/'),
            'create' => CreateAdmission::route('/create'),
            'edit' => EditAdmission::route('/{record}/edit'),
        ];
    }

    /**
     * The branch, class and academic year always come from the chosen section, and branch staff can only
     * admit students to the sections of their branch.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function alignWithSection(array $data): array
    {
        $section = Section::query()->visibleTo(auth()->user())->find($data['section_id'] ?? null);

        abort_if($section === null, 403);

        return [
            ...$data,
            'campus_id' => $section->campus_id,
            'school_class_id' => $section->school_class_id,
            'year_id' => $section->year_id,
        ];
    }
}
