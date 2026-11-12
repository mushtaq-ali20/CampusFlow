<?php

namespace App\Filament\Resources\FeeVouchers;

use App\Enums\VoucherStatus;
use App\Filament\Actions\CollectPaymentAction;
use App\Filament\Resources\FeeVouchers\Pages\ListFeeVouchers;
use App\Filament\Resources\FeeVouchers\Pages\ViewFeeVoucher;
use App\Filament\Resources\FeeVouchers\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\FeeVouchers\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\FeeVouchers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\FeeVoucher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Year;
use App\Services\FeeService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as InfolistSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The monthly fee vouchers of the students. They are generated from the fee structure of the class and are
 * never edited by hand: payments, discounts and fines change their totals. Branch staff see their branch only.
 */
class FeeVoucherResource extends Resource
{
    protected static ?string $model = FeeVoucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 40;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
        return __('Fee voucher');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Fee vouchers');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfolistSection::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('voucher_number')->label(__('Voucher')),
                        TextEntry::make('admission.student.name')->label(__('Student')),
                        TextEntry::make('admission.admission_number')->label(__('Admission No.')),
                        TextEntry::make('campus.name')->label(__('Branch')),
                        TextEntry::make('schoolClass.name')->label(__('Class')),
                        TextEntry::make('section.name')->label(__('Section')),
                        TextEntry::make('month')->label(__('Fee month'))->date('F Y'),
                        TextEntry::make('due_date')->label(__('Due date'))->date(),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('gross_amount')->label(__('Fees'))->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                        TextEntry::make('discount_amount')->label(__('Discounts'))->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                        TextEntry::make('fine_amount')->label(__('Late fine'))->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                        TextEntry::make('net_amount')->label(__('Total payable'))->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                        TextEntry::make('paid_amount')->label(__('Paid'))->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                        TextEntry::make('balance')->label(__('Balance'))->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['admission.student', 'campus', 'schoolClass', 'section']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('voucher_number')
                    ->label(__('Voucher'))
                    ->searchable(),
                TextColumn::make('admission.student.name')
                    ->label(__('Student'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('admission.student.user', fn (Builder $users) => $users
                        ->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('schoolClass.name')
                    ->label(__('Class')),
                TextColumn::make('section.name')
                    ->label(__('Section')),
                TextColumn::make('campus.name')
                    ->label(__('Branch')),
                TextColumn::make('month')
                    ->label(__('Fee month'))
                    ->date('M Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('Due date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('net_amount')
                    ->label(__('Total payable'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label(__('Paid'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => SectionResource::branchOptions()),
                SelectFilter::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => Section::query()->visibleTo(auth()->user())->with('schoolClass')->get()->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name])->all()),
                SelectFilter::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(VoucherStatus::class),
                Filter::make('month')
                    ->label(__('Fee month'))
                    ->schema([
                        DatePicker::make('month')
                            ->label(__('Fee month'))
                            ->native(false)
                            ->displayFormat('F Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['month'] ?? null,
                        fn (Builder $query, string $month): Builder => $query->whereYear('month', substr($month, 0, 4))->whereMonth('month', substr($month, 5, 2)),
                    )),
                Filter::make('overdue')
                    ->label(__('Overdue'))
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', VoucherStatus::Paid)->whereDate('due_date', '<', now())),
            ])
            ->recordActions([
                ViewAction::make(),
                CollectPaymentAction::make(),
            ]);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            InstallmentsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeVouchers::route('/'),
            'view' => ViewFeeVoucher::route('/{record}'),
        ];
    }
}
