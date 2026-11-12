<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\PayItemKind;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\SalarySlipItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The monthly payroll of a branch. There are no tax slabs, EOBI, provident fund or gratuity: a slip is the basic
 * salary, plus the fixed allowances and minus the fixed deductions set up for the employee, minus a deduction for
 * the days of unpaid leave. Amounts are whole rupees.
 */
class PayrollService
{
    /**
     * The days a month counts for the deduction of one day of unpaid leave.
     */
    public const DAYS_PER_MONTH = 30;

    public function __construct(private LeaveRequestService $leaves) {}

    /**
     * Makes the draft payroll of a branch for a month, with a slip for every employee who worked for the school
     * that month.
     *
     * @throws DomainException
     */
    public function generate(Campus $campus, string $month, User $by): PayrollRun
    {
        $start = $this->parseMonth($month);

        if ($start->gt(now()->startOfMonth())) {
            throw new DomainException(__('The payroll of a coming month cannot be made yet.'));
        }

        if (PayrollRun::query()->where('campus_id', $campus->id)->where('month', $month)->exists()) {
            throw new DomainException(__('The payroll of :month has been made already.', ['month' => $start->translatedFormat('F Y')]));
        }

        $employees = EmployeeProfile::query()
            ->with(['user', 'payItems'])
            ->where('campus_id', $campus->id)
            ->whereDate('joined_on', '<=', $start->endOfMonth())
            ->where(fn (Builder $query) => $query
                ->where('status', EmployeeStatus::Active)
                ->orWhereDate('left_on', '>=', $start))
            ->get();

        if ($employees->isEmpty()) {
            throw new DomainException(__('There are no employees to pay in this branch for :month.', ['month' => $start->translatedFormat('F Y')]));
        }

        return DB::transaction(function () use ($campus, $month, $by, $employees, $start): PayrollRun {
            $run = PayrollRun::query()->create([
                'campus_id' => $campus->id,
                'month' => $month,
                'status' => PayrollStatus::Draft,
                'generated_by' => $by->id,
            ]);

            foreach ($employees->values() as $index => $employee) {
                $this->makeSlip($run, $employee, $start, $index + 1);
            }

            return $run;
        });
    }

    /**
     * Adds a one-off line to a slip of a draft payroll, such as a bonus or a deduction for a damaged item.
     *
     * @throws DomainException
     */
    public function addLine(SalarySlip $slip, PayItemKind $kind, string $name, int $amount): SalarySlip
    {
        $this->assertDraft($slip->run);

        if ($amount <= 0) {
            throw new DomainException(__('The amount must be more than zero.'));
        }

        $slip->items()->create(['kind' => $kind, 'name' => $name, 'amount' => $amount, 'is_manual' => true]);

        return $this->recalculate($slip);
    }

    /**
     * @throws DomainException
     */
    public function removeLine(SalarySlipItem $item): SalarySlip
    {
        $slip = $item->slip;
        $this->assertDraft($slip->run);

        if (! $item->is_manual) {
            throw new DomainException(__('This line comes from the employee file. Change it there.'));
        }

        $item->delete();

        return $this->recalculate($slip);
    }

    /**
     * Fixes the payroll. The slips can be paid and printed, but no longer changed.
     *
     * @throws DomainException
     */
    public function finalize(PayrollRun $run): PayrollRun
    {
        $this->assertDraft($run);

        $run->update(['status' => PayrollStatus::Finalized, 'finalized_at' => now()]);

        return $run->refresh();
    }

    /**
     * Throws away a draft payroll, to make it again after the employee files or the leaves changed.
     *
     * @throws DomainException
     */
    public function discard(PayrollRun $run): void
    {
        $this->assertDraft($run);

        $run->delete();
    }

    /**
     * Records that a slip was paid to the employee.
     *
     * @throws DomainException
     */
    public function markPaid(SalarySlip $slip, PaymentMethod $method, CarbonInterface $on, User $by): SalarySlip
    {
        if ($slip->run->refresh()->isDraft()) {
            throw new DomainException(__('Finalize the payroll before paying the salaries.'));
        }

        if ($slip->refresh()->isPaid()) {
            throw new DomainException(__('This salary has been paid already.'));
        }

        $slip->update(['paid_on' => $on->toDateString(), 'method' => $method, 'paid_by' => $by->id]);

        return $slip->refresh();
    }

    /**
     * Pays every slip of the payroll that is still unpaid. Returns how many were paid.
     *
     * @throws DomainException
     */
    public function markAllPaid(PayrollRun $run, PaymentMethod $method, CarbonInterface $on, User $by): int
    {
        $paid = 0;

        foreach ($run->slips()->whereNull('paid_on')->get() as $slip) {
            $this->markPaid($slip, $method, $on, $by);
            $paid++;
        }

        return $paid;
    }

    /**
     * The totals of a payroll.
     *
     * @return array{employees: int, basic: int, allowances: int, deductions: int, unpaidLeave: int, net: int, paid: int, unpaid: int}
     */
    public function summary(PayrollRun $run): array
    {
        $slips = $run->slips()->get();

        return [
            'employees' => $slips->count(),
            'basic' => (int) $slips->sum('basic_salary'),
            'allowances' => (int) $slips->sum('allowances_total'),
            'deductions' => (int) $slips->sum('deductions_total'),
            'unpaidLeave' => (int) $slips->sum('unpaid_deduction'),
            'net' => (int) $slips->sum('net_salary'),
            'paid' => $slips->filter->isPaid()->count(),
            'unpaid' => $slips->reject->isPaid()->count(),
        ];
    }

    /**
     * The payroll as rows for a spreadsheet, the first row being the headings.
     *
     * @return list<list<string|int>>
     */
    public function rows(PayrollRun $run): array
    {
        $rows = [[__('Employee No.'), __('Name'), __('Designation'), __('Basic salary'), __('Allowances'), __('Deductions'), __('Unpaid leave days'), __('Unpaid leave deduction'), __('Net salary'), __('Paid on'), __('Bank'), __('Account')]];

        foreach ($run->slips()->with('employee.user')->get()->sortBy(fn (SalarySlip $slip): string => $slip->employee->employee_code) as $slip) {
            $rows[] = [
                $slip->employee->employee_code,
                $slip->employee->user->name,
                $slip->employee->designation,
                $slip->basic_salary,
                $slip->allowances_total,
                $slip->deductions_total,
                $slip->unpaid_days,
                $slip->unpaid_deduction,
                $slip->net_salary,
                $slip->paid_on?->format('d/m/Y') ?? '',
                $slip->employee->bank_name ?? '',
                $slip->employee->bank_account ?? '',
            ];
        }

        return $rows;
    }

    /**
     * The salary slip (receipt) of one employee, or of all the employees of the payroll, as a PDF.
     */
    public function slipsPdf(PayrollRun $run, ?SalarySlip $only = null): StreamedResponse
    {
        $slips = $run->slips()
            ->with(['items', 'employee.user', 'employee.campus'])
            ->when($only !== null, fn (Builder $query) => $query->whereKey($only->id))
            ->get()
            ->sortBy(fn (SalarySlip $slip): string => $slip->employee->employee_code)
            ->values();

        $logo = config('campusflow.logo');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => storage_path('framework/cache/mpdf'),
        ]);

        $mpdf->WriteHTML(view('pdf.salary-slip', [
            'run' => $run,
            'slips' => $slips,
            'institution' => config('campusflow.company_name'),
            'logo' => $logo && Storage::disk('public')->exists($logo) ? Storage::disk('public')->path($logo) : null,
        ])->render());

        $pdf = $mpdf->Output('', Destination::STRING_RETURN);
        $name = 'salary-slip-'.$run->month.'-'.($only ? Str::slug($only->employee->user->name) : 'all').'.pdf';

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $name, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Works out the totals of a slip from its lines and its deduction for unpaid leave.
     */
    public function recalculate(SalarySlip $slip): SalarySlip
    {
        /** @var Collection<int, SalarySlipItem> $items */
        $items = $slip->items()->get();
        $allowances = (int) $items->where('kind', PayItemKind::Earning)->sum('amount');
        $deductions = (int) $items->where('kind', PayItemKind::Deduction)->sum('amount');
        $gross = $slip->basic_salary + $allowances;
        $unpaid = min($gross, (int) round($gross / self::DAYS_PER_MONTH * $slip->unpaid_days));

        $slip->update([
            'allowances_total' => $allowances,
            'deductions_total' => $deductions,
            'unpaid_deduction' => $unpaid,
            'net_salary' => max(0, $gross - $deductions - $unpaid),
        ]);

        return $slip->refresh();
    }

    private function makeSlip(PayrollRun $run, EmployeeProfile $employee, CarbonImmutable $month, int $sequence): SalarySlip
    {
        $slip = SalarySlip::query()->create([
            'payroll_run_id' => $run->id,
            'employee_profile_id' => $employee->id,
            'campus_id' => $run->campus_id,
            'slip_number' => sprintf('SL-%s-%d-%03d', $month->format('Ym'), $run->campus_id, $sequence),
            'basic_salary' => $employee->basic_salary,
            'unpaid_days' => $this->leaves->unpaidDays($employee->user, $month),
        ]);

        foreach ([PayItemKind::Earning, PayItemKind::Deduction] as $kind) {
            foreach ($employee->fixedLines($kind) as $line) {
                $slip->items()->create(['kind' => $kind, 'name' => $line['name'], 'amount' => $line['amount']]);
            }
        }

        return $this->recalculate($slip);
    }

    /**
     * @throws DomainException
     */
    private function parseMonth(string $month): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new DomainException(__('Choose a month.'));
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
    }

    /**
     * @throws DomainException
     */
    private function assertDraft(PayrollRun $run): void
    {
        if (! $run->refresh()->isDraft()) {
            throw new DomainException(__('The payroll has been finalized. It can no longer be changed.'));
        }
    }
}
