<?php

namespace App\Filament\Resources\DisciplineCategories;

use App\Enums\DisciplineConsequence;
use App\Enums\DisciplineKind;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\DisciplineCategories\Pages\ManageDisciplineCategories;
use App\Models\DisciplineCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The kinds of good conduct (merits) and misconduct (violations), with their points and, for a violation, what
 * follows. Categories are deactivated, not deleted, because the records refer to them.
 */
class DisciplineCategoryResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'discipline.manage';

    protected static string $deletePermission = 'discipline.manage';

    protected static ?string $model = DisciplineCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('discipline.view') ?? false;
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
        return __('Guidance');
    }

    public static function getModelLabel(): string
    {
        return __('Discipline category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Discipline categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->placeholder(__('Late to class'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                ToggleButtons::make('kind')
                    ->label(__('Kind'))
                    ->options(DisciplineKind::class)
                    ->default(DisciplineKind::Violation)
                    ->inline()
                    ->required()
                    ->live(),
                TextInput::make('points')
                    ->label(__('Points'))
                    ->helperText(__('Added to the student\'s total for a merit, taken away for a violation.'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
                Select::make('consequence')
                    ->label(__('What follows'))
                    ->options(DisciplineConsequence::class)
                    ->default(DisciplineConsequence::Noted)
                    ->required(fn (Get $get): bool => self::isViolation($get('kind')))
                    ->visible(fn (Get $get): bool => self::isViolation($get('kind'))),
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
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('Kind'))
                    ->badge(),
                TextColumn::make('points')
                    ->label(__('Points'))
                    ->formatStateUsing(fn (DisciplineCategory $record): string => sprintf('%+d', $record->signedPoints()))
                    ->color(fn (DisciplineCategory $record): string => $record->kind->getColor())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('consequence')
                    ->label(__('What follows'))
                    ->badge()
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('Kind'))
                    ->options(DisciplineKind::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::withoutConsequenceForMerits($data)),
            ]);
    }

    /**
     * A merit has no consequence.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withoutConsequenceForMerits(array $data): array
    {
        if (! self::isViolation($data['kind'] ?? null)) {
            $data['consequence'] = null;
        }

        return $data;
    }

    private static function isViolation(DisciplineKind|string|null $kind): bool
    {
        return ($kind instanceof DisciplineKind ? $kind : DisciplineKind::tryFrom((string) $kind)) === DisciplineKind::Violation;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDisciplineCategories::route('/'),
        ];
    }
}
