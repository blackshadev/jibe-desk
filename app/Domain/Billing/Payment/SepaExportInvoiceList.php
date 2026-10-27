<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use Webmozart\Assert\Assert;

/**
 * The invoices of one batch, grouped into the categories a SEPA export emits.
 *
 * Invoices without an amount are never exported, so they belong to no category.
 */
final readonly class SepaExportInvoiceList
{
    /** @param list<SepaExportInvoice> $invoices */
    public function __construct(
        public array $invoices,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($invoices, SepaExportInvoice::class);
    }

    /** @return list<SepaExportInvoice> */
    public function forCategory(SepaCategory $category): array
    {
        return array_values(array_filter(
            $this->invoices,
            static fn (SepaExportInvoice $invoice): bool => $invoice->amountInCents() !== 0 && $invoice->category() === $category,
        ));
    }

    public function has(SepaCategory $category): bool
    {
        return $this->forCategory($category) !== [];
    }
}
