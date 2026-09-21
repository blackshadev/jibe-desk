<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\Members\ExtraMembershipItemCode;
use App\Models\ExtraMembershipItem;
use App\Models\Invoice;
use Override;

final class WindsurferLifetimeScenarioTest extends MemberLifecycleScenario
{
    #[Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->travelToDate('2026-08-15');
    }

    public function test_a_windsurfer_is_billed_over_their_lifetime(): void
    {
        // Step 1 — the member registers through the public wizard (default Windsurfer membership, not a volunteer).
        $member = $this->registerMember();

        $this->assertDatabaseHas('payment_information', [
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $member->membership->adultBillableItem->id,
            'start_date' => '2026-08-15 00:00:00',
            'end_date' => null,
        ]);

        // Step 2 — admin adds them to the adult lessons activity.
        $this->joinActivity($member, $this->activityNamed('Reguliere les volwassenen'));

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $this->activityNamed('Reguliere les volwassenen')->billable_item_id,
            'end_date' => '2026-10-30 00:00:00',
        ]);

        // Step 3 — first invoice: membership + contribution are pro-rated (5/12 ≈ 0.42), activity is monthly.
        // Note: InvoiceLine.quantity is cast to decimal:2, so 5/12 (0.416667) becomes 0.42.
        $this->generateInvoiceFor($member);

        $firstInvoice = $this->invoiceFor($member, '2026-08-15');
        static::assertSame(3, $firstInvoice->lines()->count());
        $this->assertInvoiceLine($firstInvoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 0.42);
        $this->assertInvoiceLine($firstInvoice, 'Vrijwilligersbijdrage', 20.00, 0.42);
        $this->assertInvoiceLine($firstInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);

        // Step 4 — a little over a month later: only the monthly activity re-bills.
        $this->travelToDate('2026-09-20');
        $this->generateInvoiceFor($member);

        $secondInvoice = $this->invoiceFor($member, '2026-09-20');
        static::assertSame(1, $secondInvoice->lines()->count());
        $this->assertInvoiceLine($secondInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);

        // Step 5 — a week later the member becomes a volunteer (adds a restitution credit).
        $this->travelToDate('2026-09-27');
        $this->updateMember($member, ['is_volunteer' => true]);

        $restitutionBillableItemId = ExtraMembershipItem::query()
            ->where('code', ExtraMembershipItemCode::VolunteerRestitution)
            ->firstOrFail()
            ->billable_item_id;

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $restitutionBillableItemId,
            'start_date' => '2026-09-27 00:00:00',
        ]);

        // Step 6 — third invoice (next month): activity again + pro-rated restitution (4/12 ≈ 0.33).
        $this->travelToDate('2026-10-15');
        $this->generateInvoiceFor($member);

        $thirdInvoice = $this->invoiceFor($member, '2026-10-15');
        static::assertSame(2, $thirdInvoice->lines()->count());
        $this->assertInvoiceLine($thirdInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);
        $this->assertInvoiceLine($thirdInvoice, 'Vrijwilligersbijdrage restitutie', -22.00, 0.33);

        // Step 7 — fourth invoice in January: annual items re-bill at full quantity, activity has ended.
        $this->travelToDate('2027-01-10');
        $this->generateInvoiceFor($member);

        $fourthInvoice = $this->invoiceFor($member, '2027-01-10');
        static::assertSame(3, $fourthInvoice->lines()->count());
        $this->assertInvoiceLine($fourthInvoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 1.0);
        $this->assertInvoiceLine($fourthInvoice, 'Vrijwilligersbijdrage', 20.00, 1.0);
        $this->assertInvoiceLine($fourthInvoice, 'Vrijwilligersbijdrage restitutie', -22.00, 1.0);

        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $fourthInvoice->id,
            'description' => 'Activiteit: Reguliere les volwassenen',
        ]);

        static::assertSame(4, Invoice::query()->where('member_id', $member->id)->count());
    }
}
