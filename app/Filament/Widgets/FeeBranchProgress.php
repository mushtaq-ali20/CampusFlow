<?php

namespace App\Filament\Widgets;

use App\Models\FeeVoucher;
use App\Services\FeeReportService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * How much of the billed fee each branch has collected, as bars, for the filters of the Fees group.
 */
class FeeBranchProgress extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.fee-branch-progress';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    /**
     * @return array{branches: Collection<int, FeeVoucher>}
     */
    protected function getViewData(): array
    {
        return [
            'branches' => app(FeeReportService::class)
                ->summary(FeeReportService::LEVEL_BRANCH, $this->pageFilters ?? [], auth()->user())
                ->with('campus')
                ->orderBy('campus_id')
                ->get(),
        ];
    }
}
