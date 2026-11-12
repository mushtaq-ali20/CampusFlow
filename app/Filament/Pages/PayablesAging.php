<?php

namespace App\Filament\Pages;

use App\Services\FeeService;
use App\Services\PayableService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the school owes each vendor by how long it is past due (not due yet, up to 30, 60 and 90 days, and over
 * 90), with the totals owed, overdue, due this week and paid this month. Downloads for Excel.
 */
class PayablesAging extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 620;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('payables.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Accounts');
    }

    public static function getNavigationLabel(): string
    {
        return __('Payables aging');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Payables aging');
    }

    public function getSubheading(): ?string
    {
        $summary = app(PayableService::class)->summary(auth()->user());

        return __('Owed :owed · Overdue :overdue · Due within 7 days :week · Paid this month :paid', [
            'owed' => FeeService::money($summary['owed']),
            'overdue' => FeeService::money($summary['overdue']),
            'week' => FeeService::money($summary['dueThisWeek']),
            'paid' => FeeService::money($summary['paidThisMonth']),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return collect(app(PayableService::class)->aging(auth()->user()))
            ->mapWithKeys(fn (array $row): array => [$row['vendor']->id => $row])
            ->all();
    }

    public function table(Table $table): Table
    {
        $money = fn (string $bucket) => fn (array $record): ?string => $record[$bucket] > 0 ? FeeService::money($record[$bucket]) : null;

        return $table
            ->records(fn (): array => $this->rows())
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('Nothing is owed'))
            ->headerActions([
                Action::make('download')
                    ->label(__('Download for Excel'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->download()),
            ])
            ->columns([
                TextColumn::make('vendor')
                    ->label(__('Vendor'))
                    ->state(fn (array $record): string => $record['vendor']->name)
                    ->description(fn (array $record): string => trans_choice('{1} :count bill|[2,*] :count bills', $record['bills'], ['count' => $record['bills']])),
                TextColumn::make('current')->label(__('Not due'))->state($money('current'))->placeholder('—')->alignEnd(),
                TextColumn::make('days_30')->label(__('1-30 days'))->state($money('days_30'))->placeholder('—')->alignEnd(),
                TextColumn::make('days_60')->label(__('31-60 days'))->state($money('days_60'))->placeholder('—')->alignEnd(),
                TextColumn::make('days_90')->label(__('61-90 days'))->state($money('days_90'))->placeholder('—')->alignEnd(),
                TextColumn::make('over_90')->label(__('Over 90 days'))->state($money('over_90'))->placeholder('—')->color('danger')->alignEnd(),
                TextColumn::make('total')
                    ->label(__('Total'))
                    ->state(fn (array $record): string => FeeService::money($record['total']))
                    ->weight('bold')
                    ->alignEnd(),
            ]);
    }

    public function download(): StreamedResponse
    {
        $rows = [
            [__('Vendor'), __('Bills'), __('Not due'), __('1-30 days'), __('31-60 days'), __('61-90 days'), __('Over 90 days'), __('Total')],
            ...array_map(fn (array $row): array => [
                $row['vendor']->name,
                $row['bills'],
                $row['current'],
                $row['days_30'],
                $row['days_60'],
                $row['days_90'],
                $row['over_90'],
                $row['total'],
            ], array_values($this->rows())),
        ];

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, 'payables-aging-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
