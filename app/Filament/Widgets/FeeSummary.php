<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sections\SectionResource;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\FeeReportService;
use App\Services\FeeService;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * The fee billed, collected and due, grouped by branch, class or section as chosen in the dropdown of the
 * table. It follows the dashboard filters, shows 10 rows per page, and each column can be sorted and filtered.
 */
class FeeSummary extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public string $level = FeeReportService::LEVEL_BRANCH;

    public static function canView(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function levelOptions(): array
    {
        return [
            FeeReportService::LEVEL_BRANCH => __('Branch'),
            FeeReportService::LEVEL_CLASS => __('Class'),
            FeeReportService::LEVEL_SECTION => __('Section'),
        ];
    }

    /**
     * Another grouping has other columns: the sort, the page and the filters start again.
     */
    public function updatedLevel(): void
    {
        if (! array_key_exists($this->level, $this->levelOptions())) {
            $this->level = FeeReportService::LEVEL_BRANCH;
        }

        $this->tableSort = null;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): View => view('filament.widgets.fee-summary-heading', ['options' => $this->levelOptions()]))
            ->query(fn (): Builder => app(FeeReportService::class)->summary($this->level, $this->filtersForQuery(), auth()->user()))
            ->defaultSort('dues', 'desc')
            ->paginationMode(PaginationMode::Default)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'md' => 3])
            ->columns($this->groupColumns())
            ->filters($this->tableFiltersForLevel());
    }

    /**
     * The dashboard filters, narrowed by the branch, class and section filters of the table. Those are applied
     * before grouping, so that they work whatever the rows are grouped by.
     *
     * @return array<string, mixed>
     */
    private function filtersForQuery(): array
    {
        $filters = $this->pageFilters ?? [];

        foreach (['campus_id', 'school_class_id', 'section_id'] as $key) {
            $value = $this->tableFilters[$key]['value'] ?? null;

            if (filled($value)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @return list<TextColumn>
     */
    private function groupColumns(): array
    {
        $classOrder = fn (Builder $query, string $direction): Builder => $query->orderBy(
            SchoolClass::query()->select('order')->whereColumn('school_classes.id', 'fee_vouchers.school_class_id'),
            $direction,
        );
        $branch = TextColumn::make('campus.name')
            ->label(__('Branch'))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('campus_id', $direction));
        $class = TextColumn::make('schoolClass.name')
            ->label(__('Class'))
            ->sortable(query: $classOrder);
        $section = TextColumn::make('section.name')
            ->label(__('Section'))
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('section_id', $direction));

        $labels = match ($this->level) {
            FeeReportService::LEVEL_CLASS => [$class],
            FeeReportService::LEVEL_SECTION => [$branch, $class, $section],
            default => [$branch],
        };

        return [
            ...$labels,
            TextColumn::make('vouchers')->label(__('Vouchers'))->numeric()->alignEnd()->sortable(),
            $this->moneyColumn('billed', __('Billed')),
            $this->moneyColumn('collected', __('Collected'))->color('success'),
            $this->moneyColumn('dues', __('Dues'))->color('danger'),
            TextColumn::make('rate')->label(__('Collected %'))->formatStateUsing(fn ($state): string => ($state ?? 0).' %')->alignEnd()->sortable(),
        ];
    }

    private function moneyColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn ($state): string => FeeService::money((int) $state))
            ->alignEnd()
            ->sortable();
    }

    /**
     * Branch, class and section narrow the vouchers before they are grouped (see filtersForQuery()); the other
     * filters are ranges on the totals of each row.
     *
     * @return list<Filter|SelectFilter>
     */
    private function tableFiltersForLevel(): array
    {
        return [
            SelectFilter::make('campus_id')
                ->label(__('Branch'))
                ->options(fn (): array => SectionResource::branchOptions())
                ->query(fn (Builder $query): Builder => $query),
            SelectFilter::make('school_class_id')
                ->label(__('Class'))
                ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
                ->query(fn (Builder $query): Builder => $query),
            SelectFilter::make('section_id')
                ->label(__('Section'))
                ->options(fn (): array => Section::query()->visibleTo(auth()->user())->with(['schoolClass', 'campus'])->get()->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name])->all())
                ->searchable()
                ->query(fn (Builder $query): Builder => $query),
            $this->rangeFilter('vouchers', __('Vouchers')),
            $this->rangeFilter('billed', __('Billed')),
            $this->rangeFilter('collected', __('Collected')),
            $this->rangeFilter('dues', __('Dues')),
            $this->rangeFilter('rate', __('Collected %')),
        ];
    }

    private function rangeFilter(string $column, string $label): Filter
    {
        return Filter::make($column)
            ->label($label)
            ->schema([
                TextInput::make('from')->label($label.' — '.__('from'))->numeric(),
                TextInput::make('until')->label($label.' — '.__('to'))->numeric(),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when(filled($data['from'] ?? null), fn (Builder $query): Builder => $query->where($column, '>=', $data['from']))
                ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->where($column, '<=', $data['until'])))
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if (filled($data['from'] ?? null)) {
                    $indicators[] = $label.' ≥ '.$data['from'];
                }

                if (filled($data['until'] ?? null)) {
                    $indicators[] = $label.' ≤ '.$data['until'];
                }

                return $indicators;
            });
    }
}
