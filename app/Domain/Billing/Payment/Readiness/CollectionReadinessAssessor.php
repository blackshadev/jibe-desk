<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchStatus;

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

        if ($candidate->isCredit()) {
            return $this->assessCreditCandidate($candidate, $mandate);
        }

        return $this->assessDebitCandidate($candidate, $mandate, $context);
    }

    /** @return list<ExportReadinessIssue> */
    private function assessDebitCandidate(
        SepaCollectionCandidate $candidate,
        ?MandateReadiness $mandate,
        SepaBatchExportContext $context,
    ): array {
        if ($mandate === null || $mandate->status === MandateReadinessStatus::Missing) {
            return [ExportReadinessIssue::forInvoice(
                ExportReadinessReason::MissingMandate,
                $candidate,
                'Automatische incasso verwacht, geen actieve machtiging.',
            )];
        }

        if ($mandate->status === MandateReadinessStatus::Revoked) {
            return [ExportReadinessIssue::forInvoice(
                ExportReadinessReason::RevokedMandate,
                $candidate,
                'Automatische incasso verwacht, machtiging is ingetrokken.',
            )];
        }

        return [
            ...$this->assessMandateExpiration($candidate, $mandate),
            ...$this->assessSepaCandidateSequence($candidate, $mandate, $context),
            ...$this->assessIban($candidate, $mandate),
        ];
    }

    /** @return list<ExportReadinessIssue> */
    private function assessCreditCandidate(SepaCollectionCandidate $candidate, ?MandateReadiness $mandate): array
    {
        $iban = $mandate?->iban;

        if ($iban === null || trim($iban) === '') {
            return [ExportReadinessIssue::forInvoice(
                ExportReadinessReason::RefundIbanUnavailable,
                $candidate,
                'Geen IBAN bekend voor terugbetaling.',
            )];
        }

        if (!IbanFormat::isWellFormed($iban)) {
            return [ExportReadinessIssue::forInvoice(
                ExportReadinessReason::RefundIbanUnavailable,
                $candidate,
                'IBAN is ongeldig.',
            )];
        }

        return [];
    }

    /** @return list<ExportReadinessIssue> */
    private function assessMandateExpiration(SepaCollectionCandidate $candidate, MandateReadiness $mandate): array
    {
        if ($mandate->status !== MandateReadinessStatus::Expired) {
            return [];
        }

        return [ExportReadinessIssue::forInvoice(ExportReadinessReason::ExpiredMandate, $candidate)];
    }

    /** @return list<ExportReadinessIssue> */
    private function assessSepaCandidateSequence(
        SepaCollectionCandidate $candidate,
        MandateReadiness $mandate,
        SepaBatchExportContext $context,
    ): array {
        if ($context->submittedAt !== null || $mandate->expectedSequenceType === $candidate->sequenceType) {
            return [];
        }

        return [
            ExportReadinessIssue::forInvoice(
                ExportReadinessReason::SequenceTypeMismatch,
                $candidate,
                sprintf(
                    'Gepland: %s, mandaat verwacht: %s.',
                    $candidate->sequenceType->value,
                    $mandate->expectedSequenceType->value,
                ),
            ),
        ];
    }

    /** @return list<ExportReadinessIssue> */
    private function assessIban(SepaCollectionCandidate $candidate, MandateReadiness $mandate): array
    {
        $iban = $mandate->iban;

        if ($iban === null || trim($iban) === '') {
            return [ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingIban, $candidate)];
        }

        if (IbanFormat::isWellFormed($iban)) {
            return [];
        }

        return [ExportReadinessIssue::forInvoice(ExportReadinessReason::MalformedIban, $candidate)];
    }
}
