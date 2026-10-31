<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\Readiness\ExportReadinessCategory;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class ExportReadinessReasonTest extends UnitTestCase
{
    public function test_every_reason_maps_to_a_category(): void
    {
        foreach (ExportReadinessReason::cases() as $reason) {
            static::assertInstanceOf(ExportReadinessCategory::class, $reason->category());
        }
    }

    /** @return iterable<string, array{ExportReadinessReason, bool}> */
    public static function overridableProvider(): iterable
    {
        yield 'missing mandate' => [ExportReadinessReason::MissingMandate, false];
        yield 'revoked mandate' => [ExportReadinessReason::RevokedMandate, false];
        yield 'expired mandate' => [ExportReadinessReason::ExpiredMandate, true];
        yield 'missing iban' => [ExportReadinessReason::MissingIban, false];
        yield 'malformed iban' => [ExportReadinessReason::MalformedIban, false];
        yield 'zero amount' => [ExportReadinessReason::ZeroAmount, false];
        yield 'uncollectable status' => [ExportReadinessReason::UncollectableStatus, false];
        yield 'sequence type mismatch' => [ExportReadinessReason::SequenceTypeMismatch, false];
        yield 'notice period too short' => [ExportReadinessReason::NoticePeriodTooShort, true];
        yield 'transfer with active mandate' => [ExportReadinessReason::TransferWithActiveMandate, true];
    }

    #[DataProvider('overridableProvider')]
    public function test_the_expected_reasons_are_overridable(ExportReadinessReason $reason, bool $expected): void
    {
        static::assertSame($expected, $reason->isOverridable());
    }
}
