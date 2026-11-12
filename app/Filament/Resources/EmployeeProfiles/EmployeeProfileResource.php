<?php

namespace App\Filament\Resources\EmployeeProfiles;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PayItemKind;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\EmployeeProfiles\Pages\ManageEmployeeProfiles;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\FeeService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The HR files of the staff and teachers: who they are, the job, the bank account and the salary. The salary and
 * the fixed allowances and deductions, which go on the salary slip every month, are only seen by staff who see the
 * payroll.
 */
class EmployeeProfileResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'employees.manage';

    protected static string $deletePermission = 'employees.manage';

    protected static ?string $model = EmployeeProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 520;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('employees.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Employee');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Employees');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    protected static function seesSalaries(): bool
    {
        return auth()->user()?->can('payroll.view') ?? false;
    }

    /**
     * The people who work for the school (staff with a role, and teachers) and have no employee file yet.
     *
     * @return array<int, string>
     */
    public static function availableUsers(?int $includingId = null): array
    {
        return User::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $free) => $free
                    ->whereDoesntHave('employeeProfile')
                    ->where(fn (Builder $works) => $works->whereHas('roles')->orWhereHas('teacher')))
                ->when($includingId, fn (Builder $q) => $q->orWhere('id', $includingId)))
            ->orderBy('firstname')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' ('.$user->email.')'])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function campusOptions(): array
    {
        $own = auth()->user()?->campus_id;

        return Campus::query()->when($own, fn (Builder $query) => $query->whereKey($own))->get()->pluck('name', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Employee'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('user_id')
                            ->label(__('Person'))
                            ->options(fn (?EmployeeProfile $record): array => static::availableUsers($record?->user_id))
                            ->searchable()
                            ->required()
                            ->disabled(fn (?EmployeeProfile $record): bool => $record !== null)
                            ->helperText(__('Staff and teachers who have no employee file yet. Add the person under Staff or Teachers first.')),
                        TextInput::make('employee_code')
                            ->label(__('Employee No.'))
                            ->placeholder(__('Numbered automatically'))
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('designation')->label(__('Designation'))->required()->maxLength(255),
                        TextInput::make('department')->label(__('Department'))->maxLength(255),
                        Select::make('campus_id')
                            ->label(__('Branch'))
                            ->options(fn (): array => static::campusOptions())
                            ->default(fn (): ?int => auth()->user()?->activeBranchId())
                            ->required(),
                        Select::make('employment_type')->label(__('Employment'))->options(EmploymentType::class)->default(EmploymentType::Permanent)->required(),
                        DatePicker::make('joined_on')->label(__('Joined on'))->default(now())->required(),
                        Select::make('status')->label(__('Status'))->options(EmployeeStatus::class)->default(EmployeeStatus::Active)->required()->live(),
                        DatePicker::make('left_on')
                            ->label(__('Left on'))
                            ->visible(fn (Get $get): bool => self::statusValue($get('status')) === EmployeeStatus::Left->value)
                            ->required(fn (Get $get): bool => self::statusValue($get('status')) === EmployeeStatus::Left->value),
                    ]),
                Section::make(__('Contact'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('cnic')->label(__('CNIC'))->placeholder('35202-1234567-1')->maxLength(20),
                        TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(30),
                        Textarea::make('address')->label(__('Address'))->columnSpanFull()->rows(2),
                        TextInput::make('emergency_contact_name')->label(__('Emergency contact'))->maxLength(255),
                        TextInput::make('emergency_contact_phone')->label(__('Emergency phone'))->tel()->maxLength(30),
                    ]),
                Section::make(__('Salary'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (): bool => static::seesSalaries())
                    ->description(__('Only staff who manage the payroll can change these. The allowances and deductions are the same every month and go on the salary slip when it is made.'))
                    ->schema([
                        TextInput::make('basic_salary')
                            ->label(__('Basic salary'))
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->prefix(config('campusflow.currency_symbol'))
                            ->disabled(fn (): bool => ! (auth()->user()?->can('payroll.manage') ?? false)),
                        TextInput::make('bank_name')->label(__('Bank'))->maxLength(255),
                        TextInput::make('bank_account')->label(__('Account / IBAN'))->maxLength(50),
                        Repeater::make('payItems')
                            ->label(__('Fixed allowances and deductions'))
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->addActionLabel(__('Add allowance or deduction'))
                            ->defaultItems(0)
                            ->disabled(fn (): bool => ! (auth()->user()?->can('payroll.manage') ?? false))
                            ->schema([
                                Select::make('kind')->label(__('Type'))->options(PayItemKind::class)->required(),
                                TextInput::make('name')->label(__('Name'))->placeholder(__('House rent, Advance recovery'))->required()->maxLength(255),
                                TextInput::make('amount')->label(__('Amount'))->integer()->minValue(1)->required(),
                            ]),
                    ]),
            ]);
    }

    private static function statusValue(mixed $status): ?string
    {
        return $status instanceof BackedEnum ? (string) $status->value : $status;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'campus']))
            ->defaultSort('employee_code')
            ->columns([
                TextColumn::make('employee_code')->label(__('Employee No.'))->searchable()->sortable(),
                TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->description(fn (EmployeeProfile $record): string => $record->designation)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn (Builder $users) => $users->where('firstname', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('department')->label(__('Department'))->placeholder('—')->toggleable(),
                TextColumn::make('campus.name')->label(__('Branch')),
                TextColumn::make('employment_type')->label(__('Employment'))->badge()->color('gray'),
                TextColumn::make('joined_on')->label(__('Joined'))->date('d M Y')->sortable(),
                TextColumn::make('basic_salary')
                    ->label(__('Basic salary'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->alignEnd()
                    ->visible(fn (): bool => static::seesSalaries()),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(EmployeeStatus::class)->default(EmployeeStatus::Active->value),
                SelectFilter::make('employment_type')->label(__('Employment'))->options(EmploymentType::class),
                SelectFilter::make('department')
                    ->label(__('Department'))
                    ->options(fn (): array => EmployeeProfile::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department', 'department')->all()),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeProfiles::route('/'),
        ];
    }
}
