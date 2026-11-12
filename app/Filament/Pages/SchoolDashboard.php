<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Sections\SectionResource;
use App\Filament\Widgets\ClassExplorer;
use App\Filament\Widgets\FeeBranchProgress;
use App\Filament\Widgets\FeeDefaulters;
use App\Filament\Widgets\FeeStats;
use App\Filament\Widgets\FeeSummary;
use App\Filament\Widgets\SchoolActivity;
use App\Filament\Widgets\SchoolToday;
use App\Filament\Widgets\SchoolWelcome;
use App\Filament\Widgets\TimetableMonthly;
use App\Filament\Widgets\TimetableWeekly;
use App\Models\SchoolClass;
use App\Models\Section as SchoolSection;
use App\Models\Teacher;
use App\Models\Year;
use App\Services\ActiveBranch;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * The home page. At the top, the illustrated school: a greeting with the school in numbers, today at school,
 * the classes with their students, and what happened lately. Below, two groups that collapse and expand on their own, each with its own filters:
 * the fees (billed, collected, due, and the defaulters) and the timetable (weekly or monthly).
 * Each user's open or closed groups are remembered in their browser, and their filters in their session.
 * Everything follows the branch chosen in the branch switcher of the top bar; branch staff only see their own branch.
 */
class SchoolDashboard extends Dashboard
{
    public const VIEW_WEEKLY = 'weekly';

    public const VIEW_MONTHLY = 'monthly';

    private const FEE_FILTERS_SESSION_KEY = 'dashboard.fee_filters';

    private const TIMETABLE_FILTERS_SESSION_KEY = 'dashboard.timetable_filters';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $feeFilters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $timetableFilters = [];

    public function mount(): void
    {
        $this->fillFilters('feeFiltersForm', self::FEE_FILTERS_SESSION_KEY);
        $this->fillFilters('timetableFiltersForm', self::TIMETABLE_FILTERS_SESSION_KEY);
    }

    private function fillFilters(string $form, string $sessionKey): void
    {
        $stored = session($sessionKey);

        filled($stored) ? $this->{$form}->fill($stored) : $this->{$form}->fill();
    }

    public function updatedFeeFilters(): void
    {
        session([self::FEE_FILTERS_SESSION_KEY => $this->feeFilters]);
    }

    public function updatedTimetableFilters(): void
    {
        session([self::TIMETABLE_FILTERS_SESSION_KEY => $this->timetableFilters]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents([SchoolWelcome::class, SchoolToday::class])),
                Grid::make(['default' => 1, 'lg' => 3])->schema(fn (): array => $this->getWidgetsSchemaComponents([ClassExplorer::class, SchoolActivity::class])),

                Actions::make([
                    Action::make('expandAll')
                        ->label(__('Expand all'))
                        ->icon(Heroicon::OutlinedChevronDown)
                        ->color('gray')
                        ->size('sm')
                        ->alpineClickHandler("\$dispatch('expand-section', { id: 'fees-group' }); \$dispatch('expand-section', { id: 'timetable-group' })"),
                    Action::make('collapseAll')
                        ->label(__('Collapse all'))
                        ->icon(Heroicon::OutlinedChevronUp)
                        ->color('gray')
                        ->size('sm')
                        ->alpineClickHandler("\$dispatch('collapse-section', { id: 'fees-group' }); \$dispatch('collapse-section', { id: 'timetable-group' })"),
                ])->alignEnd(),

                Section::make(__('Fees'))
                    ->id('fees-group')
                    ->description(__('Fee billed, collected and due, and the fee defaulters.'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->collapsible()
                    ->persistCollapsed()
                    ->visible(fn (): bool => auth()->user()?->can('fees.view') ?? false)
                    ->afterHeader(fn (): array => $this->chips($this->feeFilterLabels()))
                    ->schema([
                        EmbeddedSchema::make('feeFiltersForm'),
                        Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents(
                            [FeeStats::class, FeeBranchProgress::class, FeeSummary::class, FeeDefaulters::class],
                            ['pageFilters' => $this->withActiveBranch($this->feeFilters)],
                        )),
                    ]),

                Section::make(__('Timetable'))
                    ->id('timetable-group')
                    ->description(__('Weekly or monthly timetable. Click a lesson to update or reconfigure the timetable.'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->collapsible()
                    ->persistCollapsed()
                    ->visible(fn (): bool => auth()->user()?->can('school.view') ?? false)
                    ->afterHeader(fn (): array => $this->chips($this->timetableFilterLabels()))
                    ->schema([
                        EmbeddedSchema::make('timetableFiltersForm'),
                        Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents(
                            [($this->timetableFilters['view'] ?? self::VIEW_WEEKLY) === self::VIEW_MONTHLY ? TimetableMonthly::class : TimetableWeekly::class],
                            ['pageFilters' => $this->withActiveBranch($this->timetableFilters)],
                        )),
                    ]),
            ]);
    }

    public function feeFiltersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3, 'xl' => 6])
                    ->schema([
                        $this->classSelect(),
                        $this->sectionSelect(),
                        $this->yearSelect(),
                        DatePicker::make('month')
                            ->label(__('Fee month'))
                            ->native(false)
                            ->displayFormat('F Y'),
                        Actions::make([
                            Action::make('resetFeeFilters')
                                ->label(__('Reset'))
                                ->color('gray')
                                ->action(function (): void {
                                    $this->feeFiltersForm->fill();
                                    $this->updatedFeeFilters();
                                }),
                        ])->verticallyAlignEnd(),
                    ]),
            ])
            ->statePath('feeFilters')
            ->live();
    }

    public function timetableFiltersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3, 'xl' => 6])
                    ->schema([
                        $this->classSelect(),
                        $this->sectionSelect(),
                        Select::make('teacher_id')
                            ->label(__('Teacher'))
                            ->options(fn (): array => SectionResource::teacherOptions())
                            ->placeholder(__('All teachers'))
                            ->searchable(),
                        $this->yearSelect(),
                        ToggleButtons::make('view')
                            ->label(__('View'))
                            ->options([
                                self::VIEW_WEEKLY => __('Weekly'),
                                self::VIEW_MONTHLY => __('Monthly'),
                            ])
                            ->default(self::VIEW_WEEKLY)
                            ->grouped(),
                    ]),
            ])
            ->statePath('timetableFilters')
            ->live();
    }

    /**
     * The filters of a group of widgets, with the branch chosen in the branch switcher of the top bar.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array<string, mixed>
     */
    private function withActiveBranch(?array $filters): array
    {
        return [...($filters ?? []), 'campus_id' => auth()->user()?->activeBranchId()];
    }

    private function classSelect(): Select
    {
        return Select::make('school_class_id')
            ->label(__('Class'))
            ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
            ->placeholder(__('All classes'));
    }

    private function sectionSelect(): Select
    {
        return Select::make('section_id')
            ->label(__('Section'))
            ->options(fn (Get $get): array => SchoolSection::query()
                ->visibleTo(auth()->user())
                ->when(filled($get('school_class_id')), fn ($query) => $query->where('school_class_id', $get('school_class_id')))
                ->with('schoolClass')
                ->get()
                ->mapWithKeys(fn (SchoolSection $section): array => [$section->id => $section->full_name])
                ->all())
            ->placeholder(__('All sections'))
            ->searchable();
    }

    private function yearSelect(): Select
    {
        return Select::make('year_id')
            ->label(__('Academic Year'))
            ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
            ->default(fn (): ?int => Year::current()?->id)
            ->placeholder(__('All years'));
    }

    /**
     * What the fee filters are set to, shown beside the heading so that it reads even when the group is collapsed.
     *
     * @return list<string>
     */
    private function feeFilterLabels(): array
    {
        $filters = $this->feeFilters ?? [];

        return [
            ...$this->commonFilterLabels($filters),
            ...(filled($filters['month'] ?? null) ? [Carbon::parse($filters['month'])->translatedFormat('F Y')] : []),
        ];
    }

    /**
     * @return list<string>
     */
    private function timetableFilterLabels(): array
    {
        $filters = $this->timetableFilters ?? [];

        return [
            ($filters['view'] ?? self::VIEW_WEEKLY) === self::VIEW_MONTHLY ? __('Monthly') : __('Weekly'),
            ...$this->commonFilterLabels($filters),
            ...(filled($filters['teacher_id'] ?? null) ? [Teacher::query()->find($filters['teacher_id'])?->name ?? ''] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function commonFilterLabels(array $filters): array
    {
        return array_values(array_filter([
            filled($filters['year_id'] ?? null) ? Year::query()->find($filters['year_id'])?->name : __('All years'),
            app(ActiveBranch::class)->campus()?->name ?? __('All branches'),
            filled($filters['school_class_id'] ?? null) ? SchoolClass::query()->find($filters['school_class_id'])?->name : null,
            filled($filters['section_id'] ?? null) ? SchoolSection::query()->with('schoolClass')->find($filters['section_id'])?->full_name : null,
        ]));
    }

    /**
     * @param  list<string>  $labels
     * @return list<Text>
     */
    private function chips(array $labels): array
    {
        return array_map(fn (string $label): Text => Text::make($label)->badge()->color('primary'), $labels);
    }
}
