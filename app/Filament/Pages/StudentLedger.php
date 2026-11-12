<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Student;
use App\Services\FeeLedgerService;
use App\Services\FeeService;
use BackedEnum;
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

/**
 * The fee ledger of a student: every charge, discount, payment and refund in date order, with the balance after
 * each. Opens from the fee vouchers or with a student chosen here.
 */
class StudentLedger extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 75;

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
        return __('Student ledger');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Student ledger');
    }

    public function mount(): void
    {
        $this->form->fill(['student_id' => request()->integer('student') ?: null]);
    }

    public function student(): ?Student
    {
        return filled($this->filters['student_id'] ?? null) ? Student::query()->find($this->filters['student_id']) : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label(__('Student'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => AdmissionResource::searchStudents($search))
                    ->getOptionLabelUsing(fn ($value): ?string => Student::query()->find($value)?->name)
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

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): ?string => $this->student()?->name)
            ->records(function (): array {
                $student = $this->student();

                if ($student === null) {
                    return [];
                }

                return collect(app(FeeLedgerService::class)->ledger($student, auth()->user()))
                    ->mapWithKeys(fn (array $entry, int $index): array => [$index + 1 => $entry])
                    ->all();
            })
            ->paginated(false)
            ->emptyStateHeading(fn (): string => $this->student() ? __('No fee records for this student') : __('Choose a student'))
            ->columns([
                TextColumn::make('date')->label(__('Date'))->state(fn (array $record): string => $record['date']->format('d M Y')),
                TextColumn::make('description')->label(__('Description'))->wrap(),
                TextColumn::make('reference')->label(__('Reference')),
                TextColumn::make('debit')->label(__('Charged'))->state(fn (array $record): ?string => $record['debit'] > 0 ? FeeService::money($record['debit']) : null)->placeholder('—')->alignEnd(),
                TextColumn::make('credit')->label(__('Paid or reduced'))->state(fn (array $record): ?string => $record['credit'] > 0 ? FeeService::money($record['credit']) : null)->placeholder('—')->alignEnd(),
                TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->state(fn (array $record): string => FeeService::money($record['balance']))
                    ->color(fn (array $record): string => $record['balance'] > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignEnd(),
            ]);
    }
}
