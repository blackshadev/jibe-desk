<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use RuntimeException;

final class ExportNotReadyException extends RuntimeException
{
    public function __construct(
        public readonly ExportReadinessReport $report,
    ) {
        parent::__construct('The SEPA batch is not ready for export.');
    }
}
