<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Services\AccessModules;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * The roles of the school and what each can do: for every module no access, read only or full access, plus extra
 * rights such as approving refunds. The Administrator is the super user and is never edited; the Teacher and Staff
 * roles can be changed but not renamed or deleted.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 505;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $allowed = static::canAccess() && match ($action) {
            'viewAny', 'create' => true,
            'view', 'update', 'edit' => $record === null || static::isEditable($record),
            'delete' => $record !== null && static::isDeletable($record),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    public static function isEditable(Model $role): bool
    {
        return $role->name !== AccessModules::ADMINISTRATOR;
    }

    public static function isDeletable(Model $role): bool
    {
        return ! in_array($role->name, AccessModules::SYSTEM_ROLES, true) && ! $role->users()->exists();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('Role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Roles');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', 'web')->with('permissions')->withCount('users');
    }

    public static function form(Schema $schema): Schema
    {
        $modules = collect(AccessModules::all())->map(fn (array $module, string $key): Section => Section::make($module['label'])
            ->description($module['screens'])
            ->columns(2)
            ->compact()
            ->schema([
                Radio::make("levels.{$key}")
                    ->hiddenLabel()
                    ->options(AccessModules::levelOptions($key))
                    ->default(AccessModules::NONE)
                    ->inline()
                    ->required(),
                CheckboxList::make("extras.{$key}")
                    ->hiddenLabel()
                    ->options($module['extras'])
                    ->visible($module['extras'] !== [])
                    ->helperText(__('Only applies when the module is not set to no access.')),
            ]))->values()->all();

        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('Role name'))
                    ->placeholder(__('Exam controller'))
                    ->required()
                    ->maxLength(100)
                    ->unique(table: config('permission.table_names.roles'), ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('guard_name', 'web'))
                    ->disabled(fn (?Role $record): bool => $record !== null && in_array($record->name, AccessModules::SYSTEM_ROLES, true))
                    ->formatStateUsing(fn (?string $state): ?string => in_array($state, AccessModules::SYSTEM_ROLES, true) ? AccessModules::roleLabel($state) : $state)
                    ->helperText(fn (?Role $record): ?string => $record !== null && in_array($record->name, AccessModules::SYSTEM_ROLES, true) ? __('This role is part of the system: its access can change, not its name.') : null),
                ...$modules,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('Role'))
                    ->formatStateUsing(fn (string $state): string => AccessModules::roleLabel($state))
                    ->description(fn (Role $record): ?string => match ($record->name) {
                        AccessModules::ADMINISTRATOR => __('System role · super user'),
                        AccessModules::TEACHER => __('System role · own classes, plus the access below'),
                        'secretary' => __('System role'),
                        default => null,
                    })
                    ->weight('bold'),
                TextColumn::make('access')
                    ->label(__('Access'))
                    ->state(fn (Role $record): array => static::summary($record))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, __('full')) || $state === __('All rights on every module') => 'success',
                        str_contains($state, __('read only')) => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('users_count')
                    ->label(__('Users'))
                    ->alignEnd(),
            ])
            ->recordActions([
                EditAction::make()->label(__('Edit access')),
                DeleteAction::make(),
            ]);
    }

    /**
     * How many modules a role opens in full, read only, and not at all.
     *
     * @return list<string>
     */
    public static function summary(Role $role): array
    {
        if ($role->name === AccessModules::ADMINISTRATOR) {
            return [__('All rights on every module')];
        }

        $levels = collect(AccessModules::levelsOf($role))->countBy();
        $extras = count(AccessModules::extrasOf($role));

        return array_values(array_filter([
            ($levels[AccessModules::FULL] ?? 0) > 0 ? trans_choice(':count full', $levels[AccessModules::FULL], ['count' => $levels[AccessModules::FULL]]) : null,
            ($levels[AccessModules::READ] ?? 0) > 0 ? trans_choice(':count read only', $levels[AccessModules::READ], ['count' => $levels[AccessModules::READ]]) : null,
            $extras > 0 ? trans_choice('{1} :count extra right|[2,*] :count extra rights', $extras, ['count' => $extras]) : null,
            ($levels[AccessModules::NONE] ?? 0) > 0 ? trans_choice(':count no access', $levels[AccessModules::NONE], ['count' => $levels[AccessModules::NONE]]) : null,
        ]));
    }

    /**
     * The form state of a role: its level on each module and its extra rights, by module.
     *
     * @return array{levels: array<string, string>, extras: array<string, list<string>>}
     */
    public static function accessData(Role $role): array
    {
        $held = AccessModules::extrasOf($role);

        return [
            'levels' => AccessModules::levelsOf($role),
            'extras' => collect(AccessModules::all())
                ->map(fn (array $module): array => array_values(array_intersect(array_keys($module['extras']), $held)))
                ->all(),
        ];
    }

    /**
     * Saves the levels and extra rights of the form on a role.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveAccess(Role $role, array $data): Role
    {
        $extras = collect($data['extras'] ?? [])->flatten()->filter()->values()->all();

        return AccessModules::apply($role, $data['levels'] ?? [], $extras);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
