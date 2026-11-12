<?php

namespace Tests\Feature;

use App\Enums\PaymentHabit;
use App\Enums\PaymentMethod;
use App\Enums\VoucherItemKind;
use App\Filament\Pages\FeeAging;
use App\Filament\Pages\StudentLedger;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\Contact;
use App\Models\FeeVoucher;
use App\Models\ParentMessage;
use App\Models\PhoneNumber;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use App\Services\FeeAccountService;
use App\Services\FeeLedgerService;
use App\Services\FeeService;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class FeeLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private Section $section;

    private FeeLedgerService $ledger;

    private FeeService $fees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->travelTo('2026-11-11 10:00');

        $year = Year::factory()->create();
        $this->branch = Campus::factory()->create();
        $this->section = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $year->id, 'name' => 'A']);
        $this->ledger = app(FeeLedgerService::class);
        $this->fees = app(FeeService::class);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function admission(string $firstname = 'Ali', ?Section $section = null): Admission
    {
        $student = Student::factory()->create(['id' => User::factory()->create(['firstname' => $firstname, 'lastname' => 'Khan'])->id]);

        return Admission::factory()->create(['student_id' => $student->id, 'section_id' => ($section ?? $this->section)->id]);
    }

    private function voucher(Admission $admission, string $month, string $due, int $amount = 1000): FeeVoucher
    {
        $voucher = FeeVoucher::factory()->create([
            'admission_id' => $admission->id,
            'voucher_number' => 'V-'.uniqid(),
            'month' => $month,
            'due_date' => $due,
        ]);
        $voucher->items()->create(['kind' => VoucherItemKind::Fee, 'description' => 'Tuition Fee', 'amount' => $amount]);

        return $this->fees->recalculate($voucher);
    }

    private function pay(FeeVoucher $voucher, int $amount, string $on): void
    {
        $this->fees->recordPayment($voucher->refresh(), $amount, PaymentMethod::Cash, Carbon::parse($on));
    }

    public function test_the_ledger_lists_charges_discounts_payments_and_refunds_with_a_running_balance(): void
    {
        $admission = $this->admission();
        $voucher = $this->voucher($admission, '2026-11-01', '2026-11-10');
        $voucher->items()->create(['kind' => VoucherItemKind::Discount, 'description' => 'Sibling discount', 'amount' => 100]);
        $this->fees->recalculate($voucher);
        $this->pay($voucher, 500, '2026-11-05');
        $payment = $voucher->payments()->sole();
        $accounts = app(FeeAccountService::class);
        $accounts->approveRefund($accounts->requestRefund($payment, 200, 'Paid twice', PaymentMethod::Cash, null), null, Carbon::parse('2026-11-08'));

        $entries = $this->ledger->ledger($admission->student, null);

        $this->assertSame(
            [
                ['Tuition Fee · November 2026', 1000, 0, 1000],
                ['Sibling discount · November 2026', 0, 100, 900],
                ['Payment (Cash)', 0, 500, 400],
                ['Refund: Paid twice', 200, 0, 600],
            ],
            array_map(fn (array $entry): array => [$entry['description'], $entry['debit'], $entry['credit'], $entry['balance']], $entries),
        );
        $this->assertSame(['2026-11-01', '2026-11-01', '2026-11-05', '2026-11-08'], array_map(fn (array $entry): string => $entry['date']->toDateString(), $entries));
    }

    public function test_a_refund_that_is_not_approved_is_not_in_the_ledger(): void
    {
        $admission = $this->admission();
        $voucher = $this->voucher($admission, '2026-11-01', '2026-11-10');
        $this->pay($voucher, 500, '2026-11-05');
        app(FeeAccountService::class)->requestRefund($voucher->payments()->sole(), 100, 'Maybe', PaymentMethod::Cash, null);

        $this->assertCount(2, $this->ledger->ledger($admission->student, null));
    }

    public function test_an_adjustment_approved_later_is_dated_when_it_was_approved(): void
    {
        $admission = $this->admission();
        $voucher = $this->voucher($admission, '2026-11-01', '2026-11-10');
        $accounts = app(FeeAccountService::class);
        $adjustment = $accounts->requestAdjustment($voucher, -100, 'Concession', null);
        $this->travel(10)->days();
        $accounts->approveAdjustment($adjustment, null);

        $entries = $this->ledger->ledger($admission->student, null);

        $this->assertSame('Adjustment: Concession · November 2026', $entries[1]['description']);
        $this->assertSame('2026-11-21', $entries[1]['date']->toDateString());
        $this->assertSame(900, $entries[1]['balance']);
    }

    public function test_the_ledger_covers_every_admission_of_the_student(): void
    {
        $admission = $this->admission();
        $nextYear = Admission::factory()->create(['student_id' => $admission->student_id, 'section_id' => Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => Year::factory()->create()->id])->id]);
        $this->voucher($admission, '2026-10-01', '2026-10-10', 1000);
        $this->voucher($nextYear, '2026-11-01', '2026-11-10', 1500);

        $this->assertSame(2500, array_last($this->ledger->ledger($admission->student, null))['balance']);
    }

    public function test_branch_staff_only_see_the_ledger_of_their_branch(): void
    {
        $otherBranch = Campus::factory()->create();
        $otherSection = Section::factory()->create(['campus_id' => $otherBranch->id, 'year_id' => Year::factory()->create()->id]);
        $admission = $this->admission();
        $elsewhere = Admission::factory()->create(['student_id' => $admission->student_id, 'section_id' => $otherSection->id]);
        $this->voucher($admission, '2026-10-01', '2026-10-10', 1000);
        $this->voucher($elsewhere, '2026-11-01', '2026-11-10', 700);
        $user = $this->actingAsRole('secretary', $this->branch);

        $this->assertSame(1000, array_last($this->ledger->ledger($admission->student, $user))['balance']);
        $this->assertSame(1700, array_last($this->ledger->ledger($admission->student, null))['balance']);
    }

    public function test_the_aging_puts_what_is_owed_in_buckets_by_days_past_due(): void
    {
        $recent = $this->admission('Recent');
        $older = $this->admission('Older');
        $oldest = $this->admission('Oldest');
        $notDue = $this->admission('NotDue');
        $paid = $this->admission('Paid');
        $this->voucher($recent, '2026-11-01', '2026-11-05');
        $this->voucher($older, '2026-09-01', '2026-09-26', 1500);
        $this->voucher($oldest, '2026-07-01', '2026-07-01', 2000);
        $this->voucher($notDue, '2026-11-01', '2026-11-20', 800);
        $this->pay($this->voucher($paid, '2026-10-01', '2026-10-10'), 1000, '2026-10-09');

        $rows = $this->ledger->aging(null)->keyBy(fn (array $row): string => $row['admission']->student->firstname);

        $this->assertSame(['Oldest', 'Older', 'Recent', 'Notdue'], $rows->keys()->all());
        $this->assertSame(['current' => 0, 'days_30' => 1000, 'days_60' => 0, 'days_90' => 0, 'over_90' => 0], $rows['Recent']['buckets']);
        $this->assertSame(['current' => 0, 'days_30' => 0, 'days_60' => 1500, 'days_90' => 0, 'over_90' => 0], $rows['Older']['buckets']);
        $this->assertSame(['over_90' => 2000, 'total' => 2000], ['over_90' => $rows['Oldest']['buckets']['over_90'], 'total' => $rows['Oldest']['total']]);
        $this->assertSame(800, $rows['Notdue']['buckets']['current']);
        $this->assertSame(6, $rows['Recent']['oldestDays']);
    }

    public function test_the_aging_follows_each_installment_of_a_plan(): void
    {
        $admission = $this->admission();
        $voucher = $this->voucher($admission, '2026-09-01', '2026-09-10', 900);
        app(FeeAccountService::class)->planInstallments($voucher, [['amount' => 300, 'due_date' => '2026-10-01'], ['amount' => 600, 'due_date' => '2026-12-01']]);

        $row = $this->ledger->aging(null)->sole();

        $this->assertSame(['current' => 600, 'days_30' => 0, 'days_60' => 300, 'days_90' => 0, 'over_90' => 0], $row['buckets']);
        $this->assertSame(900, $row['total']);
    }

    public function test_a_part_payment_lowers_what_is_owed(): void
    {
        $voucher = $this->voucher($this->admission(), '2026-11-01', '2026-11-05');
        $this->pay($voucher, 400, '2026-11-06');

        $this->assertSame(600, $this->ledger->aging(null)->sole()['total']);
    }

    public function test_branch_staff_only_see_the_aging_of_their_branch(): void
    {
        $otherBranch = Campus::factory()->create();
        $otherSection = Section::factory()->create(['campus_id' => $otherBranch->id, 'year_id' => $this->section->year_id]);
        $this->voucher($this->admission('Ours'), '2026-11-01', '2026-11-05');
        $this->voucher($this->admission('Theirs', $otherSection), '2026-11-01', '2026-11-05');
        $user = $this->actingAsRole('secretary', $this->branch);

        $this->assertSame(['Ours'], $this->ledger->aging($user)->map(fn (array $row): string => $row['admission']->student->firstname)->all());
    }

    public function test_families_are_told_apart_by_how_they_pay(): void
    {
        $onTime = $this->admission('Ontime');
        $sometimes = $this->admission('Sometimes');
        $often = $this->admission('Often');
        $defaulter = $this->admission('Defaulter');
        $new = $this->admission('New');
        $this->pay($this->voucher($onTime, '2026-08-01', '2026-08-10'), 1000, '2026-08-09');
        $this->pay($this->voucher($onTime, '2026-09-01', '2026-09-10'), 1000, '2026-09-10');
        foreach ([['2026-07-01', '2026-07-10', '2026-07-09'], ['2026-08-01', '2026-08-10', '2026-08-09'], ['2026-09-01', '2026-09-10', '2026-09-10'], ['2026-10-01', '2026-10-10', '2026-10-25']] as [$month, $due, $paidOn]) {
            $this->pay($this->voucher($sometimes, $month, $due), 1000, $paidOn);
        }
        foreach ([['2026-08-01', '2026-08-10', '2026-08-30'], ['2026-09-01', '2026-09-10', '2026-09-30'], ['2026-10-01', '2026-10-10', '2026-10-09']] as [$month, $due, $paidOn]) {
            $this->pay($this->voucher($often, $month, $due), 1000, $paidOn);
        }
        $this->voucher($defaulter, '2026-08-01', '2026-08-10');
        $this->voucher($new, '2026-11-01', '2026-11-20');

        $habits = $this->ledger->paymentHabits([$onTime->id, $sometimes->id, $often->id, $defaulter->id, $new->id]);

        $this->assertSame(PaymentHabit::OnTime, $habits[$onTime->id]);
        $this->assertSame(PaymentHabit::SometimesLate, $habits[$sometimes->id]);
        $this->assertSame(PaymentHabit::OftenLate, $habits[$often->id]);
        $this->assertSame(PaymentHabit::Defaulter, $habits[$defaulter->id]);
        $this->assertArrayNotHasKey($new->id, $habits);
    }

    public function test_the_reminder_tells_the_parents_what_is_owed(): void
    {
        $this->voucher($this->admission(), '2026-10-01', '2026-10-10', 6500);

        $message = $this->ledger->reminder($this->ledger->aging(null)->sole());

        $this->assertStringContainsString('Rs 6,500', $message);
        $this->assertStringContainsString('Ali KHAN', $message);
        $this->assertStringContainsString('32 days past its due date', $message);
    }

    public function test_the_aging_page_lists_the_students_and_logs_the_reminder_sent(): void
    {
        $this->actingAsRole('secretary');
        $admission = $this->admission();
        $contact = Contact::factory()->create(['student_id' => $admission->student_id]);
        PhoneNumber::query()->create(['phoneable_id' => $contact->id, 'phoneable_type' => Contact::class, 'phone_number' => '0300-1234567']);
        $this->voucher($admission, '2026-10-01', '2026-10-10', 6500);

        Livewire::test(FeeAging::class)
            ->assertSuccessful()
            ->assertSee(['Ali KHAN', 'Rs 6,500', 'Never'])
            ->callAction(TestAction::make('whatsappParent')->table($admission->id), data: ['phone' => '923001234567', 'body' => 'Fee reminder'])
            ->assertHasNoActionErrors();

        $message = ParentMessage::query()->sole();
        $this->assertTrue($message->about->is($admission));
        $this->assertSame('2026-11-11', $this->ledger->aging(null)->sole()['lastReminder']->toDateString());
    }

    public function test_the_aging_downloads_for_excel(): void
    {
        $this->actingAsRole('secretary');
        $this->voucher($this->admission(), '2026-11-01', '2026-11-05');

        $page = Livewire::test(FeeAging::class)
            ->callAction(TestAction::make('download')->table())
            ->assertFileDownloaded('fee-aging-2026-11-11.csv');

        $this->assertSame('Ali KHAN', $page->instance()->download() ? 'Ali KHAN' : '');
    }

    public function test_the_ledger_page_shows_a_student_chosen_by_the_user(): void
    {
        $this->actingAsRole('secretary');
        $admission = $this->admission();
        $this->voucher($admission, '2026-11-01', '2026-11-10');

        Livewire::test(StudentLedger::class)
            ->assertSee('Choose a student')
            ->fillForm(['student_id' => $admission->student_id])
            ->assertSee(['Tuition Fee · November 2026', 'Rs 1,000']);
    }

    public function test_viewers_cannot_open_the_fee_accounts(): void
    {
        $this->actingAsRole('viewer');

        $this->get(FeeAging::getUrl())->assertForbidden();
        $this->get(StudentLedger::getUrl())->assertForbidden();
    }
}
