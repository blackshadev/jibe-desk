<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\SepaBatchId;

final readonly class ExportReadinessReport
{
    private const array BLOCKING_CATEGORIES = [
        ExportReadinessCategory::Overridable,
        ExportReadinessCategory::BrokenData,
        ExportReadinessCategory::BatchLevel,
    ];

    /** @param list<ExportReadinessIssue> $issues */
    public function __construct(
        public SepaBatchId $batchId,
        public array $issues,
        public int $noticePeriodDays,
    ) {}

    /** @return list<ExportReadinessIssue> */
    public function expectedExclusions(): array
    {
        return $this->withCategory(ExportReadinessCategory::ManualPayerExpected);
    }

    /** @return list<ExportReadinessIssue> */
    public function refundExclusions(): array
    {
        return $this->withCategory(ExportReadinessCategory::RefundExclusion);
    }

    /** @return list<ExportReadinessIssue> */
    public function overridableIssues(): array
    {
        return $this->withCategory(ExportReadinessCategory::Overridable);
    }

    /** @return list<ExportReadinessIssue> */
    public function blockingIssues(): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (ExportReadinessIssue $issue): bool => in_array(
                $issue->category(),
                self::BLOCKING_CATEGORIES,
                true,
            ),
        ));
    }

    /** @return list<ExportReadinessIssue> */
    public function brokenDataIssues(): array
    {
        return $this->withCategory(ExportReadinessCategory::BrokenData);
    }

    /** @return list<ExportReadinessIssue> */
    public function batchLevelIssues(): array
    {
        return $this->withCategory(ExportReadinessCategory::BatchLevel);
    }

    public function isEmpty(): bool
    {
        return $this->issues === [];
    }

    public function isBlocked(): bool
    {
        return $this->blockingIssues() !== [];
    }

    public function requiresExclusionAcknowledgement(): bool
    {
        return $this->expectedExclusions() !== [] || $this->refundExclusions() !== [];
    }

    public function isSatisfiedBy(ExportConfirmation $confirmation): bool
    {
        if ($this->requiresExclusionAcknowledgement() && !$confirmation->exclusionsAcknowledged) {
            return false;
        }

        return $this->unresolvedIssues($confirmation) === [];
    }

    /** @return list<ExportReadinessIssue> */
    public function unresolvedIssues(ExportConfirmation $confirmation): array
    {
        return array_values(array_filter(
            $this->blockingIssues(),
            static function (ExportReadinessIssue $issue) use ($confirmation): bool {
                if (!$issue->reason->isOverridable() || $issue->invoiceId === null) {
                    return true;
                }

                $override = $confirmation->overrideFor($issue->invoiceId);

                return $override === null || $override->reason !== $issue->reason || trim($override->note) === '';
            },
        ));
    }

    /** @return list<ExportReadinessIssue> */
    private function withCategory(ExportReadinessCategory $category): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (ExportReadinessIssue $issue): bool => $issue->category() === $category,
        ));
    }
}
