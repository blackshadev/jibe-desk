<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use App\Infrastructure\Invoices\InvoiceRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use DateTimeImmutable;
use Override;
use Tests\FeatureTestCase;

final class InvoiceRepositoryDbMatchingTest extends FeatureTestCase
{
    private InvoiceRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(InvoiceRepositoryDb::class);
    }

    public function test_it_finds_matching_credit_with_exact_match(): void
    {
        $iban = 'NL91ABNA0417164300';
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => $iban,
        ]);

        $invoice = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Open,
            'date' => '2026-01-15',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertInstanceOf(InvoiceId::class, $result);
        static::assertEquals($invoice->id, $result->value);
    }

    public function test_it_returns_null_for_wrong_iban(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $invoice = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Open,
            'date' => '2026-01-15',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit('NL91ABNA0417164999', 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_returns_null_for_wrong_amount(): void
    {
        $iban = 'NL91ABNA0417164300';
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => $iban,
        ]);

        $invoice = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Open,
            'date' => '2026-01-15',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit($iban, 200.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_returns_null_for_date_outside_30_days(): void
    {
        $iban = 'NL91ABNA0417164300';
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => $iban,
        ]);

        $invoice = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Open,
            'date' => '2026-01-01',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit($iban, 100.00, new DateTimeImmutable('2026-03-01'));

        static::assertNull($result);
    }

    public function test_it_ignores_paid_invoices(): void
    {
        $iban = 'NL91ABNA0417164300';
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => $iban,
        ]);

        $invoice = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Paid,
            'date' => '2026-01-15',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_ignores_declined_invoices(): void
    {
        $iban = 'NL91ABNA0417164300';
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => $iban,
        ]);

        $invoice = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Declined,
            'date' => '2026-01-15',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_picks_closest_amount_when_multiple_candidates(): void
    {
        $iban = 'NL91ABNA0417164300';
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->create([
            'member_id' => $member->id,
            'banking_account_number' => $iban,
        ]);

        $invoice1 = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Open,
            'date' => '2026-01-15',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice1->id,
            'price' => 90.00,
            'quantity' => 1,
        ]);

        $invoice2 = Invoice::factory()->create([
            'member_id' => $member->id,
            'status' => InvoiceStatus::Open,
            'date' => '2026-01-16',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice2->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->findMatchingCredit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertInstanceOf(InvoiceId::class, $result);
        static::assertEquals($invoice2->id, $result->value);
    }
}
