<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\Vendors\Pages\ManageVendors;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\FeeService;
use App\Services\PayableService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The suppliers of the school, with what is owed to each and their statement of bills and payments.
 */
class VendorResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'payables.manage';

    protected static string $deletePermission = 'payables.manage';

    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 610;

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
        return __('Vendor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vendors');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                TextInput::make('contact_person')->label(__('Contact person'))->maxLength(255),
                TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(30),
                TextInput::make('email')->label(__('Email'))->email()->maxLength(255),
                TextInput::make('ntn')->label(__('NTN'))->maxLength(30),
                Toggle::make('is_active')->label(__('In use'))->default(true)->inline(false),
                TextInput::make('address')->label(__('Address'))->maxLength(255)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('bills')
                ->with(['bills' => fn ($bills) => $bills->visibleTo(auth()->user())->outstanding()]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->description(fn (Vendor $record): ?string => $record->contact_person)->searchable()->sortable()->weight('bold'),
                TextColumn::make('phone')->label(__('Phone'))->placeholder('—'),
                TextColumn::make('owed')
                    ->label(__('Owed'))
                    ->state(fn (Vendor $record): int => (int) $record->bills->sum(fn (VendorBill $bill): int => $bill->balance))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null)
                    ->alignEnd(),
                IconColumn::make('is_active')->label(__('In use'))->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('In use')),
            ])
            ->recordActions([
                Action::make('statement')
                    ->label(__('Statement'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Vendor $record): bool => $record->bills_count > 0)
                    ->action(fn (Vendor $record): StreamedResponse => static::statement($record)),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Vendor $record): bool => $record->bills_count === 0),
            ]);
    }

    /**
     * The bills and payments of the vendor with the running balance, for Excel.
     */
    public static function statement(Vendor $vendor): StreamedResponse
    {
        $rows = [
            [__('Date'), __('Entry'), __('Reference'), __('Details'), __('Bill'), __('Payment'), __('Balance')],
            ...array_map(fn (array $entry): array => [
                $entry['date']->format('d/m/Y'),
                $entry['type'] === 'bill' ? __('Bill') : __('Payment'),
                $entry['reference'],
                $entry['description'] ?? '',
                $entry['bill'] ?: '',
                $entry['payment'] ?: '',
                $entry['balance'],
            ], app(PayableService::class)->ledger($vendor, auth()->user())),
        ];

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, 'vendor-statement-'.str($vendor->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendors::route('/'),
        ];
    }
}
