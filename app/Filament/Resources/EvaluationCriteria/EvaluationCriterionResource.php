<?php

namespace App\Filament\Resources\EvaluationCriteria;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\EvaluationCriteria\Pages\ManageEvaluationCriteria;
use App\Models\EvaluationCriterion;
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
 * What the employees are evaluated on, and how much each criterion weighs in the overall score.
 */
class EvaluationCriterionResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'appraisals.manage';

    protected static string $deletePermission = 'appraisals.manage';

    protected static ?string $model = EvaluationCriterion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?int $navigationSort = 570;

    protected static ?string $slug = 'evaluation-criteria';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('appraisals.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Evaluation criterion');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Evaluation criteria');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Criterion'))->placeholder(__('Punctuality'))->required()->maxLength(255),
                TextInput::make('weight')
                    ->label(__('Weight'))
                    ->helperText(__('A criterion with weight 2 counts twice as much as one with weight 1.'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
                TextInput::make('description')->label(__('What to look at'))->maxLength(255)->columnSpanFull(),
                TextInput::make('sort')->label(__('Order'))->integer()->minValue(0)->default(0)->required(),
                Toggle::make('is_active')->label(__('In use'))->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('Criterion'))->description(fn (EvaluationCriterion $record): ?string => $record->description)->weight('bold'),
                TextColumn::make('weight')->label(__('Weight'))->alignEnd(),
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
            'index' => ManageEvaluationCriteria::route('/'),
        ];
    }
}
