<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\NewInvoice;
use App\Domain\Members\MemberId;
use App\Infrastructure\Invoices\InvoiceRepositoryDb;
use App\Models\BillableItem as BillableItemModel;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Member;
use DateTimeImmutable;
use Tests\FeatureTestCase;

final class InvoiceRepositoryDbTest extends FeatureTestCase
{
    public function test_create_persists_invoice_and_lines(): void
    {
        $member = Member::factory()->createQuietly([
            'address_street' => 'Main Street',
            'address_housenumber' => '12',
            'address_housenumber_addition' => 'B',
            'address_city' => 'Amsterdam',
            'address_postalcode' => '1234 AB',
        ]);
        Invoice::factory()->forMember($member)->createQuietly([
            'invoice_number' => 'I-2026000000',
        ]);
        $billableItemOne = BillableItemModel::factory()->createQuietly();
        $billableItemTwo = BillableItemModel::factory()->createQuietly();

        $subject = app(InvoiceRepositoryDb::class);

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
            'recipient_address' => "Main Street 12B\n1234 AB, Amsterdam",
            'invoice_number' => 'I-2026000001',
            'member_id' => $member->id,
        ]);
        $this->assertDatabaseCount('invoice_lines', 2);
    }

    public function test_create_formats_the_member_address_accessor(): void
    {
        $member = Member::factory()->createQuietly([
            'address_street' => 'Main Street',
            'address_housenumber' => '12',
            'address_housenumber_addition' => 'B',
            'address_city' => 'Amsterdam',
            'address_postalcode' => '1234 AB',
        ]);

        self::assertSame("Main Street 12B\n1234 AB, Amsterdam", $member->address);
    }

    public function test_create_persists_invoice_batch_id_when_provided(): void
    {
        $member = Member::factory()->createQuietly();
        Invoice::factory()->forMember($member)->create([
            'invoice_number' => 'I-2026000000',
        ]);
        $billableItem = BillableItemModel::factory()->createQuietly();
        /** @var InvoiceBatch $batch */
        $batch = InvoiceBatch::factory()->createQuietly();
        $batchId = InvoiceBatchId::create($batch->id);

        $subject = app(InvoiceRepositoryDb::class);

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

    public function test_create_leaves_invoice_batch_id_null_when_not_provided(): void
    {
        $member = Member::factory()->createQuietly();
        Invoice::factory()->forMember($member)->createQuietly([
            'invoice_number' => 'I-2026000000',
        ]);
        $billableItem = BillableItemModel::factory()->createQuietly();

        $subject = app(InvoiceRepositoryDb::class);

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

    public function test_apply_to_existing_month_invoice_and_returns_line_ids(): void
    {
        $member = Member::factory()->createQuietly();
        $existing = Invoice::factory()->forMember($member)->createQuietly([
            'invoice_number' => 'I-2026000000',
            'status' => InvoiceStatus::Open,
            'date' => new DateTimeImmutable('2026-05-10'),
        ]);

        $billableItemOne = BillableItemModel::factory()->createQuietly();
        $billableItemTwo = BillableItemModel::factory()->createQuietly();

        $subject = app(InvoiceRepositoryDb::class);

        $apply = new ApplyInvoiceLines(
            new DateTimeImmutable('2026-05-25'),
            MemberId::create($member->id),
            new BillableItemList([
                new BillableItem(BillableItemId::create($billableItemOne->id), new CompoundPrice(10.0, 2.1), 1.0, 'First'),
                new BillableItem(BillableItemId::create($billableItemTwo->id), new CompoundPrice(20.0, 4.2), 2.0, 'Second'),
            ])
        );

        $result = $subject->applyLines($apply);

        // should return the existing invoice id and two new line ids
        self::assertSame($existing->id, $result->invoiceId->value);
        self::assertCount(2, $result->lineIds);

        $this->assertDatabaseCount('invoice_lines', 2);
        $this->assertDatabaseHas('invoice_lines', ['description' => 'First']);
        $this->assertDatabaseHas('invoice_lines', ['description' => 'Second']);
    }

    public function test_it_creates_new_invoice_when_none_exists_for_month(): void
    {
        $member = Member::factory()->createQuietly();

        $billableItemOne = BillableItemModel::factory()->createQuietly();

        $subject = app(InvoiceRepositoryDb::class);

        $apply = new ApplyInvoiceLines(
            new DateTimeImmutable('2026-06-05'),
            MemberId::create($member->id),
            new BillableItemList([
                new BillableItem(BillableItemId::create($billableItemOne->id), new CompoundPrice(15.0, 3.0), 1.0, 'Only'),
            ])
        );

        $result = $subject->applyLines($apply);

        $this->assertDatabaseHas('invoices', ['id' => $result->invoiceId->value, 'member_id' => $member->id]);
        $this->assertDatabaseCount('invoice_lines', 1);
    }

    public function test_it_creates_new_invoice_when_current_month_invoice_is_not_open(): void
    {
        $member = Member::factory()->createQuietly();
        $existing = Invoice::factory()->forMember($member)->createQuietly([
            'invoice_number' => 'I-2026000000',
            'status' => InvoiceStatus::Pending,
            'date' => new DateTimeImmutable('2026-05-10'),
        ]);

        $billableItem = BillableItemModel::factory()->createQuietly();

        $subject = app(InvoiceRepositoryDb::class);

        $apply = new ApplyInvoiceLines(
            new DateTimeImmutable('2026-05-25'),
            MemberId::create($member->id),
            new BillableItemList([
                new BillableItem(BillableItemId::create($billableItem->id), new CompoundPrice(12.0, 2.4), 1.0, 'New'),
            ])
        );

        $result = $subject->applyLines($apply);

        self::assertNotSame($existing->id, $result->invoiceId->value);
        $this->assertDatabaseCount('invoice_lines', 1);
    }

    public function test_it_creates_new_invoice_when_previous_open_invoice_is_too_old(): void
    {
        $member = Member::factory()->createQuietly();
        $old = Invoice::factory()->forMember($member)->createQuietly([
            'invoice_number' => 'I-2026000000',
            'status' => InvoiceStatus::Open,
            'date' => new DateTimeImmutable('2026-04-10'),
        ]);

        $billableItem = BillableItemModel::factory()->create();

        $subject = app(InvoiceRepositoryDb::class);

        $apply = new ApplyInvoiceLines(
            new DateTimeImmutable('2026-05-05'),
            MemberId::create($member->id),
            new BillableItemList([
                new BillableItem(BillableItemId::create($billableItem->id), new CompoundPrice(8.0, 1.68), 1.0, 'Later'),
            ])
        );

        $result = $subject->applyLines($apply);

        // should create a new invoice because the open one is in April
        self::assertNotSame($old->id, $result->invoiceId->value);
        $this->assertDatabaseCount('invoice_lines', 1);
    }
}
