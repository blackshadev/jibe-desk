<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchRepository;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Override;
use Psr\Clock\ClockInterface;

final readonly class ExportReadinessServiceImpl implements ExportReadinessService
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private SepaConfiguration $configuration,
        private ClockInterface $clock,
        private MandateReadinessService $mandateReadiness,
    ) {}

    #[Override]
    public function assess(SepaBatchId $batchId): ExportReadinessReport
    {
        $context = $this->batchRepository->getExportContext($batchId);
        $candidates = $this->batchRepository->getCollectionCandidates($batchId);

        $issues = [];

        $mailDate = CarbonImmutable::instance($context->closedAt ?? $this->clock->now())->startOfDay();
        $collectionDate = CarbonImmutable::instance($context->sepaTransferDate)->startOfDay();

        if ($collectionDate->lessThan($mailDate->addDays($this->configuration->noticePeriodDays))) {
            $issues[] = ExportReadinessIssue::forBatch(
                ExportReadinessReason::NoticePeriodTooShort,
                sprintf(
                    '%d dagen tussen sluiting en incassodatum; minimaal %d.',
                    max(0, $collectionDate->diffInDays($mailDate)),
                    $this->configuration->noticePeriodDays,
                ),
            );
        }

        /** @var list<MemberId> $memberIds */
        $memberIds = [];

        foreach ($candidates as $candidate) {
            if ($candidate->memberId === null) {
                continue;
            }

            $memberIds[] = $candidate->memberId;
        }

        $mandates = $this->mandateReadiness->forMembers($memberIds);

        foreach ($candidates as $candidate) {
            $mandate = $candidate->memberId === null ? null : $mandates[$candidate->memberId->value] ?? null;

            if ($context->status === SepaBatchStatus::Pending && $candidate->status === InvoiceStatus::Open) {
                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::UncollectableStatus, $candidate);
                continue;
            }

            if ($candidate->amountInCents() === 0) {
                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::ZeroAmount, $candidate);
                continue;
            }

            if ($candidate->isDebit()) {
                if ($mandate === null || $mandate->status === MandateReadinessStatus::Missing) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingMandate, $candidate);
                    continue;
                }

                if ($mandate->status === MandateReadinessStatus::Revoked) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::RevokedMandate, $candidate);
                    continue;
                }

                if ($mandate->status === MandateReadinessStatus::Expired) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::ExpiredMandate, $candidate);
                }

                if ($mandate->expectedSequenceType !== $candidate->sequenceType) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::SequenceTypeMismatch, $candidate);
                }
            }

            $iban = $mandate?->iban;

            if ($iban === null || trim($iban) === '') {
                // A refund without an IBAN cannot be paid out. Plan 06 owns that row; until then it is not a field
                // the gate blocks on, otherwise every manual-payer refund would dead-lock the batch.
                if ($candidate->isRefund()) {
                    continue;
                }

                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingIban, $candidate);
                continue;
            }

            if (!IbanFormat::isWellFormed($iban)) {
                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::MalformedIban, $candidate);
            }
        }

        return new ExportReadinessReport(
            batchId: $batchId,
            issues: $issues,
            noticePeriodDays: $this->configuration->noticePeriodDays,
        );
    }
}
