<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use Webmozart\Assert\Assert;

final readonly class SepaExportInvoiceList
{
    /** @var array<string, list<SepaExportInvoice>> */
    private array $invoicesByCategory;

    /** @param list<SepaExportInvoice> $invoices */
    public function __construct(
        public array $invoices,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($invoices, SepaExportInvoice::class);

        $this->invoicesByCategory = $invoices
            |> (static fn (array $invoices): array => array_filter(
                $invoices,
                static fn (SepaExportInvoice $invoice): bool => $invoice->amountInCents() !== 0,
            ))
            |> array_values(...)
            |> self::groupByCategory(...);
    }

    /** @return list<SepaExportInvoice> */
    public function forCategory(SepaCategory $category): array
    {
        return $this->invoicesByCategory[$category->name] ?? [];
    }

    public function hasForCategory(SepaCategory $category): bool
    {
        return array_key_exists($category->name, $this->invoicesByCategory);
    }

    public function hasCredit(): bool
    {
        return $this->hasForCategory(SepaCategory::Credit);
    }

    public function hasDebit(): bool
    {
        return $this->hasForCategory(SepaCategory::DebitFirst)
            || $this->hasForCategory(SepaCategory::DebitRecurring);
    }

    /**
     * @param list<SepaExportInvoice> $invoices
     *
     * @return array<string, list<SepaExportInvoice>>
     */
    private static function groupByCategory(array $invoices): array
    {
        $invoicesByCategory = [];

        foreach ($invoices as $invoice) {
            $invoicesByCategory[$invoice->category()->name][] = $invoice;
        }

        return $invoicesByCategory;
    }
}
