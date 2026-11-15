<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ClassAttendanceEntry;
use App\Filament\Pages\FeeAging;
use App\Filament\Pages\MarksEntry;
use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Filament\Resources\FeeAdjustments\FeeAdjustmentResource;
use App\Filament\Resources\FeeRefunds\FeeRefundResource;
use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Models\Year;
use App\Services\FeeReportService;
use App\Services\FeeService;
use App\Services\SchoolOverviewService;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;

/**
 * What is waiting for the user, each with a button to deal with it: attendance not marked today, fee defaulters,
 * unpaid vouchers of the month, exam marks half entered, and the requests counted in the menu (refunds and
 * adjustments to approve, enquiries in process, complaints past their time limit). Only what they may open is listed.
 */
class NeedsAttention extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->can('school.view') ?? false) || ($user?->can('fees.view') ?? false);
    }

    /**
     * @return array{items: list<array{title: string, note: string, action: string, url: ?string, tone: string}>}
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('school.view')) {
            $overview = app(SchoolOverviewService::class);
            $unmarked = $overview->attendance($user)['unmarkedToday'];

            if ($unmarked > 0 && ClassAttendanceEntry::canAccess()) {
                $items[] = $this->item(
                    trans_choice(':count section not marked today|:count sections not marked today', $unmarked, ['count' => $unmarked]),
                    __('Attendance is still open'),
                    __('Mark'),
                    ClassAttendanceEntry::getUrl(),
                    'amber',
                );
            }

            foreach ($overview->exams($user) as $exam) {
                if ($exam['withResults'] > 0 && $exam['withResults'] < $exam['students'] && MarksEntry::canAccess()) {
                    $items[] = $this->item(
                        __(':exam marks', ['exam' => $exam['exam']->getLabel()]),
                        __('Entered for :done of :count students', ['done' => $exam['withResults'], 'count' => $exam['students']]),
                        __('Enter marks'),
                        MarksEntry::getUrl(),
                        'sky',
                    );
                }
            }
        }

        if ($user?->can('fees.view')) {
            $reports = app(FeeReportService::class);
            $filters = ['year_id' => Year::current()?->id, 'campus_id' => $user->activeBranchId()];
            $year = $reports->totals($filters, $user);
            $month = $reports->totals([...$filters, 'month' => now()->startOfMonth()->toDateString()], $user);

            if ($year['defaulters'] > 0) {
                $items[] = $this->item(
                    trans_choice(':count student with overdue fees|:count students with overdue fees', $year['defaulters'], ['count' => number_format($year['defaulters'])]),
                    __(':amount overdue', ['amount' => FeeService::money($year['overdue'])]),
                    __('View'),
                    FeeAging::canAccess() ? FeeAging::getUrl() : null,
                    'rose',
                );
            }

            if ($month['unpaid'] > 0) {
                $items[] = $this->item(
                    trans_choice(':count voucher unpaid this month|:count vouchers unpaid this month', $month['unpaid'], ['count' => number_format($month['unpaid'])]),
                    __(':amount still due', ['amount' => FeeService::money($month['dues'])]),
                    __('Open'),
                    FeeVoucherResource::canAccess() ? FeeVoucherResource::getUrl() : null,
                    'amber',
                );
            }
        }

        $requests = [
            [FeeRefundResource::class, ':count refund to approve|:count refunds to approve'],
            [FeeAdjustmentResource::class, ':count fee adjustment to approve|:count fee adjustments to approve'],
            [AdmissionEnquiryResource::class, ':count enquiry in process|:count enquiries in process'],
            [ComplaintResource::class, ':count complaint past its time limit|:count complaints past their time limit'],
        ];

        foreach ($requests as [$resource, $label]) {
            $count = $this->pending($resource);

            if ($count > 0) {
                $items[] = $this->item(trans_choice($label, $count, ['count' => $count]), $resource::getNavigationLabel(), __('Open'), $resource::getUrl(), 'gray');
            }
        }

        return ['items' => $items];
    }

    /**
     * The count shown on a screen's menu entry, when the user may open that screen.
     *
     * @param  class-string<resource>  $resource
     */
    private function pending(string $resource): int
    {
        return $resource::canAccess() ? (int) $resource::getNavigationBadge() : 0;
    }

    /**
     * @return array{title: string, note: string, action: string, url: ?string, tone: string}
     */
    private function item(string $title, string $note, string $action, ?string $url, string $tone): array
    {
        return compact('title', 'note', 'action', 'url', 'tone');
    }
}
