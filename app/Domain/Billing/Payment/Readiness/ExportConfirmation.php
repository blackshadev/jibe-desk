<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;

final readonly class ExportConfirmation
{
    /** @param list<ExportOverride> $overrides */
    public function __construct(
        public array $overrides = [],
    ) {}

    public function overrideFor(InvoiceId $invoiceId): ?ExportOverride
    {
        foreach ($this->overrides as $override) {
            if ($override->invoiceId->value === $invoiceId->value) {
                return $override;
            }
        }

        return null;
    }
}
