<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\Campus;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * The staff of the school: users with a role, and for branch staff the branch they work for.
 * Only administrators manage them. Staff without a branch work across all branches.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 500;

    protected static ?string $slug = 'staff';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return static::canAccess() ? Response::allow() : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('Staff member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Staff');
    }

    /**
     * Only the users who have a role. With a branch chosen in the branch switcher, the staff of that branch and
     * the staff who work across all branches.
     */
    public static function getEloquentQuery(): Builder
    {
        $branchId = auth()->user()?->activeBranchId();

        return parent::getEloquentQuery()
            ->whereHas('roles')
            ->when($branchId !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('campus_id')->orWhere('campus_id', $branchId)))
            ->with(['roles', 'campus']);
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'name')->all();
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
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (?User $record): bool => $record === null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText(fn (?User $record): ?string => $record ? __('Leave empty to keep the current password.') : null),
                Select::make('role')
                    ->label(__('Role'))
                    ->options(fn (): array => static::roleOptions())
                    ->required()
                    ->live(),
                Select::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Campus::query()->get()->pluck('name', 'id')->all())
                    ->placeholder(__('All branches'))
                    ->helperText(__('A staff member with a branch only sees and manages the records of that branch.'))
                    ->hidden(fn (Get $get): bool => $get('role') === 'admin'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('lastname')
            ->columns([
                TextColumn::make('lastname')
                    ->label(__('Last name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('firstname')
                    ->label(__('First name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge(),
                TextColumn::make('campus.name')
                    ->label(__('Branch'))
                    ->placeholder(__('All branches')),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('Role'))
                    ->options(fn (): array => static::roleOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $data['value']))
                        : $query),
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Campus::query()->get()->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => [...$data, 'role' => $record->roles->first()?->name])
                    ->using(fn (User $record, array $data): User => static::saveStaffMember($record, $data)),
                Action::make('removeAccess')
                    ->label(__('Remove access'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The person keeps their other records, such as a teacher profile, but can no longer use the staff screens.'))
                    ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                    ->action(function (User $record): void {
                        $record->syncRoles([]);
                        $record->update(['campus_id' => null]);
                    }),
            ]);
    }

    /**
     * Creates or updates a staff member. Administrators work across all branches.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveStaffMember(User $user, array $data): User
    {
        $role = $data['role'];
        unset($data['role']);

        if ($role === 'admin') {
            $data['campus_id'] = null;
        }

        $user->fill($data)->save();
        $user->syncRoles([$role]);

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaff::route('/'),
        ];
    }
}
