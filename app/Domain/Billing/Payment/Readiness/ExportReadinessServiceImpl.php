<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchRepository;
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
        private MandateReadinessRepository $mandateReadiness,
        private CollectionReadinessAssessor $candidateAssessor,
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

        /** @var array<int, MemberId> $memberIds */
        $memberIds = [];

        foreach ($candidates as $candidate) {
            if ($candidate->memberId === null) {
                continue;
            }

            $memberIds[$candidate->memberId->value] = $candidate->memberId;
        }

        $memberIds = array_values($memberIds);

        $mandates = $this->mandateReadiness->forMembers($memberIds);

        foreach ($candidates as $candidate) {
            $mandate = $candidate->memberId === null
                ? null
                : $mandates[$candidate->memberId->value] ?? null;

            foreach ($this->candidateAssessor->assess($candidate, $mandate, $context) as $issue) {
                $issues[] = $issue;
            }
        }

        return new ExportReadinessReport(
            batchId: $batchId,
            issues: $issues,
            noticePeriodDays: $this->configuration->noticePeriodDays,
        );
    }
}
