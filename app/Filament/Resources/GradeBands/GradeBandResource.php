<?php

namespace App\Filament\Resources\GradeBands;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\GradeBands\Pages\ManageGradeBands;
use App\Models\GradeBand;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The grading scale used on the results master sheet and the result cards.
 */
class GradeBandResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'school.manage';

    protected static string $deletePermission = 'school.manage';

    protected static ?string $model = GradeBand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 35;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('evaluation.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Grading scale');
    }

    public static function getModelLabel(): string
    {
        return __('Grade');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Grading scale');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('grade')
                    ->label(__('Grade'))
                    ->placeholder('A+')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true),
                TextInput::make('min_percentage')
                    ->label(__('From (%)'))
                    ->helperText(__('A percentage from this value up to the next band gets this grade.'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('remarks')
                    ->label(__('Remarks'))
                    ->placeholder(__('Outstanding'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('min_percentage', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('grade')->label(__('Grade'))->weight('bold'),
                TextColumn::make('min_percentage')
                    ->label(__('From'))
                    ->formatStateUsing(fn (float $state): string => rtrim(rtrim(number_format($state, 2), '0'), '.').'%'),
                TextColumn::make('remarks')->label(__('Remarks'))->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make()->button()->color('gray'),
                ActionGroup::make([
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGradeBands::route('/'),
        ];
    }
}
