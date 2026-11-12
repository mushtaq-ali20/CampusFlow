<?php

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\PayablesAging;
use App\Filament\Resources\VendorBills\Pages\ManageVendorBills;
use App\Filament\Resources\VendorBills\VendorBillResource;
use App\Filament\Resources\Vendors\Pages\ManageVendors;
use App\Models\Campus;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\PayableService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PayableTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private Vendor $vendor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branch = Campus::factory()->create();
        $this->vendor = Vendor::factory()->create(['name' => 'Paper House']);
        $this->admin = User::factory()->create(['campus_id' => null]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function service(): PayableService
    {
        return app(PayableService::class);
    }

    private function bill(int $amount = 10000, string $billDate = '2026-03-01', string $dueDate = '2026-03-31', array $attributes = []): VendorBill
    {
        return $this->service()->recordBill([
            'campus_id' => $this->branch->id,
            'vendor_id' => $this->vendor->id,
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'amount' => $amount,
            ...$attributes,
        ], $this->admin);
    }

    public function test_a_bill_is_recorded_unpaid_with_its_own_reference(): void
    {
        $bill = $this->bill(12500, attributes: ['bill_number' => 'INV-77', 'description' => 'Exam paper']);

        $this->assertSame(['BILL-000001', BillStatus::Unpaid, 12500, 12500, $this->admin->id], [$bill->reference, $bill->status, $bill->amount, $bill->balance, $bill->recorded_by]);
    }

    public function test_a_bill_needs_a_positive_amount_sensible_dates_and_a_new_number(): void
    {
        $this->bill(attributes: ['bill_number' => 'INV-77']);

        foreach ([
            fn () => $this->bill(0),
            fn () => $this->bill(100, '2026-03-10', '2026-03-09'),
            fn () => $this->bill(attributes: ['bill_number' => 'INV-77']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The bill should have been refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, VendorBill::query()->count());
    }

    public function test_a_bill_is_paid_in_parts_until_nothing_is_owed(): void
    {
        $bill = $this->bill(10000);

        $first = $this->service()->pay($bill, 4000, PaymentMethod::Cheque, Carbon::parse('2026-03-10'), $this->admin, 'CHQ-1');

        $this->assertSame(['PV-000001', 'CHQ-1', BillStatus::Partial, 6000], [$first->voucher_number, $first->reference, $bill->refresh()->status, $bill->balance]);

        $this->service()->pay($bill, 6000, PaymentMethod::Cash, Carbon::parse('2026-03-20'), $this->admin);

        $this->assertSame([BillStatus::Paid, 0, 10000], [$bill->refresh()->status, $bill->balance, $bill->paid_amount]);
    }

    public function test_a_payment_cannot_be_zero_or_more_than_what_is_owed(): void
    {
        $bill = $this->bill(10000);

        foreach ([0, 10001] as $amount) {
            try {
                $this->service()->pay($bill, $amount, PaymentMethod::Cash, now(), $this->admin);
                $this->fail("A payment of {$amount} should have been refused.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_a_bill_without_payments_is_cancelled_and_then_owes_nothing(): void
    {
        $paid = $this->bill(5000);
        $this->service()->pay($paid, 1000, PaymentMethod::Cash, now(), $this->admin);
        $mistake = $this->bill(7000);

        $this->service()->cancel($mistake);

        $this->assertSame([BillStatus::Cancelled, 0], [$mistake->status, $mistake->balance]);

        foreach ([
            fn () => $this->service()->cancel($paid),
            fn () => $this->service()->pay($mistake, 100, PaymentMethod::Cash, now(), $this->admin),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The change should have been refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_cancelled_bill_frees_its_number(): void
    {
        $this->service()->cancel($this->bill(attributes: ['bill_number' => 'INV-77']));

        $this->assertSame(BillStatus::Unpaid, $this->bill(attributes: ['bill_number' => 'INV-77'])->status);
    }

    public function test_the_aging_splits_what_is_owed_by_how_late_it_is(): void
    {
        $other = Vendor::factory()->create(['name' => 'Lesco']);
        $this->bill(1000, '2026-06-01', '2026-07-15');
        $this->bill(2000, '2026-05-01', '2026-06-20');
        $partly = $this->bill(3000, '2026-04-01', '2026-05-10');
        $this->service()->pay($partly, 500, PaymentMethod::Cash, Carbon::parse('2026-05-01'), $this->admin);
        $this->bill(4000, '2026-01-01', '2026-02-01');
        $paid = $this->bill(9000, '2026-01-01', '2026-01-15');
        $this->service()->pay($paid, 9000, PaymentMethod::Cash, Carbon::parse('2026-01-10'), $this->admin);
        $this->service()->cancel($this->bill(8000, '2026-01-01', '2026-01-15'));
        $this->service()->recordBill(['campus_id' => $this->branch->id, 'vendor_id' => $other->id, 'bill_date' => '2026-03-01', 'due_date' => '2026-04-20', 'amount' => 20000], $this->admin);

        $aging = $this->service()->aging(null, Carbon::parse('2026-06-30'));

        $this->assertSame(['Lesco', 'Paper House'], array_map(fn (array $row): string => $row['vendor']->name, $aging));
        $this->assertSame(
            ['bills' => 4, 'current' => 1000, 'days_30' => 2000, 'days_60' => 2500, 'days_90' => 0, 'over_90' => 4000, 'total' => 9500],
            collect($aging[1])->except('vendor')->all(),
        );
        $this->assertSame(['days_90' => 20000, 'total' => 20000], collect($aging[0])->only(['days_90', 'total'])->all());
    }

    public function test_the_summary_totals_what_is_owed_overdue_due_soon_and_paid(): void
    {
        $this->bill(1000, '2026-06-01', '2026-07-03');
        $this->bill(2000, '2026-06-01', '2026-07-20');
        $late = $this->bill(3000, '2026-04-01', '2026-05-10');
        $this->service()->pay($late, 500, PaymentMethod::Cash, Carbon::parse('2026-06-05'), $this->admin);
        $this->service()->pay($this->bill(700, '2026-04-01', '2026-05-10'), 700, PaymentMethod::Cash, Carbon::parse('2026-05-05'), $this->admin);

        $this->assertSame(
            ['owed' => 5500, 'overdue' => 2500, 'dueThisWeek' => 1000, 'paidThisMonth' => 500],
            $this->service()->summary(null, Carbon::parse('2026-06-30')),
        );
    }

    public function test_the_ledger_of_a_vendor_keeps_a_running_balance(): void
    {
        $first = $this->bill(10000, '2026-03-01');
        $this->service()->pay($first, 4000, PaymentMethod::Cash, Carbon::parse('2026-03-10'), $this->admin);
        $this->bill(5000, '2026-03-05');
        $this->service()->cancel($this->bill(999, '2026-03-06'));

        $ledger = $this->service()->ledger($this->vendor);

        $this->assertSame(['bill', 'bill', 'payment'], array_column($ledger, 'type'));
        $this->assertSame([10000, 15000, 11000], array_column($ledger, 'balance'));
    }

    public function test_bills_are_recorded_and_paid_from_the_screen(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'Stationery']);

        Livewire::test(ManageVendorBills::class)
            ->callAction('create', data: [
                'vendor_id' => $this->vendor->id,
                'campus_id' => $this->branch->id,
                'expense_category_id' => $category->id,
                'bill_number' => 'INV-9',
                'bill_date' => '2026-03-01',
                'due_date' => '2026-03-31',
                'amount' => 8000,
            ])
            ->assertHasNoFormErrors();

        $bill = VendorBill::query()->firstOrFail();

        Livewire::test(ManageVendorBills::class)
            ->assertCanSeeTableRecords([$bill])
            ->callAction(TestAction::make('pay')->table($bill), data: ['amount' => 3000, 'method' => PaymentMethod::Cheque->value, 'paid_on' => '2026-03-15', 'reference' => 'CHQ-5'])
            ->assertNotified(__('Payment recorded'))
            ->assertActionHidden(TestAction::make('cancel')->table($bill));

        $this->assertSame([BillStatus::Partial, 5000, $category->id], [$bill->refresh()->status, $bill->balance, $bill->expense_category_id]);
    }

    public function test_a_payment_above_the_balance_is_refused_on_the_screen(): void
    {
        $bill = $this->bill(1000);

        Livewire::test(ManageVendorBills::class)
            ->callAction(TestAction::make('pay')->table($bill), data: ['amount' => 5000, 'method' => PaymentMethod::Cash->value, 'paid_on' => '2026-03-15'])
            ->assertHasFormErrors(['amount']);

        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_a_duplicate_bill_shows_the_reason_on_the_screen(): void
    {
        $this->bill(attributes: ['bill_number' => 'INV-9']);

        Livewire::test(ManageVendorBills::class)
            ->callAction('create', data: ['vendor_id' => $this->vendor->id, 'campus_id' => $this->branch->id, 'bill_number' => 'INV-9', 'bill_date' => '2026-03-01', 'due_date' => '2026-03-31', 'amount' => 8000])
            ->assertNotified(__('The bill :number of this vendor has been recorded already.', ['number' => 'INV-9']));

        $this->assertSame(1, VendorBill::query()->count());
    }

    public function test_the_vendors_show_what_is_owed_and_their_statement_downloads(): void
    {
        $bill = $this->bill(10000);
        $this->service()->pay($bill, 2500, PaymentMethod::Cash, now(), $this->admin);

        Livewire::test(ManageVendors::class)
            ->assertSee('7,500')
            ->callAction(TestAction::make('statement')->table($this->vendor))
            ->assertFileDownloaded('vendor-statement-paper-house.csv');
    }

    public function test_the_aging_page_lists_the_vendors_and_downloads(): void
    {
        $this->bill(10000);

        Livewire::test(PayablesAging::class)
            ->assertSee(['Paper House', 'Owed'])
            ->callAction(TestAction::make('download')->table())
            ->assertFileDownloaded();
    }

    public function test_bills_follow_the_branch(): void
    {
        $mine = $this->bill();
        $theirs = VendorBill::factory()->create(['vendor_id' => $this->vendor->id, 'amount' => 4321]);
        $accountant = User::factory()->create(['campus_id' => $this->branch->id]);
        $accountant->givePermissionTo('payables.view');
        $this->actingAs($accountant);

        Livewire::test(ManageVendorBills::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('pay')->table($mine));

        $this->assertSame(10000, $this->service()->summary($accountant, Carbon::parse('2026-03-15'))['owed']);
    }

    public function test_payables_are_only_for_staff_with_the_permission(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole('secretary');
        $this->actingAs($secretary);

        $this->assertFalse(VendorBillResource::canAccess());
        $this->assertFalse(PayablesAging::canAccess());
        $this->get(VendorBillResource::getUrl())->assertForbidden();
    }
}
