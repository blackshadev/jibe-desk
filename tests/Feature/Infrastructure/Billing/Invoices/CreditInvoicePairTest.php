<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Infrastructure\Billing\Invoices\CreditInvoicePair;
use App\Models\Invoice;
use App\Models\SepaBatch;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

final class CreditInvoicePairTest extends FeatureTestCase
{
    public function test_may_settle_returns_true_when_the_original_is_declined(): void
    {
        $exportedBatch = SepaBatch::factory()->createQuietly(['exported_at' => '2026-10-01 12:00:00']);
        $original = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'sepa_batch_id' => $exportedBatch->id,
        ]);
        $credit = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Pending,
            'sepa_batch_id' => $exportedBatch->id,
        ]);

        $pair = new CreditInvoicePair($credit, $original);

        static::assertTrue($pair->maySettle());
    }

    public function test_may_settle_returns_true_when_neither_invoice_has_a_batch(): void
    {
        $original = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending]);
        $credit = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending]);

        $pair = new CreditInvoicePair($credit, $original);

        static::assertTrue($pair->maySettle());
    }

    public function test_may_settle_returns_true_when_the_batch_is_not_exported_or_submitted(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $original = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Pending,
            'sepa_batch_id' => $batch->id,
        ]);
        $credit = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Pending,
            'sepa_batch_id' => $batch->id,
        ]);

        $pair = new CreditInvoicePair($credit, $original);

        static::assertTrue($pair->maySettle());
    }

    /**
     * @param 'credit'|'original' $invoiceWithInFlightBatch
     * @param array<string, string> $batchAttributes
     */
    #[DataProvider('inFlightBatchProvider')]
    public function test_may_settle_returns_false_when_money_is_in_flight(
        string $invoiceWithInFlightBatch,
        array $batchAttributes,
    ): void {
        $inFlightBatch = SepaBatch::factory()->createQuietly($batchAttributes);
        $original = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Pending,
            'sepa_batch_id' => $invoiceWithInFlightBatch === 'original' ? $inFlightBatch->id : null,
        ]);
        $credit = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Pending,
            'sepa_batch_id' => $invoiceWithInFlightBatch === 'credit' ? $inFlightBatch->id : null,
        ]);

        $pair = new CreditInvoicePair($credit, $original);

        static::assertFalse($pair->maySettle());
    }

    /** @return iterable<string, array{'credit'|'original', array<string, string>}> */
    public static function inFlightBatchProvider(): iterable
    {
        yield 'credit in exported batch' => ['credit', ['exported_at' => '2026-10-01 12:00:00']];
        yield 'original in exported batch' => ['original', ['exported_at' => '2026-10-01 12:00:00']];
        yield 'credit in submitted batch' => ['credit', ['submitted_at' => '2026-10-01 12:00:00']];
        yield 'original in submitted batch' => ['original', ['submitted_at' => '2026-10-01 12:00:00']];
    }
}
