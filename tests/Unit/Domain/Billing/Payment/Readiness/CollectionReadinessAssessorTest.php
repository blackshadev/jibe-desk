<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\CollectionReadinessAssessor;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class CollectionReadinessAssessorTest extends UnitTestCase
{
    private CollectionReadinessAssessor $assessor;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->assessor = new CollectionReadinessAssessor();
    }

    /** @param list<ExportReadinessReason> $expectedReasons */
    #[DataProvider('assessmentProvider')]
    public function test_it_assesses_the_candidate(
        SepaCollectionCandidate $candidate,
        ?MandateReadiness $mandate,
        SepaBatchExportContext $context,
        array $expectedReasons,
    ): void {
        $issues = $this->assessor->assess($candidate, $mandate, $context);

        static::assertSame($expectedReasons, $this->reasons($issues));
    }

    #[DataProvider('explanationProvider')]
    public function test_it_explains_the_reported_issue(
        SepaCollectionCandidate $candidate,
        ?MandateReadiness $mandate,
        SepaBatchExportContext $context,
        string $expectedExplanation,
    ): void {
        $issues = $this->assessor->assess($candidate, $mandate, $context);

        static::assertCount(1, $issues);
        static::assertSame($expectedExplanation, $issues[0]->detail);
    }

    /** @return iterable<string, array{SepaCollectionCandidate, ?MandateReadiness, SepaBatchExportContext, list<ExportReadinessReason>}> */
    public static function assessmentProvider(): iterable
    {
        yield 'open invoice on a pending batch' => [
            self::candidate(status: InvoiceStatus::Open),
            self::mandate(),
            self::context(),
            [ExportReadinessReason::UncollectableStatus],
        ];

        yield 'open invoice on a pending batch takes precedence over a missing mandate' => [
            self::candidate(status: InvoiceStatus::Open),
            null,
            self::context(),
            [ExportReadinessReason::UncollectableStatus],
        ];

        yield 'open invoice on an open batch' => [
            self::candidate(status: InvoiceStatus::Open),
            self::mandate(),
            self::context(status: SepaBatchStatus::Open),
            [],
        ];

        yield 'zero amount' => [
            self::candidate(total: new CompoundPrice(0.0, 0.0)),
            self::mandate(),
            self::context(),
            [ExportReadinessReason::ZeroAmount],
        ];

        yield 'zero amount on an open invoice of a pending batch' => [
            self::candidate(status: InvoiceStatus::Open, total: new CompoundPrice(0.0, 0.0)),
            self::mandate(),
            self::context(),
            [ExportReadinessReason::UncollectableStatus],
        ];

        yield 'zero amount takes precedence over a missing mandate' => [
            self::candidate(total: new CompoundPrice(0.0, 0.0)),
            null,
            self::context(),
            [ExportReadinessReason::ZeroAmount],
        ];

        yield 'refund with a usable iban' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            self::mandate(),
            self::context(),
            [],
        ];

        yield 'refund without a mandate' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            null,
            self::context(),
            [ExportReadinessReason::MissingIban],
        ];

        yield 'refund with an active mandate without an iban' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            self::mandate(iban: null),
            self::context(),
            [ExportReadinessReason::MissingIban],
        ];

        yield 'refund with a blank iban' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            self::mandate(iban: '   '),
            self::context(),
            [ExportReadinessReason::MissingIban],
        ];

        yield 'refund with a malformed iban' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            self::mandate(iban: 'NOT-AN-IBAN'),
            self::context(),
            [ExportReadinessReason::MalformedIban],
        ];

        yield 'refund with a revoked mandate and a usable iban' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            self::mandate(status: MandateReadinessStatus::Revoked),
            self::context(),
            [],
        ];

        yield 'refund with a revoked mandate and a malformed iban' => [
            self::candidate(total: new CompoundPrice(-10.0, -2.1)),
            self::mandate(status: MandateReadinessStatus::Revoked, iban: 'NOT-AN-IBAN'),
            self::context(),
            [ExportReadinessReason::MalformedIban],
        ];

        yield 'debit without a mandate' => [
            self::candidate(),
            null,
            self::context(),
            [ExportReadinessReason::MissingMandate],
        ];

        yield 'debit with a missing mandate' => [
            self::candidate(),
            self::mandate(status: MandateReadinessStatus::Missing, iban: null),
            self::context(),
            [ExportReadinessReason::MissingMandate],
        ];

        yield 'debit with a revoked mandate' => [
            self::candidate(),
            self::mandate(status: MandateReadinessStatus::Revoked),
            self::context(),
            [ExportReadinessReason::RevokedMandate],
        ];

        yield 'debit with a transfer payment method and an active mandate' => [
            self::candidate(paymentMethod: PaymentMethod::Transfer),
            self::mandate(),
            self::context(),
            [ExportReadinessReason::TransferWithActiveMandate],
        ];

        yield 'debit with a transfer payment method and an expired mandate' => [
            self::candidate(paymentMethod: PaymentMethod::Transfer),
            self::mandate(status: MandateReadinessStatus::Expired),
            self::context(),
            [ExportReadinessReason::ExpiredMandate],
        ];

        yield 'debit with a direct debit payment method and an active mandate' => [
            self::candidate(paymentMethod: PaymentMethod::DirectDebit),
            self::mandate(),
            self::context(),
            [],
        ];

        yield 'debit with an expired mandate' => [
            self::candidate(),
            self::mandate(status: MandateReadinessStatus::Expired),
            self::context(),
            [ExportReadinessReason::ExpiredMandate],
        ];

        yield 'debit with a stale first collection plan' => [
            self::candidate(sequenceType: SequenceType::First),
            self::mandate(expectedSequenceType: SequenceType::Recurring),
            self::context(),
            [ExportReadinessReason::SequenceTypeMismatch],
        ];

        yield 'debit with a stale recurring collection plan' => [
            self::candidate(sequenceType: SequenceType::Recurring),
            self::mandate(expectedSequenceType: SequenceType::First),
            self::context(),
            [ExportReadinessReason::SequenceTypeMismatch],
        ];

        yield 'debit with a matching first collection' => [
            self::candidate(sequenceType: SequenceType::First),
            self::mandate(expectedSequenceType: SequenceType::First),
            self::context(),
            [],
        ];

        yield 'debit with an expired mandate and a stale collection plan' => [
            self::candidate(sequenceType: SequenceType::First),
            self::mandate(status: MandateReadinessStatus::Expired, expectedSequenceType: SequenceType::Recurring),
            self::context(),
            [ExportReadinessReason::ExpiredMandate, ExportReadinessReason::SequenceTypeMismatch],
        ];

        yield 'debit with a transfer payment method and a stale collection plan' => [
            self::candidate(sequenceType: SequenceType::First, paymentMethod: PaymentMethod::Transfer),
            self::mandate(expectedSequenceType: SequenceType::Recurring),
            self::context(),
            [ExportReadinessReason::TransferWithActiveMandate, ExportReadinessReason::SequenceTypeMismatch],
        ];

        yield 'debit without an iban' => [
            self::candidate(),
            self::mandate(iban: null),
            self::context(),
            [ExportReadinessReason::MissingIban],
        ];

        yield 'debit with a blank iban' => [
            self::candidate(),
            self::mandate(iban: '   '),
            self::context(),
            [ExportReadinessReason::MissingIban],
        ];

        yield 'debit with a malformed iban' => [
            self::candidate(),
            self::mandate(iban: 'NOT-AN-IBAN'),
            self::context(),
            [ExportReadinessReason::MalformedIban],
        ];

        yield 'debit with every applicable issue' => [
            self::candidate(sequenceType: SequenceType::First),
            self::mandate(
                status: MandateReadinessStatus::Expired,
                iban: null,
                expectedSequenceType: SequenceType::Recurring,
            ),
            self::context(),
            [
                ExportReadinessReason::ExpiredMandate,
                ExportReadinessReason::SequenceTypeMismatch,
                ExportReadinessReason::MissingIban,
            ],
        ];

        yield 'submitted batch skips the collection plan check' => [
            self::candidate(sequenceType: SequenceType::First),
            self::mandate(expectedSequenceType: SequenceType::Recurring),
            self::context(submittedAt: CarbonImmutable::parse('2026-10-23 09:30:00')),
            [],
        ];

        yield 'submitted batch still reports an expired mandate' => [
            self::candidate(),
            self::mandate(status: MandateReadinessStatus::Expired),
            self::context(submittedAt: CarbonImmutable::parse('2026-10-23 09:30:00')),
            [ExportReadinessReason::ExpiredMandate],
        ];

        yield 'submitted batch still reports a transfer payment method on an active mandate' => [
            self::candidate(paymentMethod: PaymentMethod::Transfer),
            self::mandate(),
            self::context(submittedAt: CarbonImmutable::parse('2026-10-23 09:30:00')),
            [ExportReadinessReason::TransferWithActiveMandate],
        ];
    }

    /** @return iterable<string, array{SepaCollectionCandidate, ?MandateReadiness, SepaBatchExportContext, string}> */
    public static function explanationProvider(): iterable
    {
        yield 'missing mandate' => [
            self::candidate(),
            null,
            self::context(),
            'Automatische incasso verwacht, geen actieve machtiging.',
        ];

        yield 'revoked mandate' => [
            self::candidate(),
            self::mandate(status: MandateReadinessStatus::Revoked),
            self::context(),
            'Automatische incasso verwacht, machtiging is ingetrokken.',
        ];

        yield 'first collection planned on a recurring mandate' => [
            self::candidate(sequenceType: SequenceType::First),
            self::mandate(expectedSequenceType: SequenceType::Recurring),
            self::context(),
            'Gepland: FRST, mandaat verwacht: RCUR.',
        ];

        yield 'recurring collection planned on a first mandate' => [
            self::candidate(sequenceType: SequenceType::Recurring),
            self::mandate(expectedSequenceType: SequenceType::First),
            self::context(),
            'Gepland: RCUR, mandaat verwacht: FRST.',
        ];
    }

    private static function candidate(
        InvoiceStatus $status = InvoiceStatus::Pending,
        CompoundPrice $total = new CompoundPrice(10.0, 2.1),
        SequenceType $sequenceType = SequenceType::Recurring,
        PaymentMethod $paymentMethod = PaymentMethod::DirectDebit,
    ): SepaCollectionCandidate {
        return new SepaCollectionCandidate(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: 'I-2026-000001',
            recipientName: 'Jan de Vries',
            memberId: MemberId::create(7),
            total: $total,
            status: $status,
            sequenceType: $sequenceType,
            paymentMethod: $paymentMethod,
        );
    }

    private static function mandate(
        MandateReadinessStatus $status = MandateReadinessStatus::Active,
        ?string $iban = 'NL91ABNA0417164300',
        SequenceType $expectedSequenceType = SequenceType::Recurring,
    ): MandateReadiness {
        return new MandateReadiness(
            status: $status,
            iban: $iban,
            expectedSequenceType: $expectedSequenceType,
        );
    }

    private static function context(
        SepaBatchStatus $status = SepaBatchStatus::Pending,
        ?DateTimeInterface $submittedAt = null,
    ): SepaBatchExportContext {
        return new SepaBatchExportContext(
            status: $status,
            sepaTransferDate: CarbonImmutable::parse('2026-11-05'),
            closedAt: CarbonImmutable::parse('2026-10-22'),
            submittedAt: $submittedAt,
        );
    }

    /** @param list<ExportReadinessIssue> $issues */
    private function reasons(array $issues): array
    {
        return array_map(
            static fn (ExportReadinessIssue $issue): ExportReadinessReason => $issue->reason,
            $issues,
        );
    }
}
