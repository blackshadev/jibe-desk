<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateDetails;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SepaExport;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\SepaBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Override;
use Tests\FeatureTestCase;

final class FrstRcurSequenceScenarioTest extends FeatureTestCase
{
    private const string IBAN = 'NL91ABNA0417164300';
    private const string BIC = 'ABNANL2A';

    #[Override]
    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_a_mixed_batch_exports_a_first_and_a_recurring_block(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        $batch = $this->batch('2026-11-05');
        $firstMember = Member::factory()->createQuietly();
        $recurringMember = Member::factory()->createQuietly();

        PaymentInformation::factory()->createQuietly(['member_id' => $firstMember->id]);
        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-01 00:00:00'))
            ->createQuietly(['member_id' => $recurringMember->id]);

        $firstInvoice = $this->invoiceFor($batch, $firstMember, 'INV-FRST');
        $recurringInvoice = $this->invoiceFor($batch, $recurringMember, 'INV-RCUR');

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::First, $firstInvoice->refresh()->sepa_sequence_type);
        static::assertSame(SequenceType::Recurring, $recurringInvoice->refresh()->sepa_sequence_type);

        $export = $this->export($batch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(2, $xpath->query('//pain:PmtInf')->length);
        static::assertEqualsCanonicalizing(['FRST', 'RCUR'], $this->sequenceTypes($xpath));
    }

    public function test_re_exporting_a_submitted_batch_keeps_the_first_block(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$batch, $invoice] = $this->firstCollectionScenario();

        $this->export($batch);
        $this->markSubmitted($batch);

        $invoice->refresh();

        static::assertSame(SequenceType::First, $invoice->sepa_sequence_type);
        static::assertSame(
            $batch->fresh()->submitted_at?->format('Y-m-d H:i:s'),
            $invoice->member?->currentPaymentInformation?->last_collected_at?->format('Y-m-d H:i:s'),
        );

        $export = $this->export($batch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(['FRST'], $this->sequenceTypes($xpath));
    }

    public function test_the_next_batch_for_a_collected_mandate_is_recurring(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$firstBatch, , $member] = $this->firstCollectionScenario();
        $this->export($firstBatch);
        $this->markSubmitted($firstBatch);

        $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00:00'));

        $secondBatch = $this->batch('2026-12-05');
        $secondInvoice = $this->invoiceFor($secondBatch, $member, 'INV-SECOND');
        $this->attachDecimal($secondInvoice);

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($secondBatch->id));

        static::assertSame(SequenceType::Recurring, $secondInvoice->refresh()->sepa_sequence_type);

        $export = $this->export($secondBatch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(['RCUR'], $this->sequenceTypes($xpath));
    }

    public function test_a_reauthorised_mandate_starts_with_a_first_block(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$batch, , $member] = $this->firstCollectionScenario();
        $this->export($batch);
        $this->markSubmitted($batch);

        $newMandateId = app(MandateRepository::class)->create(
            MemberId::create($member->id),
            new MandateDetails(
                iban: self::IBAN,
                bic: self::BIC,
                accountHolderName: $member->name,
                signedAt: CarbonImmutable::parse('2026-10-15'),
            ),
        );

        $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00:00'));

        $nextBatch = $this->batch('2026-12-05');
        $invoice = $this->invoiceFor($nextBatch, $member);
        $this->attachDecimal($invoice);

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($nextBatch->id));

        static::assertSame(SequenceType::First, $invoice->refresh()->sepa_sequence_type);

        $export = $this->export($nextBatch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(['FRST'], $this->sequenceTypes($xpath));
        static::assertSame(
            [new MandateId(MemberId::create($member->id), $newMandateId)->value],
            $this->mandateIds($xpath),
        );
    }

    public function test_a_declined_first_collection_makes_the_retry_recurring(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$batch, $invoice, $member] = $this->firstCollectionScenario();
        $this->export($batch);
        $this->markSubmitted($batch);

        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00'));

        app(InvoiceService::class)->markAsDeclined(
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
            null,
            'Teruggeboekt door bank',
        );

        app(InvoiceService::class)->reopen(new InvoiceIdList([InvoiceId::create($invoice->id)]));

        static::assertNull($invoice->refresh()->sepa_sequence_type);

        $retryBatch = $this->batch('2026-12-20');
        app(SepaBatchService::class)->attachInvoices(
            SepaBatchId::create($retryBatch->id),
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
        );

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($retryBatch->id));

        static::assertSame(SequenceType::Recurring, $invoice->refresh()->sepa_sequence_type);

        $export = $this->export($retryBatch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(['RCUR'], $this->sequenceTypes($xpath));
    }

    public function test_the_export_gate_does_not_block_a_legitimate_first_collection(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$batch] = $this->firstCollectionScenario();

        $report = app(ExportReadinessService::class)->assess(SepaBatchId::create($batch->id));

        static::assertSame(
            [],
            array_values(array_filter(
                $report->issues,
                static fn ($issue): bool => $issue->reason === ExportReadinessReason::SequenceTypeMismatch,
            )),
        );
        static::assertFalse($report->isBlocked());
    }

    public function test_a_stale_first_plan_blocks_the_export(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$firstBatch, , $member] = $this->firstCollectionScenario();

        $submittedBatch = $this->batch('2026-11-05');
        $submittedInvoice = $this->invoiceFor($submittedBatch, $member, 'INV-SUBMITTED');
        $this->attachDecimal($submittedInvoice);
        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($submittedBatch->id));
        $this->export($submittedBatch);
        $this->markSubmitted($submittedBatch);

        $this->expectException(ExportNotReadyException::class);

        $this->export($firstBatch);
    }

    public function test_replanning_a_stale_first_plan_exports_as_recurring(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        [$firstBatch, , $member] = $this->firstCollectionScenario();

        $submittedBatch = $this->batch('2026-11-05');
        $submittedInvoice = $this->invoiceFor($submittedBatch, $member, 'INV-SUBMITTED');
        $this->attachDecimal($submittedInvoice);
        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($submittedBatch->id));
        $this->export($submittedBatch);
        $this->markSubmitted($submittedBatch);

        app(SepaBatchService::class)->replanSequenceTypes(SepaBatchId::create($firstBatch->id));

        $export = $this->export($firstBatch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(['RCUR'], $this->sequenceTypes($xpath));
    }

    public function test_a_reauthorisation_makes_the_frozen_recurring_plan_stale(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        $batch = $this->batch('2026-11-05');
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-01 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);
        $invoice = $this->invoiceFor($batch, $member);

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::Recurring, $invoice->refresh()->sepa_sequence_type);

        $newMandateId = app(MandateRepository::class)->create(
            MemberId::create($member->id),
            new MandateDetails(
                iban: self::IBAN,
                bic: self::BIC,
                accountHolderName: $member->name,
                signedAt: CarbonImmutable::parse('2026-10-15'),
            ),
        );

        $this->expectException(ExportNotReadyException::class);

        $this->export($batch);
    }

    public function test_replanning_after_reauthorisation_exports_a_first_block_with_the_new_reference(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));

        $batch = $this->batch('2026-11-05');
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-01 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);
        $invoice = $this->invoiceFor($batch, $member);

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($batch->id));

        $newMandateId = app(MandateRepository::class)->create(
            MemberId::create($member->id),
            new MandateDetails(
                iban: self::IBAN,
                bic: self::BIC,
                accountHolderName: $member->name,
                signedAt: CarbonImmutable::parse('2026-10-15'),
            ),
        );

        app(SepaBatchService::class)->replanSequenceTypes(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::First, $invoice->refresh()->sepa_sequence_type);

        $export = $this->export($batch);
        $xpath = $this->directDebitXpath($export);

        static::assertSame(['FRST'], $this->sequenceTypes($xpath));
        static::assertSame(
            [new MandateId(MemberId::create($member->id), $newMandateId)->value],
            $this->mandateIds($xpath),
        );
    }

    /** @return array{SepaBatch, Invoice, Member} */
    private function firstCollectionScenario(): array
    {
        $batch = $this->batch('2026-11-05');
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);
        $invoice = $this->invoiceFor($batch, $member);

        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($batch->id));

        return [$batch, $invoice, $member];
    }

    private function batch(string $sepaTransferDate): SepaBatch
    {
        return SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Open,
            'invoice_date' => '2026-09-25',
            'sepa_transfer_date' => $sepaTransferDate,
            'closed_at' => null,
        ]);
    }

    private function invoiceFor(SepaBatch $batch, Member $member, ?string $invoiceNumber = null): Invoice
    {
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(array_filter([
                'status' => InvoiceStatus::Open,
                'invoice_number' => $invoiceNumber,
            ], static fn (mixed $value): bool => $value !== null));

        $this->attachDecimal($invoice);

        return $invoice;
    }

    private function attachDecimal(Invoice $invoice): void
    {
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
            'vat' => 2.10,
        ]);
    }

    private function export(SepaBatch $batch): SepaExport
    {
        return app(SepaBatchService::class)->export(
            SepaBatchId::create($batch->id),
            new ExportConfirmation(exclusionsAcknowledged: true),
            User::factory()->createQuietly()->id,
        );
    }

    private function markSubmitted(SepaBatch $batch): void
    {
        app(SepaBatchService::class)->markSubmitted(
            SepaBatchId::create($batch->id),
            User::factory()->createQuietly()->id,
        );
    }

    private function directDebitXpath(SepaExport $export): DOMXPath
    {
        $xml = new DOMDocument();
        static::assertTrue($xml->loadXML($export->directDebit));

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');

        return $xpath;
    }

    /** @return list<string> */
    private function sequenceTypes(DOMXPath $xpath): array
    {
        $sequenceTypes = [];

        foreach ($xpath->query('//pain:PmtInf/pain:PmtTpInf/pain:SeqTp') as $node) {
            $sequenceTypes[] = $node->textContent;
        }

        return $sequenceTypes;
    }

    /** @return list<string> */
    private function mandateIds(DOMXPath $xpath): array
    {
        $mandateIds = [];

        foreach ($xpath->query('//pain:DrctDbtTxInf/pain:DrctDbtTx/pain:MndtRltdInf/pain:MndtId') as $node) {
            $mandateIds[] = $node->textContent;
        }

        return $mandateIds;
    }
}
