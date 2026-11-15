<?php

namespace App\Filament\Resources\FeeHeads;

use App\Enums\FeeFrequency;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\FeeHeads\Pages\ManageFeeHeads;
use App\Models\FeeHead;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The kinds of fee: monthly tuition, one-time admission fee, annual fee... Fee heads are deactivated, not deleted,
 * because the vouchers refer to them.
 */
class FeeHeadResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'fees.manage';

    protected static string $deletePermission = 'fees.manage';

    protected static ?string $model = FeeHead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

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
        return __('Fees');
    }

    public static function getModelLabel(): string
    {
        return __('Fee head');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Fee heads');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->placeholder('Tuition Fee')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('frequency')
                    ->label(__('Charged'))
                    ->options(FeeFrequency::class)
                    ->required()
                    ->helperText(__('Monthly fees are on every voucher. One-time fees are charged on the first voucher of a student. Annual fees are charged in the month set in the fee settings.')),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('frequency')
                    ->label(__('Charged'))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->button()->color('gray'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeeHeads::route('/'),
        ];
    }
}
