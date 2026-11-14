<?php

namespace App\Filament\Resources\FeeDiscounts;

use App\Enums\DiscountMode;
use App\Enums\DiscountType;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\FeeDiscounts\Pages\ManageFeeDiscounts;
use App\Models\FeeDiscount;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sibling discounts, scholarships and other discounts. They are given to admitted students from
 * the Discounts tab of the admission, and apply to the vouchers created afterwards.
 */
class FeeDiscountResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'fees.manage';

    protected static string $deletePermission = 'fees.manage';

    protected static ?string $model = FeeDiscount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 30;

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
        return __('Discount');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Discounts');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->placeholder('Sibling discount 10%')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('Type'))
                    ->options(DiscountType::class)
                    ->required(),
                Select::make('mode')
                    ->label(__('Discount is'))
                    ->options(DiscountMode::class)
                    ->default(DiscountMode::Percent)
                    ->required()
                    ->live(),
                TextInput::make('value')
                    ->label(fn (Get $get): string => $get('mode') === DiscountMode::Fixed->value ? __('Amount per voucher') : __('Percentage'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(fn (Get $get): ?int => $get('mode') === DiscountMode::Percent->value ? 100 : null)
                    ->required(),
                Select::make('fee_head_id')
                    ->label(__('Applies to'))
                    ->relationship('feeHead', 'name')
                    ->placeholder(__('All monthly fees'))
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('value')
                    ->label(__('Value'))
                    ->formatStateUsing(fn ($state, FeeDiscount $record): string => $record->mode === DiscountMode::Percent ? $state.' %' : number_format($state)),
                TextColumn::make('feeHead.name')
                    ->label(__('Applies to'))
                    ->placeholder(__('All monthly fees')),
                TextColumn::make('admissions_count')
                    ->label(__('Students'))
                    ->counts('admissions'),
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
            'index' => ManageFeeDiscounts::route('/'),
        ];
    }
}
