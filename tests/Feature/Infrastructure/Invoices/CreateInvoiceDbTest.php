<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\NewInvoice;
use App\Domain\Members\MemberId;
use App\Infrastructure\Invoices\CreateInvoiceDb;
use App\Models\BillableItem as BillableItemModel;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Member;
use DateTimeImmutable;
use Tests\FeatureTestCase;

final class CreateInvoiceDbTest extends FeatureTestCase
{
    public function test_it_persists_invoice_and_lines(): void
    {
        $member = Member::factory()->createQuietly();
        Invoice::factory()->forMember($member)->create([
            'invoice_number' => 'I-2026000000',
        ]);
        $billableItemOne = BillableItemModel::factory()->create();
        $billableItemTwo = BillableItemModel::factory()->create();

        $subject = app(CreateInvoiceDb::class);

        $invoice = new NewInvoice(
            memberId: MemberId::create($member->id),
            invoiceDate: new DateTimeImmutable('2026-05-25'),
            items: new BillableItemList([
                new BillableItem(BillableItemId::create($billableItemOne->id), new CompoundPrice(10.0, 2.1), 1.0, 'First'),
                new BillableItem(BillableItemId::create($billableItemTwo->id), new CompoundPrice(20.0, 4.2), 2.0, 'Second'),
            ]),
        );

        $result = $subject->create($invoice);

        $this->assertDatabaseHas('invoices', [
            'id' => $result->value,
            'recipient_name' => $member->name,
            'recipient_address' => 'TODO',
            'invoice_number' => 'I-2026000001',
            'member_id' => $member->id,
        ]);
        $this->assertDatabaseCount('invoice_lines', 2);
    }

    public function test_it_persists_invoice_batch_id_when_provided(): void
    {
        $member = Member::factory()->createQuietly();
        Invoice::factory()->forMember($member)->create([
            'invoice_number' => 'I-2026000000',
        ]);
        $billableItem = BillableItemModel::factory()->create();
        $batch = InvoiceBatch::unguarded(fn (): InvoiceBatch => InvoiceBatch::query()->create(['invoice_date' => new DateTimeImmutable('2026-05-24')]));
        $batchId = InvoiceBatchId::create($batch->id);

        $subject = app(CreateInvoiceDb::class);

        $subject->create(new NewInvoice(
            memberId: MemberId::create($member->id),
            invoiceDate: new DateTimeImmutable('2026-05-25'),
            items: new BillableItemList([
                new BillableItem(BillableItemId::create($billableItem->id), new CompoundPrice(10.0, 2.1), 1.0, 'Only'),
            ]),
            batchId: $batchId,
        ));

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'I-2026000001',
            'invoice_batch_id' => $batchId->value,
        ]);
    }

    public function test_it_leaves_invoice_batch_id_null_when_not_provided(): void
    {
        $member = Member::factory()->createQuietly();
        Invoice::factory()->forMember($member)->create([
            'invoice_number' => 'I-2026000000',
        ]);
        $billableItem = BillableItemModel::factory()->create();

        $subject = app(CreateInvoiceDb::class);

        $subject->create(new NewInvoice(
            memberId: MemberId::create($member->id),
            invoiceDate: new DateTimeImmutable('2026-05-25'),
            items: new BillableItemList([
                new BillableItem(BillableItemId::create($billableItem->id), new CompoundPrice(10.0, 2.1), 1.0, 'Only'),
            ]),
        ));

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'I-2026000001',
            'invoice_batch_id' => null,
        ]);
    }
}
