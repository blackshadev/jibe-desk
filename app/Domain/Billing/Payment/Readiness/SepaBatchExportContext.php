<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\SepaBatchStatus;
use DateTimeInterface;

final readonly class SepaBatchExportContext
{
    public function __construct(
        public SepaBatchStatus $status,
        public DateTimeInterface $sepaTransferDate,
        public ?DateTimeInterface $closedAt,
        public ?DateTimeInterface $submittedAt = null,
    ) {}
}
