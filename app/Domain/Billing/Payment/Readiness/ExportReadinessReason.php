<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

enum ExportReadinessReason: string
{
    case MissingMandate = 'missing_mandate';
    case RevokedMandate = 'revoked_mandate';
    case ExpiredMandate = 'expired_mandate';
    case MissingIban = 'missing_iban';
    case MalformedIban = 'malformed_iban';
    case ZeroAmount = 'zero_amount';
    case UncollectableStatus = 'uncollectable_status';
    case SequenceTypeMismatch = 'sequence_type_mismatch';
    case NoticePeriodTooShort = 'notice_period_too_short';

    public function category(): ExportReadinessCategory
    {
        return match ($this) {
            self::MissingMandate, self::RevokedMandate => ExportReadinessCategory::ManualPayerExpected,
            self::ExpiredMandate => ExportReadinessCategory::Overridable,
            self::NoticePeriodTooShort => ExportReadinessCategory::BatchLevel,
            self::MissingIban, self::MalformedIban, self::ZeroAmount, self::UncollectableStatus, self::SequenceTypeMismatch => ExportReadinessCategory::BrokenData,
        };
    }

    public function isOverridable(): bool
    {
        return $this === self::ExpiredMandate || $this === self::NoticePeriodTooShort;
    }
}
