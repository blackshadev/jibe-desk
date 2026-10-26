<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
use App\Domain\Billing\Payment\Readiness\ExportOverride;
use App\Domain\Billing\Payment\Readiness\ExportReadinessCategory;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\SepaBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Override;
use Tests\FeatureTestCase;

final class MandateLifecycleScenarioTest extends FeatureTestCase
{
    #[Override]
    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_submitting_a_batch_stamps_the_mandates_last_collected_at(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 09:30:00'));

        [$batch, $invoice, $mandate] = $this->collectionScenario();

        app(SepaBatchService::class)->markSubmitted(
            SepaBatchId::create($batch->id),
            User::factory()->createQuietly()->id,
        );

        static::assertSame(
            $batch->fresh()->submitted_at?->format('Y-m-d H:i:s'),
            $mandate->refresh()->last_collected_at?->format('Y-m-d H:i:s'),
        );
        static::assertNotNull($invoice->fresh());
    }

    public function test_a_refund_does_not_stamp_last_collected_at(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 09:30:00'));

        $batch = $this->batch();
        $member = Member::factory()->createQuietly();
        $mandate = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => -10.00,
            'quantity' => 1,
        ]);

        app(SepaBatchService::class)->markSubmitted(
            SepaBatchId::create($batch->id),
            User::factory()->createQuietly()->id,
        );

        static::assertNull($mandate->refresh()->last_collected_at);
    }

    public function test_a_collection_declined_after_submission_still_counts_as_use(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 09:30:00'));

        [$batch, $invoice, $mandate] = $this->collectionScenario();

        app(SepaBatchService::class)->markSubmitted(
            SepaBatchId::create($batch->id),
            User::factory()->createQuietly()->id,
        );

        $this->travelTo(CarbonImmutable::parse('2026-11-15 09:30:00'));

        app(InvoiceService::class)->markAsDeclined(
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
            null,
            'Teruggeboekt door bank',
        );

        static::assertNotNull($mandate->refresh()->last_collected_at);

        $this->travelTo(CarbonImmutable::parse('2029-09-22 09:30:00'));

        static::assertSame(MandateExpirationStatus::Active, $mandate->refresh()->expiration_status);
    }

    public function test_an_expired_mandate_is_reported_as_an_overridable_exclusion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        [$batch, $invoice] = $this->expiredScenario();

        $report = app(ExportReadinessService::class)->assess(SepaBatchId::create($batch->id));

        $issue = collect($report->overridableIssues())
            ->first(static fn ($issue): bool => $issue->reason === ExportReadinessReason::ExpiredMandate
                && $issue->invoiceId?->value === $invoice->id);

        static::assertNotNull($issue);
        static::assertSame(ExportReadinessCategory::Overridable, $issue->category());
        static::assertTrue($report->isBlocked());
    }

    public function test_an_expired_mandate_can_be_collected_with_a_reason(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        [$batch, $invoice] = $this->expiredScenario();

        $export = app(SepaBatchService::class)->export(
            SepaBatchId::create($batch->id),
            new ExportConfirmation(
                exclusionsAcknowledged: true,
                overrides: [
                    new ExportOverride(
                        InvoiceId::create($invoice->id),
                        ExportReadinessReason::ExpiredMandate,
                        'Afgestemd met penningmeester',
                    ),
                ],
            ),
            User::factory()->createQuietly()->id,
        );

        static::assertNotSame('', $export->directDebit);
        $this->assertDatabaseHas('sepa_export_overrides', [
            'sepa_batch_id' => $batch->id,
            'invoice_id' => $invoice->id,
            'reason' => 'expired_mandate',
            'note' => 'Afgestemd met penningmeester',
        ]);
    }

    public function test_the_expired_mandate_override_does_not_apply_to_the_next_export(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        [$batch, $invoice] = $this->expiredScenario();
        $userId = User::factory()->createQuietly()->id;

        app(SepaBatchService::class)->export(
            SepaBatchId::create($batch->id),
            new ExportConfirmation(
                exclusionsAcknowledged: true,
                overrides: [
                    new ExportOverride(
                        InvoiceId::create($invoice->id),
                        ExportReadinessReason::ExpiredMandate,
                        'Afgestemd met penningmeester',
                    ),
                ],
            ),
            $userId,
        );

        $this->expectException(ExportNotReadyException::class);

        app(SepaBatchService::class)->export(
            SepaBatchId::create($batch->id),
            new ExportConfirmation(exclusionsAcknowledged: true),
            $userId,
        );
    }

    /** @return array{SepaBatch, Invoice, PaymentInformation} */
    private function collectionScenario(): array
    {
        $batch = $this->batch();
        $member = Member::factory()->createQuietly();
        $mandate = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
        ]);
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        return [$batch, $invoice, $mandate];
    }

    /** @return array{SepaBatch, Invoice} */
    private function expiredScenario(): array
    {
        $batch = $this->batch();
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-10-22'))
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL91ABNA0417164300',
                'banking_bic' => 'ABNANL2A',
            ]);
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        return [$batch, $invoice];
    }

    private function batch(): SepaBatch
    {
        return SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'invoice_date' => '2026-09-25',
            'sepa_transfer_date' => '2026-10-20',
            'closed_at' => '2026-10-01 12:00:00',
        ]);
    }
}
