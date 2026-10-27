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

        $mailDate = CarbonImmutable::instance($context->closedAt ?? $this->clock->now())->startOfDay();
        $collectionDate = CarbonImmutable::instance($context->sepaTransferDate)->startOfDay();

        $batchIssues = $collectionDate->lessThan($mailDate->addDays($this->configuration->noticePeriodDays))
            ? [
                ExportReadinessIssue::forBatch(
                    ExportReadinessReason::NoticePeriodTooShort,
                    sprintf(
                        '%d dagen tussen sluiting en incassodatum; minimaal %d.',
                        max(0, $collectionDate->diffInDays($mailDate)),
                        $this->configuration->noticePeriodDays,
                    ),
                ),
            ]
            : [];

        $memberIds = $candidates
            |> (static fn (array $candidates): array => array_map(
                static fn (SepaCollectionCandidate $candidate): ?MemberId => $candidate->memberId,
                $candidates,
            ))
            |> (static fn (array $memberIds): array => array_filter($memberIds))
            |> (static fn (array $memberIds): array => array_values(array_combine(
                array_map(static fn (MemberId $memberId): int => $memberId->value, $memberIds),
                $memberIds,
            )));

        $mandates = $this->mandateReadiness->forMembers($memberIds);

        $candidateIssues = $candidates
            |> (fn (array $candidates): array => array_map(
                fn (SepaCollectionCandidate $candidate): array => $this->candidateAssessor->assess(
                    $candidate,
                    $candidate->memberId === null
                        ? null
                        : $mandates[$candidate->memberId->value] ?? null,
                    $context,
                ),
                $candidates,
            ))
            |> (static fn (array $issues): array => array_merge([], ...$issues));

        return new ExportReadinessReport(
            batchId: $batchId,
            issues: [...$batchIssues, ...$candidateIssues],
            noticePeriodDays: $this->configuration->noticePeriodDays,
        );
    }
}
