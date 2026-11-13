<?php

namespace App\Filament\Pages;

use App\Filament\Actions\SendWhatsAppAction;
use App\Models\Section;
use App\Services\FeeLedgerService;
use App\Services\FeeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
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
 * What the students owe by how long it is past due (not due yet, up to 30, 60 and 90 days, and over 90), with how
 * each family pays and when they were last reminded on WhatsApp. Downloads for Excel.
 */
class FeeAging extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static ?int $navigationSort = 70;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Fees');
    }

    public static function getNavigationLabel(): string
    {
        return __('Aging and recovery');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Aging and recovery');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_id')
                    ->label(__('Section'))
                    ->placeholder(__('All sections'))
                    ->options(fn (): array => Section::query()
                        ->visibleTo(auth()->user())
                        ->with(['schoolClass', 'campus'])
                        ->get()
                        ->sortBy(fn (Section $section): string => $section->campus->name.str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
                        ->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name])
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable()),
            ])
            ->statePath('filters');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $sectionId = filled($this->filters['section_id'] ?? null) ? (int) $this->filters['section_id'] : null;

        return app(FeeLedgerService::class)->aging(auth()->user(), $sectionId)
            ->mapWithKeys(fn (array $row): array => [$row['admission']->id => $row])
            ->all();
    }

    public function table(Table $table): Table
    {
        $money = fn (string $bucket) => fn (array $record): ?string => $record['buckets'][$bucket] > 0 ? FeeService::money($record['buckets'][$bucket]) : null;

        return $table
            ->records(fn (): array => $this->rows())
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('Nothing is outstanding'))
            ->headerActions([
                Action::make('download')
                    ->label(__('Download for Excel'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->download()),
            ])
            ->columns([
                TextColumn::make('student')
                    ->label(__('Student'))
                    ->state(fn (array $record): string => $record['admission']->student->name)
                    ->description(fn (array $record): string => $record['admission']->section?->full_name.' · '.$record['admission']->admission_number),
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
                TextColumn::make('habit')
                    ->label(__('Pays'))
                    ->state(fn (array $record): string => $record['habit']->getLabel())
                    ->badge()
                    ->color(fn (array $record): string => $record['habit']->getColor()),
                TextColumn::make('lastReminder')
                    ->label(__('Last reminder'))
                    ->state(fn (array $record): ?string => $record['lastReminder']?->format('d M Y'))
                    ->placeholder(__('Never')),
            ])
            ->recordActions([
                SendWhatsAppAction::make()
                    ->label(__('Remind'))
                    ->student(fn (array $record) => $record['admission']->student)
                    ->message(fn (array $record): string => app(FeeLedgerService::class)->reminder($record))
                    ->aboutUsing(fn (array $record) => $record['admission']),
            ]);
    }

    public function download(): StreamedResponse
    {
        $rows = [
            [__('Student'), __('Admission No.'), __('Section'), __('Not due'), __('1-30 days'), __('31-60 days'), __('61-90 days'), __('Over 90 days'), __('Total'), __('Pays')],
            ...array_map(fn (array $row): array => [
                $row['admission']->student->name,
                $row['admission']->admission_number,
                $row['admission']->section?->full_name,
                ...array_values($row['buckets']),
                $row['total'],
                $row['habit']->getLabel(),
            ], array_values($this->rows())),
        ];

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, 'fee-aging-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
