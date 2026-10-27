<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchStatus;

/**
 * Assesses one collection candidate against its mandate and the batch context.
 */
final readonly class CollectionReadinessAssessor
{
    /** @return list<ExportReadinessIssue> */
    public function assess(
        SepaCollectionCandidate $candidate,
        ?MandateReadiness $mandate,
        SepaBatchExportContext $context,
    ): array {
        if ($context->status === SepaBatchStatus::Pending && $candidate->status === InvoiceStatus::Open) {
            return [ExportReadinessIssue::forInvoice(ExportReadinessReason::UncollectableStatus, $candidate)];
        }

        if ($candidate->amountInCents() === 0) {
            return [ExportReadinessIssue::forInvoice(ExportReadinessReason::ZeroAmount, $candidate)];
        }

        if ($candidate->isDebit()) {
            return $this->debitIssues($candidate, $mandate, $context);
        }

        return $this->refundIssues($candidate, $mandate);
    }

    /** @return list<ExportReadinessIssue> */
    private function debitIssues(
        SepaCollectionCandidate $candidate,
        ?MandateReadiness $mandate,
        SepaBatchExportContext $context,
    ): array {
        if ($mandate === null || $mandate->status === MandateReadinessStatus::Missing) {
            return [ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingMandate, $candidate)];
        }

        if ($mandate->status === MandateReadinessStatus::Revoked) {
            return [ExportReadinessIssue::forInvoice(ExportReadinessReason::RevokedMandate, $candidate)];
        }

        $issues = [];

        if ($mandate->status === MandateReadinessStatus::Expired) {
            $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::ExpiredMandate, $candidate);
        }

        $sequenceIssue = $this->sequenceIssue($candidate, $mandate, $context);

        if ($sequenceIssue !== null) {
            $issues[] = $sequenceIssue;
        }

        $ibanIssue = $this->ibanIssue($candidate, $mandate);

        if ($ibanIssue !== null) {
            $issues[] = $ibanIssue;
        }

        return $issues;
    }

    /** @return list<ExportReadinessIssue> */
    private function refundIssues(SepaCollectionCandidate $candidate, ?MandateReadiness $mandate): array
    {
        $iban = $mandate?->iban;

        if ($iban === null || trim($iban) === '') {
            return [];
        }

        if (IbanFormat::isWellFormed($iban)) {
            return [];
        }

        return [ExportReadinessIssue::forInvoice(ExportReadinessReason::MalformedIban, $candidate)];
    }

    private function sequenceIssue(
        SepaCollectionCandidate $candidate,
        MandateReadiness $mandate,
        SepaBatchExportContext $context,
    ): ?ExportReadinessIssue {
        if ($context->submittedAt !== null || $mandate->expectedSequenceType === $candidate->sequenceType) {
            return null;
        }

        return ExportReadinessIssue::forInvoice(
            ExportReadinessReason::SequenceTypeMismatch,
            $candidate,
            sprintf(
                'Gepland: %s, mandaat verwacht: %s.',
                $candidate->sequenceType->value,
                $mandate->expectedSequenceType->value,
            ),
        );
    }

    private function ibanIssue(SepaCollectionCandidate $candidate, MandateReadiness $mandate): ?ExportReadinessIssue
    {
        $iban = $mandate->iban;

        if ($iban === null || trim($iban) === '') {
            return ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingIban, $candidate);
        }

        if (IbanFormat::isWellFormed($iban)) {
            return null;
        }

        return ExportReadinessIssue::forInvoice(ExportReadinessReason::MalformedIban, $candidate);
    }
}
