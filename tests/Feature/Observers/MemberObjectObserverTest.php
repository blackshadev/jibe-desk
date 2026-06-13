<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceLineId;
use App\Domain\Members\MemberId;
use App\Models\BillableItem;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\MemberObject;
use App\Models\MemberObjectType;
use App\Observers\MemberObjectObserver;
use Carbon\CarbonImmutable;
use Override;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\Invoices\CreateInvoiceExpectation;

final class MemberObjectObserverTest extends FeatureTestCase
{
    private CreateInvoiceExpectation $invoiceRepository;

    private MemberObjectObserver $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-06-01 00:00:00');

        $this->invoiceRepository = CreateInvoiceExpectation::create();
        $this->subject = new MemberObjectObserver($this->invoiceRepository->mock);
    }

    public function test_it_applies_invoice_on_created_member_object(): void
    {
        $billable = BillableItem::createDefault();
        /** @var MemberObjectType $type */
        $type = MemberObjectType::factory()
            ->for($billable)
            ->createQuietly();

        $member = Member::factory()->createQuietly();

        /** @var MemberObject $memberObject */
        $memberObject = MemberObject::factory()
            ->for($member)
            ->for($type)
            ->createQuietly([
                'name' => 'foo',
                'start_date' => now(),
            ]);

        $invoice = Invoice::factory()
            ->for($member)
            ->createQuietly();

        $invoiceLine = InvoiceLine::factory()
            ->for($invoice)
            ->createQuietly();

        $this->invoiceRepository->expectsApplyLines(
            new ApplyInvoiceLines(
                new MemberId($member->id),
                CarbonImmutable::now(),
                new BillableItemList([
                    $billable->toInvoiceBillableItem(),
                ]),
            ),
            new AppliedInvoiceWithLineIds(
                false,
                InvoiceId::create($invoice->id),
                [InvoiceLineId::create($invoiceLine->id)],
            ),
        );

        $this->subject->created($memberObject);

        $memberObject->refresh();

        static::assertSame($invoiceLine->id, $memberObject->invoice_line_id);
    }

    public function test_it_deletes_invoice_line_on_deleted_member_object(): void
    {
        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()
            ->for($member)
            ->createQuietly();

        /** @var InvoiceLine $invoiceLine */
        $invoiceLine = InvoiceLine::factory()
            ->for($invoice)
            ->createQuietly();

        /** @var MemberObject $memberObject */
        $memberObject = MemberObject::factory()
            ->for($member)
            ->for($invoiceLine)
            ->createQuietly([
                'name' => 'bar',
                'start_date' => now()->toDateString(),
            ]);

        $this->subject->deleted($memberObject);

        $this->assertDatabaseMissing('invoice_lines', [
            'id' => $invoiceLine->id,
        ]);

        $memberObject->refresh();
        static::assertNull($memberObject->invoice_line_id);
    }
}
