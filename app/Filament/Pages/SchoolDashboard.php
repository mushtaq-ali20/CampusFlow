<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Sections\SectionResource;
use App\Filament\Widgets\ClassExplorer;
use App\Filament\Widgets\FeeStats;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\SchoolActivity;
use App\Models\SchoolClass;
use App\Models\Section as SchoolSection;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\ActiveBranch;
use App\Services\DashboardLayout;
use App\Services\SchoolOverviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Renderless;

/**
 * The home page. Under the greeting, a row of numbers (attendance and fees), then a board of widgets: what needs
 * attention and the school activity side by side, then fees, timetable and classes across the full width. Each
 * user can drag a widget within its group and minimize it; their layout is saved with their account (see
 * DashboardLayout). The fee and timetable widgets have their own filters, kept in the session.
 * Everything follows the branch chosen in the branch switcher of the top bar; branch staff only see their own branch.
 */
class SchoolDashboard extends Dashboard
{
    public const VIEW_WEEKLY = 'weekly';

    public const VIEW_MONTHLY = 'monthly';

    private const FEE_FILTERS_SESSION_KEY = 'dashboard.fee_filters';

    private const TIMETABLE_FILTERS_SESSION_KEY = 'dashboard.timetable_filters';

    protected string $view = 'filament.pages.school-dashboard';

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

    public function getHeading(): string
    {
        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 17 => __('Good afternoon'),
            default => __('Good evening'),
        };
        $name = auth()->user()?->firstname;

        return filled($name) ? $greeting.', '.$name : $greeting;
    }

    public function getSubheading(): string
    {
        $user = auth()->user();
        $year = Year::current();
        $parts = [now()->translatedFormat('l, j F Y')];

        if ($year !== null) {
            $parts[] = $year->start !== null && $year->end !== null && now()->between($year->start, $year->end)
                ? __(':year, day :day', ['year' => $year->name, 'day' => (int) $year->start->diffInDays(today()) + 1])
                : $year->name;
        }

        if ($user?->can('school.view')) {
            $counts = app(SchoolOverviewService::class)->counts($user);

            $parts[] = trans_choice(':count student|:count students', $counts['students'], ['count' => number_format($counts['students'])]);
            $parts[] = trans_choice(':count teacher|:count teachers', $counts['teachers'], ['count' => number_format($counts['teachers'])]);
            $parts[] = trans_choice(':count section|:count sections', $counts['sections'], ['count' => number_format($counts['sections'])]);
            $parts[] = trans_choice(':count branch|:count branches', $counts['branches'], ['count' => $counts['branches']]);
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetLayout')
                ->label(__('Reset layout'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->link()
                ->action(fn () => $this->resetDashboardLayout()),
            Action::make('newAdmission')
                ->label(__('New admission'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url(fn (): string => AdmissionResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->can('admissions.manage') ?? false),
            Action::make('markAttendance')
                ->label(__('Mark attendance'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->url(fn (): string => ClassAttendanceEntry::getUrl())
                ->visible(fn (): bool => ClassAttendanceEntry::canAccess()),
        ];
    }

    /**
     * The widgets of the board this user may see.
     *
     * @return list<string>
     */
    public function visibleDashboardWidgets(): array
    {
        $visible = [
            'attention' => NeedsAttention::canView(),
            'activity' => SchoolActivity::canView(),
            'fees' => FeeStats::canView(),
            'timetable' => auth()->user()?->can('school.view') ?? false,
            'classes' => ClassExplorer::canView(),
        ];

        return array_keys(array_filter($visible));
    }

    /**
     * Called when a widget is dropped in its group: $position counts the widgets the user sees there.
     */
    #[Renderless]
    public function sortDashboardWidget(string $widget, int $position, string $group): void
    {
        app(DashboardLayout::class)->move($this->user(), $widget, $position, $group, $this->visibleDashboardWidgets());
    }

    /**
     * Moves a widget one place back (-1) or forward (1) in its group, from the keyboard.
     */
    public function nudgeDashboardWidget(string $widget, int $offset): void
    {
        $group = DashboardLayout::WIDGETS[$widget] ?? null;

        if ($group === null) {
            return;
        }

        $layout = app(DashboardLayout::class);
        $visible = $this->visibleDashboardWidgets();
        $shown = array_values(array_intersect($layout->groups($this->user())[$group], $visible));
        $index = array_search($widget, $shown, true);

        if ($index !== false) {
            $layout->move($this->user(), $widget, $index + ($offset < 0 ? -1 : 1), $group, $visible);
        }
    }

    #[Renderless]
    public function setDashboardWidgetCollapsed(string $widget, bool $collapsed): void
    {
        app(DashboardLayout::class)->setCollapsed($this->user(), $widget, $collapsed);
    }

    /**
     * The page is loaded again, because which widgets are minimized lives in the browser until then.
     */
    public function resetDashboardLayout(): void
    {
        app(DashboardLayout::class)->reset($this->user());

        $this->redirect(static::getUrl());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $layout = app(DashboardLayout::class);
        $visible = $this->visibleDashboardWidgets();
        $groups = array_map(
            fn (array $widgets): array => array_values(array_intersect($widgets, $visible)),
            $layout->groups($this->user()),
        );

        return [
            'groups' => $groups,
            'collapsed' => $layout->collapsed($this->user()),
            'feeFilters' => $this->withActiveBranch($this->feeFilters),
            'timetableFilters' => $this->withActiveBranch($this->timetableFilters),
            'isMonthly' => ($this->timetableFilters['view'] ?? self::VIEW_WEEKLY) === self::VIEW_MONTHLY,
            'feeChips' => $this->feeFilterLabels(),
            'timetableChips' => $this->timetableFilterLabels(),
        ];
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function feeFiltersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 5])
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
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 5])
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
     * The filters of a widget, with the branch chosen in the branch switcher of the top bar.
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
     * What the fee filters are set to, shown beside the heading so that it reads even when the widget is minimized.
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
}
