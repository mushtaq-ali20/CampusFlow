<?php

namespace App\Filament\Resources\ComplaintCategories;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\ComplaintCategories\Pages\ManageComplaintCategories;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Models\ComplaintCategory;
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
 * The kinds of complaint, with their time limit, who handles a new complaint and to whom it is escalated.
 * Categories are deactivated, not deleted, because complaints refer to them.
 */
class ComplaintCategoryResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'complaints.configure';

    protected static string $deletePermission = 'complaints.configure';

    protected static ?string $model = ComplaintCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 80;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('complaints.configure') ?? false;
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
        return __('Front desk');
    }

    public static function getModelLabel(): string
    {
        return __('Complaint category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Complaint categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->placeholder(__('Transport'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('response_hours')
                    ->label(__('Time limit (hours)'))
                    ->helperText(__('For a medium priority. Twice as long for low, half for high, a quarter for urgent.'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(720)
                    ->default(24)
                    ->required(),
                Select::make('assign_to_id')
                    ->label(__('Handled by'))
                    ->options(fn (): array => ComplaintResource::staffOptions())
                    ->searchable(),
                Select::make('escalate_to_id')
                    ->label(__('Escalated to'))
                    ->helperText(__('Receives the complaints still open after the time limit.'))
                    ->options(fn (): array => ComplaintResource::staffOptions())
                    ->searchable(),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('response_hours')->label(__('Time limit (hours)'))->alignEnd(),
                TextColumn::make('assignTo.name')->label(__('Handled by'))->placeholder('—'),
                TextColumn::make('escalateTo.name')->label(__('Escalated to'))->placeholder('—'),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageComplaintCategories::route('/'),
        ];
    }
}
