<?php

namespace App\Filament\Resources\Sections;

use App\Enums\Shift;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Filament\Resources\Sections\Pages\ViewSection;
use App\Filament\Resources\Sections\RelationManagers\AdmissionsRelationManager;
use App\Filament\Resources\Sections\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Sections\RelationManagers\TimetableEntriesRelationManager;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SectionResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'school.manage';

    protected static string $deletePermission = 'school.manage';

    protected static ?string $model = Section::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('school.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getModelLabel(): string
    {
        return __('Section');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sections');
    }

    /**
     * Staff attached to a branch only see the sections of their branch.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    /**
     * The branches the user may choose: only their own branch for branch staff.
     *
     * @return array<int, string>
     */
    public static function branchOptions(): array
    {
        $branchId = auth()->user()?->activeBranchId();

        return Campus::query()
            ->when($branchId !== null, fn (Builder $query) => $query->whereKey($branchId))
            ->get()
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function teacherOptions(): array
    {
        $branchId = auth()->user()?->activeBranchId();

        return Teacher::query()
            ->when($branchId !== null, fn (Builder $query) => $query->whereHas('campuses', fn (Builder $campuses) => $campuses->whereKey($branchId)))
            ->get()
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make(__('Placement'))
                    ->description(__('The branch, class, academic year and shift this section belongs to.'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('campus_id')
                            ->label(__('Branch'))
                            ->options(fn (): array => static::branchOptions())
                            ->default(fn (): ?int => auth()->user()?->activeBranchId())
                            ->required()
                            ->live(),
                        Select::make('school_class_id')
                            ->label(__('Class'))
                            ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
                            ->required()
                            ->live(),
                        Select::make('year_id')
                            ->label(__('Academic Year'))
                            ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => Year::current()?->id)
                            ->required()
                            ->live(),
                        Select::make('shift')
                            ->label(__('Shift'))
                            ->options(Shift::class)
                            ->default(Shift::Morning)
                            ->required()
                            ->live(),
                    ]),

                FormSection::make(__('Section details'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema(static::sectionDetailsFields()),
            ]);
    }

    /**
     * @return array<int, TextInput|Select>
     */
    private static function sectionDetailsFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Section'))
                ->placeholder('A')
                ->required()
                ->maxLength(20)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('campus_id', $get('campus_id'))
                        ->where('school_class_id', $get('school_class_id'))
                        ->where('year_id', $get('year_id'))
                        ->where('shift', $get('shift') instanceof Shift ? $get('shift')->value : $get('shift')),
                )
                ->validationMessages([
                    'unique' => __('This section already exists for this class, shift, branch and academic year.'),
                ]),
            TextInput::make('capacity')
                ->label(__('Capacity'))
                ->integer()
                ->minValue(1),
            Select::make('class_teacher_id')
                ->label(__('Class teacher'))
                ->options(fn (): array => static::teacherOptions())
                ->searchable()
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['campus', 'schoolClass', 'year', 'classTeacher']))
            ->defaultSort('name')
            ->defaultGroup(
                Group::make('shift')
                    ->label(__('Shift').' › '.__('Class'))
                    ->getKeyFromRecordUsing(fn (Section $record): string => $record->shift->value.'-'.$record->school_class_id)
                    ->getTitleFromRecordUsing(fn (Section $record): string => $record->shift->getLabel().' › '.$record->schoolClass->name)
                    ->orderQueryUsing(fn (Builder $query): Builder => $query
                        ->orderByDesc('shift')
                        ->orderBy(SchoolClass::query()->select('order')->whereColumn('school_classes.id', 'sections.school_class_id'))
                        ->orderBy('school_class_id'))
                    ->collapsible(),
            )
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label(__('Class'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Section'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shift')
                    ->label(__('Shift'))
                    ->badge(),
                TextColumn::make('campus.name')
                    ->label(__('Branch')),
                TextColumn::make('year.name')
                    ->label(__('Academic Year')),
                TextColumn::make('classTeacher.name')
                    ->label(__('Class teacher')),
                TextColumn::make('capacity')
                    ->label(__('Capacity')),
                TextColumn::make('teaching_assignments_count')
                    ->label(__('Subjects assigned'))
                    ->counts('teachingAssignments'),
            ])
            ->filters([
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => static::branchOptions()),
                SelectFilter::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Year::current()?->id),
                SelectFilter::make('shift')
                    ->label(__('Shift'))
                    ->options(Shift::class),
            ])
            ->recordActions([
                ViewAction::make()->button()->color('gray'),
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AdmissionsRelationManager::class,
            TeachingAssignmentsRelationManager::class,
            TimetableEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSections::route('/'),
            'create' => CreateSection::route('/create'),
            'view' => ViewSection::route('/{record}'),
            'edit' => EditSection::route('/{record}/edit'),
        ];
    }
}
