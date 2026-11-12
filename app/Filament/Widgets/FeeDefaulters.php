<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\FeeVoucher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\FeeReportService;
use App\Services\FeeService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The students who have not paid their fee by the due date, with what they owe. Filter by branch, class and
 * section, and sort by any column. "View vouchers" opens their fee vouchers to collect the payment.
 */
class FeeDefaulters extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Fee defaulters'))
            ->description(__('Students with fees not paid by the due date'))
            ->query(fn (): Builder => app(FeeReportService::class)->defaulters($this->pageFilters ?? [], auth()->user()))
            ->defaultSort('dues', 'desc')
            ->paginationMode(PaginationMode::Default)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'md' => 3])
            ->columns([
                TextColumn::make('admission.student.name')
                    ->label(__('Student'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('admission.student.user', fn (Builder $users) => $users
                        ->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('admission.admission_number')
                    ->label(__('Admission No.')),
                TextColumn::make('campus.name')
                    ->label(__('Branch'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('campus_id', $direction)),
                TextColumn::make('schoolClass.name')
                    ->label(__('Class'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        SchoolClass::query()->select('order')->whereColumn('school_classes.id', 'fee_vouchers.school_class_id'),
                        $direction,
                    )),
                TextColumn::make('section.name')
                    ->label(__('Section'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('section_id', $direction)),
                TextColumn::make('pending_months')
                    ->label(__('Months due'))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('dues')
                    ->label(__('Dues'))
                    ->formatStateUsing(fn ($state): string => FeeService::money((int) $state))
                    ->color('danger')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('oldest_due')
                    ->label(__('Oldest due date'))
                    ->date()
                    ->description(fn (FeeVoucher $record): string => __(':days days overdue', ['days' => (int) Carbon::parse($record->oldest_due)->diffInDays(now())]))
                    ->sortable(),
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
                    ->options(fn (): array => Section::query()->visibleTo(auth()->user())->with('schoolClass')->get()->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name])->all())
                    ->searchable(),
                $this->rangeFilter('pending_months', __('Months due')),
                $this->rangeFilter('dues', __('Dues')),
            ])
            ->recordActions([
                Action::make('viewVouchers')
                    ->label(__('View vouchers'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (FeeVoucher $record): string => FeeVoucherResource::getUrl('index', ['tableSearch' => $record->admission->student->name])),
            ]);
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
                ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->where($column, '<=', $data['until'])));
    }
}
