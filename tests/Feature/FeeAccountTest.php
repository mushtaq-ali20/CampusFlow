<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Enums\VoucherItemKind;
use App\Enums\VoucherStatus;
use App\Filament\Resources\FeeAdjustments\FeeAdjustmentResource;
use App\Filament\Resources\FeeAdjustments\Pages\ListFeeAdjustments;
use App\Filament\Resources\FeeRefunds\FeeRefundResource;
use App\Filament\Resources\FeeRefunds\Pages\ListFeeRefunds;
use App\Filament\Resources\FeeVouchers\Pages\ViewFeeVoucher;
use App\Filament\Resources\FeeVouchers\RelationManagers\PaymentsRelationManager;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\FeeAdjustment;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use App\Models\FeeVoucher;
use App\Models\Section;
use App\Models\User;
use App\Models\Year;
use App\Services\FeeAccountService;
use App\Services\FeeService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class FeeAccountTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private Section $section;

    private FeeAccountService $accounts;

    private FeeService $fees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->travelTo('2026-11-11 10:00');

        $year = Year::factory()->create();
        $this->branch = Campus::factory()->create();
        $this->section = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $year->id]);
        $this->accounts = app(FeeAccountService::class);
        $this->fees = app(FeeService::class);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function voucher(int $amount = 1000, ?Admission $admission = null): FeeVoucher
    {
        $voucher = FeeVoucher::factory()->create([
            'admission_id' => ($admission ?? Admission::factory()->create(['section_id' => $this->section->id]))->id,
            'voucher_number' => 'V-'.uniqid(),
            'due_date' => '2026-11-10',
        ]);
        $voucher->items()->create(['kind' => VoucherItemKind::Fee, 'description' => 'Tuition Fee', 'amount' => $amount]);

        return $this->fees->recalculate($voucher);
    }

    private function pay(FeeVoucher $voucher, int $amount, string $on = '2026-11-11'): FeePayment
    {
        return $this->fees->recordPayment($voucher, $amount, PaymentMethod::Cash, Carbon::parse($on));
    }

    public function test_the_balance_is_split_into_installments_and_the_voucher_is_due_at_the_last_one(): void
    {
        $voucher = $this->voucher();

        $this->accounts->planInstallments($voucher, [
            ['amount' => 400, 'due_date' => '2026-11-20'],
            ['amount' => 600, 'due_date' => '2026-12-20'],
        ]);

        $voucher->refresh();
        $this->assertSame([400, 600], $voucher->installments->pluck('amount')->all());
        $this->assertSame([1, 2], $voucher->installments->pluck('sequence')->all());
        $this->assertSame('2026-12-20', $voucher->due_date->toDateString());
    }

    public function test_a_new_plan_replaces_the_old_one(): void
    {
        $voucher = $this->voucher();
        $this->accounts->planInstallments($voucher, [['amount' => 500, 'due_date' => '2026-11-20'], ['amount' => 500, 'due_date' => '2026-12-20']]);

        $this->accounts->planInstallments($voucher, [['amount' => 250, 'due_date' => '2026-11-20'], ['amount' => 250, 'due_date' => '2026-12-20'], ['amount' => 500, 'due_date' => '2027-01-20']]);

        $this->assertSame([250, 250, 500], $voucher->installments()->pluck('amount')->all());
    }

    public function test_a_plan_must_add_up_to_the_balance_and_follow_the_rules(): void
    {
        $voucher = $this->voucher();
        $this->pay($voucher, 200);
        $voucher->refresh();

        $cases = [
            'does not add up to the balance' => [[['amount' => 400, 'due_date' => '2026-11-20'], ['amount' => 600, 'due_date' => '2026-12-20']], 'must add up to the balance of Rs 800'],
            'one part only' => [[['amount' => 800, 'due_date' => '2026-11-20']], '2 to 6 installments'],
            'seven parts' => [array_map(fn (int $month): array => ['amount' => 100, 'due_date' => "2027-0{$month}-20"], range(1, 7)), '2 to 6 installments'],
            'dates not increasing' => [[['amount' => 400, 'due_date' => '2026-12-20'], ['amount' => 400, 'due_date' => '2026-12-20']], 'due after the one before'],
            'a part of zero' => [[['amount' => 0, 'due_date' => '2026-11-20'], ['amount' => 800, 'due_date' => '2026-12-20']], 'greater than zero'],
        ];

        foreach ($cases as $name => [$parts, $message]) {
            try {
                $this->accounts->planInstallments($voucher, $parts);
                $this->fail("A plan that {$name} should be refused.");
            } catch (DomainException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage(), $name);
            }
        }

        $this->assertSame(0, $voucher->installments()->count());
    }

    public function test_a_paid_voucher_has_no_plan(): void
    {
        $voucher = $this->voucher();
        $this->pay($voucher, 1000);

        $this->expectException(DomainException::class);

        $this->accounts->planInstallments($voucher->refresh(), [['amount' => 500, 'due_date' => '2026-11-20'], ['amount' => 0, 'due_date' => '2026-12-20']]);
    }

    public function test_payments_made_after_the_plan_pay_the_installments_in_order(): void
    {
        $voucher = $this->voucher();
        $this->pay($voucher, 300);
        $this->travel(1)->hours();
        $this->accounts->planInstallments($voucher->refresh(), [['amount' => 200, 'due_date' => '2026-11-20'], ['amount' => 500, 'due_date' => '2026-12-20']]);
        $this->travel(1)->hours();
        $this->pay($voucher, 350);

        $status = $this->accounts->installmentStatus($voucher->refresh());

        $this->assertSame([200, 150], $status->pluck('paid')->all());
        $this->assertSame([0, 350], $status->pluck('balance')->all());
    }

    public function test_a_reduction_becomes_a_discount_line_once_approved(): void
    {
        $admin = $this->actingAsRole('admin');
        $voucher = $this->voucher();

        $adjustment = $this->accounts->requestAdjustment($voucher, -200, 'Sibling concession', $admin);
        $this->assertSame(RequestStatus::Pending, $adjustment->status);
        $this->assertSame(1000, $voucher->refresh()->net_amount);

        $this->accounts->approveAdjustment($adjustment, $admin);

        $voucher->refresh();
        $item = $voucher->items()->where('kind', VoucherItemKind::Discount)->sole();
        $this->assertSame([1000, 200, 800], [$voucher->gross_amount, $voucher->discount_amount, $voucher->net_amount]);
        $this->assertSame('Adjustment: Sibling concession', $item->description);
        $this->assertSame(RequestStatus::Approved, $adjustment->refresh()->status);
        $this->assertSame($item->id, $adjustment->fee_voucher_item_id);
        $this->assertSame($admin->id, $adjustment->decided_by);
    }

    public function test_an_increase_becomes_a_fee_line_and_a_paid_voucher_is_open_again(): void
    {
        $voucher = $this->voucher();
        $this->pay($voucher, 1000);
        $this->assertSame(VoucherStatus::Paid, $voucher->refresh()->status);

        $adjustment = $this->accounts->requestAdjustment($voucher, 300, 'Lab charges', null);
        $this->accounts->approveAdjustment($adjustment, null);

        $voucher->refresh();
        $this->assertSame([1300, 1300, 1000], [$voucher->gross_amount, $voucher->net_amount, $voucher->paid_amount]);
        $this->assertSame(VoucherStatus::Partial, $voucher->status);
    }

    public function test_a_reduction_cannot_be_more_than_the_balance(): void
    {
        $voucher = $this->voucher();
        $this->pay($voucher, 900);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Refund a payment instead');

        $this->accounts->requestAdjustment($voucher->refresh(), -200, 'Too much', null);
    }

    public function test_an_adjustment_is_decided_once(): void
    {
        $adjustment = $this->accounts->requestAdjustment($this->voucher(), -100, 'Concession', null);
        $this->accounts->rejectAdjustment($adjustment, null);

        $this->assertSame(RequestStatus::Rejected, $adjustment->refresh()->status);

        try {
            $this->accounts->approveAdjustment($adjustment, null);
            $this->fail('A rejected adjustment cannot be approved.');
        } catch (DomainException) {
            $this->assertSame(1000, $adjustment->voucher->refresh()->net_amount);
        }

        $this->expectException(DomainException::class);
        $this->accounts->requestAdjustment($this->voucher(), 0, 'Nothing', null);
    }

    public function test_a_refund_is_limited_to_what_was_paid_and_not_yet_refunded(): void
    {
        $payment = $this->pay($this->voucher(), 500);

        $first = $this->accounts->requestRefund($payment, 300, 'Paid twice', PaymentMethod::Cash, null);
        $this->assertSame(200, $this->accounts->refundable($payment));

        try {
            $this->accounts->requestRefund($payment, 300, 'Too much', PaymentMethod::Cash, null);
            $this->fail('More than the refundable amount should be refused.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('At most Rs 200', $exception->getMessage());
        }

        $this->accounts->rejectRefund($first, null);
        $this->assertSame(500, $this->accounts->refundable($payment));

        $second = $this->accounts->requestRefund($payment, 500, 'Student left', PaymentMethod::BankTransfer, null);
        $this->accounts->approveRefund($second, null, Carbon::parse('2026-11-12'));

        $this->assertSame([RequestStatus::Approved, '2026-11-12'], [$second->refresh()->status, $second->refunded_on->toDateString()]);
        $this->assertSame(0, $this->accounts->refundable($payment));
    }

    public function test_a_refund_leaves_the_payment_and_the_voucher_as_they_were(): void
    {
        $voucher = $this->voucher();
        $payment = $this->pay($voucher, 500);
        $refund = $this->accounts->requestRefund($payment, 200, 'Paid twice', PaymentMethod::Cash, null);

        $this->accounts->approveRefund($refund, null);

        $this->assertSame(500, $payment->refresh()->amount);
        $this->assertSame(500, $voucher->refresh()->paid_amount);
    }

    public function test_the_fee_staff_plan_installments_from_the_voucher_page(): void
    {
        $this->actingAsRole('secretary');
        $voucher = $this->voucher();

        Livewire::test(ViewFeeVoucher::class, ['record' => $voucher->id])
            ->callAction('installmentPlan', data: ['parts' => [
                ['amount' => 300, 'due_date' => '2026-11-25'],
                ['amount' => 700, 'due_date' => '2026-12-25'],
            ]])
            ->assertHasNoActionErrors();

        $this->assertSame(2, $voucher->installments()->count());
        $this->assertSame('2026-12-25', $voucher->refresh()->due_date->toDateString());

        Livewire::test(ViewFeeVoucher::class, ['record' => $voucher->id])
            ->callAction('installmentPlan', data: ['parts' => [
                ['amount' => 300, 'due_date' => '2026-11-25'],
                ['amount' => 600, 'due_date' => '2026-12-25'],
            ]]);

        $this->assertSame([300, 700], $voucher->installments()->pluck('amount')->all());
    }

    public function test_secretaries_request_adjustments_and_admins_approve_them(): void
    {
        $secretary = $this->actingAsRole('secretary');
        $voucher = $this->voucher();

        Livewire::test(ViewFeeVoucher::class, ['record' => $voucher->id])
            ->callAction('requestAdjustment', data: ['amount' => -250, 'reason' => 'Staff child'])
            ->assertHasNoActionErrors();

        $adjustment = FeeAdjustment::query()->sole();
        $this->assertSame([-250, $secretary->id, RequestStatus::Pending], [$adjustment->amount, $adjustment->requested_by, $adjustment->status]);

        Livewire::test(ListFeeAdjustments::class)
            ->assertCanSeeTableRecords([$adjustment])
            ->assertActionHidden(TestAction::make('approve')->table($adjustment));

        $this->actingAsRole('admin');

        Livewire::test(ListFeeAdjustments::class)
            ->callAction(TestAction::make('approve')->table($adjustment))
            ->assertHasNoActionErrors();

        $this->assertSame(750, $voucher->refresh()->net_amount);
    }

    public function test_a_zero_adjustment_is_refused_by_the_form(): void
    {
        $this->actingAsRole('secretary');
        $voucher = $this->voucher();

        Livewire::test(ViewFeeVoucher::class, ['record' => $voucher->id])
            ->callAction('requestAdjustment', data: ['amount' => 0, 'reason' => 'Nothing'])
            ->assertHasActionErrors(['amount']);

        $this->assertDatabaseCount('fee_adjustments', 0);
    }

    public function test_secretaries_request_refunds_and_admins_approve_them(): void
    {
        $this->actingAsRole('secretary');
        $voucher = $this->voucher();
        $payment = $this->pay($voucher, 600);

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $voucher, 'pageClass' => ViewFeeVoucher::class])
            ->callAction(TestAction::make('requestRefund')->table($payment), data: ['amount' => 150, 'method' => 'cash', 'reason' => 'Paid twice'])
            ->assertHasNoActionErrors();

        $refund = FeeRefund::query()->sole();
        $this->assertSame([150, RequestStatus::Pending], [$refund->amount, $refund->status]);

        Livewire::test(ListFeeRefunds::class)
            ->assertCanSeeTableRecords([$refund])
            ->assertActionHidden(TestAction::make('approve')->table($refund));

        $this->actingAsRole('admin');

        Livewire::test(ListFeeRefunds::class)
            ->callAction(TestAction::make('approve')->table($refund), data: ['refunded_on' => '2026-11-11'])
            ->assertHasNoActionErrors();

        $this->assertSame(RequestStatus::Approved, $refund->refresh()->status);
    }

    public function test_the_refund_button_is_hidden_when_nothing_can_be_refunded(): void
    {
        $this->actingAsRole('secretary');
        $voucher = $this->voucher();
        $payment = $this->pay($voucher, 400);
        $this->accounts->requestRefund($payment, 400, 'Student left', PaymentMethod::Cash, null);

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $voucher, 'pageClass' => ViewFeeVoucher::class])
            ->assertActionHidden(TestAction::make('requestRefund')->table($payment));
    }

    public function test_branch_staff_only_see_the_requests_of_their_branch(): void
    {
        $otherBranch = Campus::factory()->create();
        $otherSection = Section::factory()->create(['campus_id' => $otherBranch->id, 'year_id' => $this->section->year_id]);
        $ours = $this->accounts->requestAdjustment($this->voucher(), -100, 'Ours', null);
        $theirs = $this->accounts->requestAdjustment($this->voucher(1000, Admission::factory()->create(['section_id' => $otherSection->id])), -100, 'Theirs', null);
        $this->actingAsRole('secretary', $this->branch);

        Livewire::test(ListFeeAdjustments::class)
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_users_without_the_fee_permission_cannot_open_the_requests(): void
    {
        $this->actingAsRole('viewer');

        $this->get(FeeAdjustmentResource::getUrl())->assertForbidden();
        $this->get(FeeRefundResource::getUrl())->assertForbidden();
    }
}
