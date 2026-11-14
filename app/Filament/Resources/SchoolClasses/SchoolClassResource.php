<?php

namespace App\Filament\Resources\SchoolClasses;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\Resources\SchoolClasses\Pages\ViewSchoolClass;
use App\Filament\Resources\SchoolClasses\RelationManagers\SubjectsRelationManager;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolClassResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'school.manage';

    protected static string $deletePermission = 'school.manage';

    protected static ?string $model = SchoolClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 10;

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
        return __('Class');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Classes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->columnSpanFull()->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->placeholder('Class 1')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('order')
                        ->label(__('Promotion order'))
                        ->helperText(__('Students are promoted to the class with the next order.'))
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(fn (): int => (int) SchoolClass::query()->max('order') + 1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('order')
                    ->label(__('Order'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subjects_count')
                    ->label(__('Subjects'))
                    ->counts('subjects'),
                TextColumn::make('sections_count')
                    ->label(__('Sections'))
                    ->counts('sections'),
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
            SubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolClasses::route('/'),
            'create' => CreateSchoolClass::route('/create'),
            'view' => ViewSchoolClass::route('/{record}'),
            'edit' => EditSchoolClass::route('/{record}/edit'),
        ];
    }
}
