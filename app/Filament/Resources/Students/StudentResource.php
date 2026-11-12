<?php

namespace App\Filament\Resources\Students;

use App\Enums\AdmissionStatus;
use App\Enums\Shift;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Exports\StudentExporter;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\EnrollStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AdmissionsRelationManager;
use App\Filament\Resources\Students\RelationManagers\ContactsRelationManager;
use App\Models\SchoolClass;
use App\Models\Section as SchoolSection;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'enrollments.edit';

    protected static string $deletePermission = 'enrollments.delete';

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('enrollments.view') ?? false;
    }

    protected static ?int $navigationSort = 200;

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('Student');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Students');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Student Info'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('user.email')
                            ->label(__('Email')),
                        TextEntry::make('idnumber')
                            ->label(__('ID Number'))
                            ->placeholder('-'),
                        TextEntry::make('user.birthdate')
                            ->label(__('Birthdate'))
                            ->date(),
                        TextEntry::make('student_age')
                            ->label(__('Age')),
                        TextEntry::make('formatted_gender')
                            ->label(__('Gender'))
                            ->placeholder('-'),
                        TextEntry::make('phone.phone_number')
                            ->label(__('Phone'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('profession.name')
                            ->label(__('Profession'))
                            ->placeholder('-'),
                        TextEntry::make('institution.name')
                            ->label(__('Institution'))
                            ->placeholder('-'),
                    ]),
                Section::make(__('Address'))
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('address')
                            ->label(__('Address'))
                            ->placeholder('-'),
                        TextEntry::make('city')
                            ->label(__('City'))
                            ->placeholder('-'),
                        TextEntry::make('zip_code')
                            ->label(__('zip'))
                            ->placeholder('-'),
                        TextEntry::make('state')
                            ->label(__('State'))
                            ->placeholder('-'),
                        TextEntry::make('country')
                            ->label(__('Country'))
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Student')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Student Info'))
                            ->schema([
                                TextInput::make('firstname')
                                    ->label(__('First name'))
                                    ->required()
                                    ->maxLength(30),
                                TextInput::make('lastname')
                                    ->label(__('Last name'))
                                    ->required()
                                    ->maxLength(30),
                                TextInput::make('email')
                                    ->label(__('Email'))
                                    ->email()
                                    ->nullable()
                                    ->maxLength(60)
                                    ->unique(User::class, 'email', ignorable: fn (?Student $record): ?User => $record?->user),
                                TextInput::make('idnumber')
                                    ->label(__('ID Number'))
                                    ->nullable(),
                                DatePicker::make('birthdate')
                                    ->label(__('Birthdate'))
                                    ->maxDate(today())
                                    ->nullable(),
                                Radio::make('gender_id')
                                    ->label(__('Gender'))
                                    ->options([
                                        0 => __('Other'),
                                        1 => __('Female'),
                                        2 => __('Male'),
                                    ])
                                    ->required()
                                    ->default(0)
                                    ->inline(),
                                SpatieMediaLibraryFileUpload::make('profile_picture')
                                    ->label(__('Profile Picture'))
                                    ->collection('profile-picture')
                                    ->conversion('thumb')
                                    ->image()
                                    ->nullable(),
                                Select::make('profession_id')
                                    ->label(__('Profession'))
                                    ->relationship('profession', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                    ])
                                    ->nullable(),
                                Select::make('institution_id')
                                    ->label(__('Institution'))
                                    ->relationship('institution', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                    ])
                                    ->nullable(),
                                Repeater::make('phone')
                                    ->relationship()
                                    ->label(__('Phone numbers'))
                                    ->schema([
                                        TextInput::make('phone_number')
                                            ->label(__('Phone number'))
                                            ->required(),
                                    ])
                                    ->defaultItems(0)
                                    ->reorderable(false),
                            ]),

                        Tab::make(__('Address'))
                            ->schema([
                                TextInput::make('address')
                                    ->label(__('Address'))
                                    ->nullable()
                                    ->maxLength(60),
                                TextInput::make('zip_code')
                                    ->label(__('zip'))
                                    ->nullable()
                                    ->maxLength(10),
                                TextInput::make('city')
                                    ->label(__('City'))
                                    ->nullable()
                                    ->maxLength(30),
                                TextInput::make('state')
                                    ->label(__('State'))
                                    ->nullable()
                                    ->maxLength(30),
                                TextInput::make('country')
                                    ->label(__('Country'))
                                    ->nullable()
                                    ->maxLength(20),
                            ]),

                        Tab::make(__('Invoicing Info'))
                            ->schema([
                                TextInput::make('iban')
                                    ->label(__('IBAN'))
                                    ->nullable()
                                    ->maxLength(90),
                                TextInput::make('bic')
                                    ->label(__('BIC'))
                                    ->nullable()
                                    ->maxLength(30),
                            ])
                            ->visible(fn (): bool => (bool) config('settings.collect_student_banking_info')),
                    ]),
            ]);
    }

    /**
     * Students with their latest admission: the branch, class, shift and section they study in, and their guardian.
     * With a branch chosen in the branch switcher, only the students admitted in that branch.
     */
    public static function getEloquentQuery(): Builder
    {
        $branchId = auth()->user()?->activeBranchId();

        return parent::getEloquentQuery()
            ->with(['user', 'phone', 'contacts', 'latestAdmission.campus', 'latestAdmission.schoolClass', 'latestAdmission.section'])
            ->when($branchId !== null, fn (Builder $query) => $query->whereHas('latestAdmission', fn (Builder $admission) => $admission->where('campus_id', $branchId)));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mobile_name')
                    ->label(__('Student'))
                    ->state(fn (Student $record): string => $record->user?->firstname.' '.$record->user?->lastname)
                    ->description(fn (Student $record): string => collect([
                        $record->latestAdmission?->schoolClass?->name,
                        $record->latestAdmission?->section?->name,
                        $record->latestAdmission?->section?->shift?->getLabel(),
                    ])->filter()->implode(' · '))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchByName($query, $search))
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('latestAdmission.admission_number')
                    ->label(__('Admission no.'))
                    ->searchable()
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('name')
                    ->label(__('Student'))
                    ->state(fn (Student $record): string => $record->user?->firstname.' '.$record->user?->lastname)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchByName($query, $search))
                    ->visibleFrom('md'),
                TextColumn::make('latestAdmission.campus.name')
                    ->label(__('Branch'))
                    ->placeholder('—')
                    ->visible(fn (): bool => auth()->user()?->activeBranchId() === null)
                    ->visibleFrom('md'),
                TextColumn::make('latestAdmission.schoolClass.name')
                    ->label(__('Class'))
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('latestAdmission.section.shift')
                    ->label(__('Shift'))
                    ->badge()
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('latestAdmission.section.name')
                    ->label(__('Section'))
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('guardian')
                    ->label(__('Guardian'))
                    ->state(fn (Student $record): ?string => $record->contacts->first()?->name)
                    ->description(fn (Student $record): ?string => $record->contacts->first()?->phone->first()?->phone_number)
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('latestAdmission.status')
                    ->label(__('Status'))
                    ->badge()
                    ->placeholder(__('Not admitted'))
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
                TextColumn::make('student_age')
                    ->label(__('Age'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $classId) => $query->whereHas('latestAdmission', fn (Builder $admission) => $admission->where('school_class_id', $classId)),
                    )),
                SelectFilter::make('shift')
                    ->label(__('Shift'))
                    ->options(Shift::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $shift) => $query->whereHas('latestAdmission.section', fn (Builder $section) => $section->where('shift', $shift)),
                    )),
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => SchoolSection::query()
                        ->visibleTo(auth()->user())
                        ->where('year_id', Year::current()?->id)
                        ->with(['schoolClass', 'campus'])
                        ->get()
                        ->sortBy(fn (SchoolSection $section): string => sprintf('%05d-%05d-%s-%s', $section->campus_id, $section->schoolClass?->order ?? 0, $section->shift->value, $section->name))
                        ->mapWithKeys(fn (SchoolSection $section): array => [
                            $section->id => $section->full_name.(auth()->user()?->activeBranchId() === null ? ', '.$section->campus?->name : ''),
                        ])
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $sectionId) => $query->whereHas('latestAdmission', fn (Builder $admission) => $admission->where('section_id', $sectionId)),
                    )),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AdmissionStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $status) => $query->whereHas('latestAdmission', fn (Builder $admission) => $admission->where('status', $status)),
                    )),
                Filter::make('age')
                    ->label(__('Age'))
                    ->schema([
                        TextInput::make('min_age')
                            ->label(__('Min Age'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('max_age')
                            ->label(__('Max Age'))
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        if ($data['min_age']) {
                            $query->whereHas('user', fn ($q) => $q
                                ->where('birthdate', '<=', Carbon::now()->subYears((int) $data['min_age'])));
                        }
                        if ($data['max_age']) {
                            $query->whereHas('user', fn ($q) => $q
                                ->where('birthdate', '>=', Carbon::now()->subYears((int) $data['max_age'] + 1)));
                        }
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(StudentExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StudentExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    private static function searchByName(Builder $query, string $search): Builder
    {
        return $query->whereHas('user', fn (Builder $user) => $user
            ->where('firstname', 'like', "%{$search}%")
            ->orWhere('lastname', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%"));
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            AdmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
            'enroll' => EnrollStudent::route('/{record}/enroll'),
        ];
    }
}
