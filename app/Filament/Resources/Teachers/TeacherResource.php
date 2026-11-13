<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Models\Campus;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class TeacherResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'hr.manage';

    protected static string $deletePermission = 'hr.manage';

    protected static ?string $model = Teacher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?int $navigationSort = 510;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('hr.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Teacher');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Teachers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('firstname')
                    ->label(__('First name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('lastname')
                    ->label(__('Last name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignorable: fn (?Teacher $record): ?User => $record?->user),
                TextInput::make('max_week_hours')
                    ->label(__('Max weekly hours'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(168)
                    ->step(0.01)
                    ->nullable(),
                DatePicker::make('hired_at')
                    ->label(__('Hire Date'))
                    ->nullable(),
                Select::make('campuses')
                    ->label(__('Branches'))
                    ->helperText(__('Branches are also added when the teacher is assigned to a section.'))
                    ->relationship('campuses', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Campus $record): string => $record->name)
                    ->multiple()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Mobile: stacked teacher info
                TextColumn::make('mobile_name')
                    ->label(__('Teacher'))
                    ->state(fn ($record) => $record->user?->lastname.', '.$record->user?->firstname)
                    ->description(fn ($record) => $record->user?->email)
                    ->searchable(query: fn ($query, $search) => $query->whereHas('user', fn ($q) => $q->where('lastname', 'like', "%{$search}%")->orWhere('firstname', 'like', "%{$search}%")))
                    ->sortable(query: fn ($query, $direction) => $query->join('users', 'teachers.user_id', '=', 'users.id')->orderBy('users.lastname', $direction))
                    ->wrap()
                    ->hiddenFrom('md'),
                // Desktop columns
                TextColumn::make('id')
                    ->label(__('ID Number'))
                    ->sortable(),
                TextColumn::make('user.lastname')
                    ->label(__('Last name'))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('user.firstname')
                    ->label(__('First name'))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->wrap()
                    ->width('180px')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('subjects')
                    ->label(__('Subjects'))
                    ->state(fn (Teacher $record): array => self::currentAssignments($record)->pluck('subject.name')->unique()->sort()->values()->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('sections_taught')
                    ->label(__('Sections taught'))
                    ->state(fn (Teacher $record): array => self::currentAssignments($record)
                        ->pluck('section')
                        ->unique('id')
                        ->sortBy(fn (Section $section): string => sprintf('%s-%05d-%s', $section->shift->value === 'morning' ? 0 : 1, $section->schoolClass?->order ?? 0, $section->name))
                        ->map(fn (Section $section): string => $section->full_name)
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('campuses.name')
                    ->label(__('Branches'))
                    ->badge()
                    ->visibleFrom('md'),
                TextColumn::make('max_week_hours')
                    ->label(__('Max hours/week'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('hired_at')
                    ->label(__('Hire Date'))
                    ->date()
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Active'),
                        'inactive' => __('Inactive'),
                    ])
                    ->default('active')
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'active' => $query->whereNull('deleted_at'),
                            'inactive' => $query->whereNotNull('deleted_at'),
                            default => $query->withTrashed(),
                        };
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Teachers with their subjects and sections. With a branch chosen in the branch switcher, only the teachers who
     * work at that branch, including those who also work at another one.
     */
    public static function getEloquentQuery(): Builder
    {
        $branchId = auth()->user()?->activeBranchId();

        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user', 'campuses', 'teachingAssignments.subject', 'teachingAssignments.section.schoolClass'])
            ->when($branchId !== null, fn (Builder $query) => $query->whereHas('campuses', fn (Builder $campuses) => $campuses->whereKey($branchId)));
    }

    /**
     * What the teacher teaches this academic year, at the branch chosen in the branch switcher or at all branches.
     *
     * @return Collection<int, TeachingAssignment>
     */
    private static function currentAssignments(Teacher $teacher): Collection
    {
        $branchId = auth()->user()?->activeBranchId();
        $yearId = Year::current()?->id;

        return $teacher->teachingAssignments
            ->filter(fn (TeachingAssignment $assignment): bool => $assignment->section !== null
                && $assignment->section->year_id === $yearId
                && ($branchId === null || $assignment->section->campus_id === $branchId))
            ->values();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeachers::route('/'),
            'create' => CreateTeacher::route('/create'),
            'edit' => EditTeacher::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
