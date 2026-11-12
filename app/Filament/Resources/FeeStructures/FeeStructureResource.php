<?php

namespace App\Filament\Resources\FeeStructures;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\FeeStructures\Pages\ManageFeeStructures;
use App\Models\Campus;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\DeleteAction;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The fee of each class, by fee head and academic year. A structure for a branch overrides the structure for all
 * branches. The fees charged to a student come from the structure of their class.
 */
class FeeStructureResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'fees.manage';

    protected static string $deletePermission = 'fees.manage';

    protected static ?string $model = FeeStructure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Fees');
    }

    public static function getModelLabel(): string
    {
        return __('Fee structure');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Fee structures');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
                    ->required(),
                Select::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Year::current()?->id)
                    ->required(),
                Select::make('fee_head_id')
                    ->label(__('Fee head'))
                    ->options(fn (): array => FeeHead::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('school_class_id', $get('school_class_id'))
                            ->where('year_id', $get('year_id'))
                            ->when(
                                filled($get('campus_id')),
                                fn (Unique $rule): Unique => $rule->where('campus_id', $get('campus_id')),
                                fn (Unique $rule): Unique => $rule->whereNull('campus_id'),
                            ),
                    )
                    ->validationMessages(['unique' => __('This fee is already defined for this class, academic year and branch.')]),
                Select::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Campus::query()->get()->pluck('name', 'id')->all())
                    ->placeholder(__('All branches')),
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->prefix(fn (): string => (string) config('campusflow.currency_symbol'))
                    ->integer()
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['schoolClass', 'feeHead', 'campus', 'year'])
                ->when(
                    auth()->user()?->activeBranchId() !== null,
                    fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('campus_id')->orWhere('campus_id', auth()->user()->activeBranchId())),
                ))
            ->defaultSort('school_class_id')
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label(__('Class'))
                    ->sortable(),
                TextColumn::make('feeHead.name')
                    ->label(__('Fee head')),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money(config('campusflow.currency_code', 'PKR'), locale: 'en')
                    ->sortable(),
                TextColumn::make('campus.name')
                    ->label(__('Branch'))
                    ->placeholder(__('All branches')),
                TextColumn::make('year.name')
                    ->label(__('Academic Year')),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Year::current()?->id),
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Campus::query()->get()->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeeStructures::route('/'),
        ];
    }
}
