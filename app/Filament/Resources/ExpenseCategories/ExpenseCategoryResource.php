<?php

namespace App\Filament\Resources\ExpenseCategories;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\ExpenseCategories\Pages\ManageExpenseCategories;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * What the vendor bills are for, such as utilities, stationery or repairs.
 */
class ExpenseCategoryResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'payables.manage';

    protected static string $deletePermission = 'payables.manage';

    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 630;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('payables.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Accounts');
    }

    public static function getModelLabel(): string
    {
        return __('Expense category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Expense categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->placeholder(__('Utilities'))->required()->maxLength(255)->unique(ignoreRecord: true),
                Toggle::make('is_active')->label(__('In use'))->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('bold'),
                IconColumn::make('is_active')->label(__('In use'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenseCategories::route('/'),
        ];
    }
}
