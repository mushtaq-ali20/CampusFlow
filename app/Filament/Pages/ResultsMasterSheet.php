<?php

namespace App\Filament\Pages;

use App\Enums\ExamType;
use App\Models\Section;
use App\Services\GradingService;
use App\Services\ResultService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The results of a section in an exam term: every student with their marks in each subject, total, percentage,
 * grade, position and result, a summary of the class and the analysis of each subject. The sheet downloads for
 * Excel, and the result cards of the whole section as one PDF.
 */
class ResultsMasterSheet extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 7;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('evaluation.view') ?? false) || ($user?->isTeacher() ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Results master sheet');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Results master sheet');
    }

    /**
     * The sections whose results the user sees: all the sections they can see, or for a teacher without the
     * evaluation permission, the sections they teach.
     *
     * @return Builder<Section>
     */
    protected function sections(): Builder
    {
        $user = auth()->user();

        return Section::query()
            ->visibleTo($user)
            ->when(! $user->can('evaluation.view'), fn (Builder $query) => $query->taughtBy($user));
    }

    public function section(): ?Section
    {
        $id = $this->filters['section_id'] ?? null;

        return filled($id) ? $this->sections()->with(['schoolClass.subjects', 'year'])->find($id) : null;
    }

    public function exam(): ExamType
    {
        return ExamType::tryFrom((string) ($this->filters['exam'] ?? '')) ?? ExamType::Annual;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sheet(): ?array
    {
        $section = $this->section();

        return $section ? app(ResultService::class)->sheet($section, $this->exam()) : null;
    }

    public function mount(): void
    {
        $this->form->fill(['exam' => ExamType::Annual->value]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    Select::make('section_id')
                        ->label(__('Section'))
                        ->options(fn (): array => $this->sections()
                            ->with(['schoolClass', 'campus', 'year'])
                            ->get()
                            ->sortBy(fn (Section $section): string => $section->year?->name.$section->campus->name.str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
                            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name.' · '.$section->year?->name])
                            ->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn () => $this->resetTable()),
                    Select::make('exam')
                        ->label(__('Exam'))
                        ->options(ExamType::class)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->resetTable()),
                ])->extraAttributes(['class' => 'cf-filter-bar']),
            ])
            ->statePath('filters');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('form'),
                View::make('filament.pages.results-summary')
                    ->viewData(fn (): array => ['sheet' => $this->sheet()])
                    ->visible(fn (): bool => $this->section() !== null),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        $sheet = $this->sheet();
        $grading = app(GradingService::class);

        $subjectColumns = collect($sheet['subjects'] ?? [])->map(fn (array $subject): TextColumn => TextColumn::make('subject_'.$subject['id'])
            ->label($subject['name'])
            ->tooltip(__('Out of :total, passing :passing', ['total' => $subject['total'], 'passing' => $subject['passing']]))
            ->state(fn (array $record): ?string => $this->formatMarks($record['subjects'][$subject['id']]['marks']))
            ->color(fn (array $record): ?string => $record['subjects'][$subject['id']]['passed'] === false ? 'danger' : null)
            ->placeholder('—')
            ->alignEnd())->all();

        return $table
            ->heading(fn (): ?string => $sheet ? $sheet['section']->full_name.' · '.$sheet['exam']->getLabel() : null)
            ->records(fn (): array => collect($sheet['rows'] ?? [])
                ->mapWithKeys(fn (array $row): array => [$row['admission']->id => $row])
                ->all())
            ->emptyStateHeading(fn (): string => $this->section() ? __('No students in this section') : __('Choose a section'))
            ->paginated(false)
            ->headerActions([
                Action::make('download')
                    ->label(__('Download for Excel'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => $this->section() !== null)
                    ->action(fn (): StreamedResponse => $this->download()),
                Action::make('resultCards')
                    ->label(__('Result cards (PDF)'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->visible(fn (): bool => $this->section() !== null)
                    ->action(fn (): StreamedResponse => app(ResultService::class)->cards($this->section(), $this->exam())),
            ])
            ->columns([
                TextColumn::make('position')->label(__('Pos.'))->placeholder('—'),
                TextColumn::make('name')->label(__('Student'))->description(fn (array $record): string => $record['admission']->admission_number),
                ...$subjectColumns,
                TextColumn::make('obtained')
                    ->label(__('Total'))
                    ->state(fn (array $record): string => $this->formatMarks($record['obtained']).' / '.$record['total'])
                    ->alignEnd(),
                TextColumn::make('percentage')
                    ->label('%')
                    ->state(fn (array $record): ?string => $record['percentage'] !== null ? $record['percentage'].'%' : null)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('grade')->label(__('Grade'))->placeholder('—'),
                TextColumn::make('result')
                    ->label(__('Result'))
                    ->state(fn (array $record): string => ResultService::resultLabel($record['result']))
                    ->badge()
                    ->color(fn (array $record): string => match ($record['result']) {
                        'pass' => 'success',
                        'fail' => 'danger',
                        'incomplete' => 'warning',
                        default => 'gray',
                    }),
            ]);
    }

    /**
     * The sheet as rows, with their headings first.
     *
     * @return list<list<string|int|float|null>>
     */
    public function rows(): array
    {
        $sheet = $this->sheet();

        if ($sheet === null) {
            return [];
        }

        return [
            [__('Position'), __('Student'), __('Admission No.'), ...array_map(fn (array $subject): string => $subject['name'].' ('.$subject['total'].')', $sheet['subjects']), __('Total'), __('Out of'), '%', __('Grade'), __('Result')],
            ...array_map(fn (array $row): array => [
                $row['position'],
                $row['name'],
                $row['admission']->admission_number,
                ...array_map(fn (array $subject) => $row['subjects'][$subject['id']]['marks'], $sheet['subjects']),
                $row['obtained'],
                $row['total'],
                $row['percentage'],
                $row['grade'],
                ResultService::resultLabel($row['result']),
            ], $sheet['rows']),
        ];
    }

    public function download(): StreamedResponse
    {
        $rows = $this->rows();
        $name = 'results-'.str($this->section()->full_name)->slug().'-'.str($this->exam()->value)->slug().'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function formatMarks(?float $marks): ?string
    {
        return $marks === null ? null : rtrim(rtrim(number_format($marks, 2, '.', ''), '0'), '.');
    }
}
