<?php

namespace App\Filament\Pages;

use App\Enums\ClassAttendanceStatus;
use App\Models\Admission;
use App\Models\ClassAttendance;
use App\Models\Section;
use App\Services\AttendanceReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports on the daily attendance taken by the class teachers: each section on a day (and the sections whose
 * attendance was not submitted), the absent students and the late-comers over a period, and the attendance of each
 * student over a month. Each report downloads as a CSV file that opens in Excel.
 */
class ClassAttendanceReports extends Page implements HasTable
{
    use InteractsWithTable;

    public const CLASS_WISE = 'class_wise';

    public const ABSENTEES = 'absentees';

    public const LATE_COMERS = 'late_comers';

    public const MONTHLY = 'monthly';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?int $navigationSort = 8;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('attendance.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Attendance reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Attendance reports');
    }

    /**
     * @return array<string, string>
     */
    public static function reportOptions(): array
    {
        return [
            self::CLASS_WISE => __('Class-wise'),
            self::ABSENTEES => __('Absentees'),
            self::LATE_COMERS => __('Late-comers'),
            self::MONTHLY => __('Monthly summary'),
        ];
    }

    /**
     * The last twelve months, the current one first.
     *
     * @return array<string, string>
     */
    public static function monthOptions(): array
    {
        return collect(range(0, 11))
            ->mapWithKeys(fn (int $monthsAgo): array => [
                now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m') => now()->startOfMonth()->subMonths($monthsAgo)->translatedFormat('F Y'),
            ])
            ->all();
    }

    public function mount(): void
    {
        $this->form->fill([
            'report' => self::CLASS_WISE,
            'date' => now()->toDateString(),
            'from' => now()->toDateString(),
            'until' => now()->toDateString(),
            'month' => now()->format('Y-m'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $reload = fn () => $this->resetTable();

        return $schema
            ->components([
                Grid::make()->schema([
                    ToggleButtons::make('report')
                        ->label(__('Report'))
                        ->options(self::reportOptions())
                        ->inline()
                        ->required()
                        ->live()
                        ->afterStateUpdated($reload)
                        ->extraFieldWrapperAttributes(['class' => 'cf-filter-full']),
                    DatePicker::make('date')
                        ->label(__('Date'))
                        ->maxDate(now())
                        ->live()
                        ->afterStateUpdated($reload)
                        ->visible(fn (Get $get): bool => $get('report') === self::CLASS_WISE),
                    DatePicker::make('from')
                        ->label(__('From'))
                        ->maxDate(now())
                        ->live()
                        ->afterStateUpdated($reload)
                        ->visible(fn (Get $get): bool => in_array($get('report'), [self::ABSENTEES, self::LATE_COMERS], true)),
                    DatePicker::make('until')
                        ->label(__('To'))
                        ->maxDate(now())
                        ->live()
                        ->afterStateUpdated($reload)
                        ->visible(fn (Get $get): bool => in_array($get('report'), [self::ABSENTEES, self::LATE_COMERS], true)),
                    Select::make('month')
                        ->label(__('Month'))
                        ->options(self::monthOptions())
                        ->live()
                        ->afterStateUpdated($reload)
                        ->visible(fn (Get $get): bool => $get('report') === self::MONTHLY),
                    Select::make('section_id')
                        ->label(__('Section'))
                        ->placeholder(__('All sections'))
                        ->options(fn (): array => Section::query()
                            ->visibleTo(auth()->user())
                            ->whereHas('admissions')
                            ->with(['schoolClass', 'campus'])
                            ->get()
                            ->sortBy(fn (Section $section): string => $section->campus->name.str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
                            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name])
                            ->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated($reload)
                        ->visible(fn (Get $get): bool => $get('report') !== self::CLASS_WISE),
                ])->extraAttributes(['class' => 'cf-filter-bar']),
            ])
            ->statePath('filters');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('form'),
                EmbeddedTable::make(),
            ]);
    }

    public function report(): string
    {
        $report = $this->filters['report'] ?? null;

        return array_key_exists((string) $report, self::reportOptions()) ? $report : self::CLASS_WISE;
    }

    public function table(Table $table): Table
    {
        $table = $table
            ->query(fn (): Builder => $this->reportQuery())
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->headerActions([
                Action::make('download')
                    ->label(__('Download for Excel'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->download()),
            ]);

        return match ($this->report()) {
            self::CLASS_WISE => $table
                ->heading(__('Attendance on :date', ['date' => $this->date('date')->translatedFormat('l d F Y')]))
                ->columns([
                    TextColumn::make('campus.name')->label(__('Branch')),
                    TextColumn::make('full_name')->label(__('Section')),
                    TextColumn::make('classTeacher.name')->label(__('Class teacher'))->placeholder('—'),
                    TextColumn::make('students_count')->label(__('Students'))->alignEnd(),
                    TextColumn::make('present_count')->label(__('Present'))->alignEnd(),
                    TextColumn::make('absent_count')->label(__('Absent'))->alignEnd()->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                    TextColumn::make('late_count')->label(__('Late'))->alignEnd()->color(fn (int $state): ?string => $state > 0 ? 'warning' : null),
                    TextColumn::make('leave_count')->label(__('Leave'))->alignEnd(),
                    TextColumn::make('percentage')
                        ->label(__('Attendance'))
                        ->state(fn (Section $record): ?string => $this->formatPercentage(AttendanceReportService::percentage($record->present_count, $record->late_count, $record->recorded_count)))
                        ->placeholder('—')
                        ->alignEnd(),
                    TextColumn::make('submitted')
                        ->label(__('Submitted'))
                        ->state(fn (Section $record): string => $record->recorded_count > 0 ? __('Submitted') : __('Not submitted'))
                        ->badge()
                        ->color(fn (Section $record): string => $record->recorded_count > 0 ? 'success' : 'danger'),
                ]),
            self::ABSENTEES, self::LATE_COMERS => $table
                ->heading($this->report() === self::ABSENTEES ? __('Absent students') : __('Late-comers'))
                ->columns([
                    TextColumn::make('date')->label(__('Date'))->date('D d M Y'),
                    TextColumn::make('admission.student.name')->label(__('Student')),
                    TextColumn::make('admission.admission_number')->label(__('Admission No.')),
                    TextColumn::make('section.full_name')->label(__('Section')),
                    TextColumn::make('remarks')->label(__('Remarks'))->placeholder('—'),
                ]),
            default => $table
                ->heading(__('Attendance in :month', ['month' => $this->month()->translatedFormat('F Y')]))
                ->columns([
                    TextColumn::make('student.name')->label(__('Student')),
                    TextColumn::make('admission_number')->label(__('Admission No.')),
                    TextColumn::make('section.full_name')->label(__('Section')),
                    TextColumn::make('present_count')->label(__('Present'))->alignEnd(),
                    TextColumn::make('absent_count')->label(__('Absent'))->alignEnd()->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                    TextColumn::make('late_count')->label(__('Late'))->alignEnd()->color(fn (int $state): ?string => $state > 0 ? 'warning' : null),
                    TextColumn::make('leave_count')->label(__('Leave'))->alignEnd(),
                    TextColumn::make('recorded_count')->label(__('Days recorded'))->alignEnd(),
                    TextColumn::make('percentage')
                        ->label(__('Attendance'))
                        ->state(fn (Admission $record): ?string => $this->formatPercentage(AttendanceReportService::percentage($record->present_count, $record->late_count, $record->recorded_count)))
                        ->placeholder('—')
                        ->alignEnd(),
                ]),
        };
    }

    /**
     * @return Builder<Model>
     */
    public function reportQuery(): Builder
    {
        $service = app(AttendanceReportService::class);
        $user = auth()->user();
        $sectionId = filled($this->filters['section_id'] ?? null) ? (int) $this->filters['section_id'] : null;

        return match ($this->report()) {
            self::CLASS_WISE => $service->classWise($this->date('date'), $user),
            self::ABSENTEES => $service->withStatus(ClassAttendanceStatus::Absent, $this->date('from'), $this->date('until'), $user, $sectionId),
            self::LATE_COMERS => $service->withStatus(ClassAttendanceStatus::Late, $this->date('from'), $this->date('until'), $user, $sectionId),
            default => $service->monthly($this->month(), $user, $sectionId),
        };
    }

    /**
     * The rows of the report as they are downloaded, with their headings first.
     *
     * @return list<list<string|int|float|null>>
     */
    public function rows(): array
    {
        $records = $this->reportQuery()->get();

        return match ($this->report()) {
            self::CLASS_WISE => [
                [__('Branch'), __('Section'), __('Class teacher'), __('Students'), __('Present'), __('Absent'), __('Late'), __('Leave'), __('Attendance %'), __('Submitted')],
                ...$records->map(fn (Section $section): array => [
                    $section->campus->name,
                    $section->full_name,
                    $section->classTeacher?->name,
                    $section->students_count,
                    $section->present_count,
                    $section->absent_count,
                    $section->late_count,
                    $section->leave_count,
                    AttendanceReportService::percentage($section->present_count, $section->late_count, $section->recorded_count),
                    $section->recorded_count > 0 ? __('Submitted') : __('Not submitted'),
                ])->all(),
            ],
            self::ABSENTEES, self::LATE_COMERS => [
                [__('Date'), __('Student'), __('Admission No.'), __('Section'), __('Remarks')],
                ...$records->map(fn (ClassAttendance $attendance): array => [
                    $attendance->date->toDateString(),
                    $attendance->admission->student->name,
                    $attendance->admission->admission_number,
                    $attendance->section->full_name,
                    $attendance->remarks,
                ])->all(),
            ],
            default => [
                [__('Student'), __('Admission No.'), __('Section'), __('Present'), __('Absent'), __('Late'), __('Leave'), __('Days recorded'), __('Attendance %')],
                ...$records->map(fn (Admission $admission): array => [
                    $admission->student->name,
                    $admission->admission_number,
                    $admission->section->full_name,
                    $admission->present_count,
                    $admission->absent_count,
                    $admission->late_count,
                    $admission->leave_count,
                    $admission->recorded_count,
                    AttendanceReportService::percentage($admission->present_count, $admission->late_count, $admission->recorded_count),
                ])->all(),
            ],
        };
    }

    public function download(): StreamedResponse
    {
        $rows = $this->rows();
        $fileName = 'attendance-'.str_replace('_', '-', $this->report()).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function date(string $field): Carbon
    {
        $value = $this->filters[$field] ?? null;

        return filled($value) ? Carbon::parse($value) : now();
    }

    private function month(): Carbon
    {
        $value = $this->filters['month'] ?? null;

        return filled($value) && preg_match('/^\d{4}-\d{2}$/', $value) ? Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay() : now()->startOfMonth();
    }

    private function formatPercentage(?float $percentage): ?string
    {
        return $percentage === null ? null : $percentage.'%';
    }
}
