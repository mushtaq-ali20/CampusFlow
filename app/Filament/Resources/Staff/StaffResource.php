<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\Campus;
use App\Models\User;
use App\Services\AccessModules;
use App\Services\UserAccessService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * The users of the school: everyone with a role (Administrator, Principal, Accountant, Staff, Teacher or a role
 * made on the Roles screen), with the branch they work for, whether they may sign in, and when they last did.
 * A user without a branch works across all branches.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 500;

    protected static ?string $slug = 'users';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    protected static function manages(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
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
            'create', 'update' => static::manages(),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    /**
     * The users who have a role. With a branch chosen in the branch switcher, the users of that branch and those
     * who work across all branches.
     */
    public static function getEloquentQuery(): Builder
    {
        $branchId = auth()->user()?->activeBranchId();

        return parent::getEloquentQuery()
            ->whereHas('roles')
            ->when($branchId !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('campus_id')->orWhere('campus_id', $branchId)))
            ->with(['roles', 'campus', 'accessState']);
    }

    /**
     * The roles a user can be given, by their screen name. Only an administrator gives the Administrator role.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $name): bool => $name === AccessModules::ADMINISTRATOR && ! (auth()->user()?->isAdministrator() ?? false))
            ->mapWithKeys(fn (string $name): array => [$name => AccessModules::roleLabel($name)])
            ->all();
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
                    ->label(fn (?User $record): string => $record ? __('New password') : __('Temporary password'))
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (?User $record): bool => $record === null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (?User $record): string => $record
                        ? __('Leave empty to keep the current password. A new password must be changed by the person at their next sign-in.')
                        : __('The person must choose their own password at the first sign-in.')),
                Select::make('role')
                    ->label(__('Role'))
                    ->options(fn (): array => static::roleOptions())
                    ->required()
                    ->live()
                    ->helperText(__('One role per user. The role decides which modules the person can open and change.')),
                Select::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Campus::query()->get()->pluck('name', 'id')->all())
                    ->placeholder(__('All branches'))
                    ->helperText(__('With a branch, the person only sees and manages the records of that branch.'))
                    ->hidden(fn (Get $get): bool => $get('role') === AccessModules::ADMINISTRATOR),
                Toggle::make('is_active')
                    ->label(__('Active: the person can sign in'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('lastname')
            ->columns([
                TextColumn::make('lastname')
                    ->label(__('Name'))
                    ->formatStateUsing(fn (User $record): string => $record->name)
                    ->description(fn (User $record): ?string => $record->email)
                    ->searchable(['firstname', 'lastname', 'email'])
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->formatStateUsing(fn (string $state): string => AccessModules::roleLabel($state))
                    ->badge()
                    ->color(fn (string $state): string => $state === AccessModules::ADMINISTRATOR ? 'warning' : 'info'),
                TextColumn::make('campus.name')
                    ->label(__('Branch'))
                    ->placeholder(__('All branches')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(fn (User $record): string => $record->isActive() ? __('Active') : __('Deactivated'))
                    ->badge()
                    ->color(fn (User $record): string => $record->isActive() ? 'success' : 'gray'),
                TextColumn::make('accessState.last_login_at')
                    ->label(__('Last sign-in'))
                    ->since()
                    ->placeholder(__('Never')),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('Role'))
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name')->mapWithKeys(fn (string $name): array => [$name => AccessModules::roleLabel($name)])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $data['value']))
                        : $query),
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Campus::query()->get()->pluck('name', 'id')->all()),
                TernaryFilter::make('active')
                    ->label(__('Status'))
                    ->trueLabel(__('Active'))
                    ->falseLabel(__('Deactivated'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereDoesntHave('accessState', fn (Builder $state) => $state->where('is_active', false)),
                        false: fn (Builder $query) => $query->whereHas('accessState', fn (Builder $state) => $state->where('is_active', false)),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->color('gray')
                    ->visible(fn (User $record): bool => static::canChange($record))
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => [...$data, 'role' => $record->roles->first()?->name, 'is_active' => $record->isActive()])
                    ->using(fn (User $record, array $data): User => static::save($record, $data)),
                ActionGroup::make([
                    Action::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->visible(fn (User $record): bool => $record->isActive() && static::canChange($record) && ! $record->is(auth()->user()))
                        ->requiresConfirmation()
                        ->modalDescription(__('The person can no longer sign in. Their records and history are kept, and they can be activated again.'))
                        ->action(fn (User $record) => static::attempt(fn () => app(UserAccessService::class)->deactivate($record, auth()->user()), __('User deactivated'))),
                    Action::make('activate')
                        ->label(__('Activate'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (User $record): bool => ! $record->isActive() && static::canChange($record))
                        ->action(fn (User $record) => static::attempt(fn () => app(UserAccessService::class)->activate($record), __('User activated'))),
                ]),
            ]);
    }

    /**
     * Only an administrator changes an administrator.
     */
    protected static function canChange(User $record): bool
    {
        return static::manages() && (! $record->isAdministrator() || (auth()->user()?->isAdministrator() ?? false));
    }

    /**
     * Creates or updates a user with their role, branch and status.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(User $user, array $data): User
    {
        try {
            return app(UserAccessService::class)->save($user, $data, auth()->user());
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    private static function attempt(callable $callback, string $success): void
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
            'index' => ManageStaff::route('/'),
        ];
    }
}
