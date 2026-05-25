<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Infrastructure\Invoices\InvoiceDbRepository;
use App\Models\Invoice;
use Tests\FeatureTestCase;

final class InvoiceDbRepositoryTest extends FeatureTestCase
{
    public function test_get_latest_invoice_number(): void
    {
        Invoice::factory()->createManyQuietly([[
            'invoice_number' => 'I-2023000111',
        ], [
            'invoice_number' => 'I-2024000001',
        ], [
            'invoice_number' => 'I-2024000002',
        ], [
            'invoice_number' => 'I-2024000103',
        ]]);

        $repository = new InvoiceDbRepository();
        $latestInvoiceNumber = $repository->getLatestInvoiceNumber();

        self::assertSame('I-2024000103', $latestInvoiceNumber);
    }

    public function test_get_latest_invoice_number_defaults_when_no_invoices_available(): void
    {
        $repository = new InvoiceDbRepository();
        $latestInvoiceNumber = $repository->getLatestInvoiceNumber();

        self::assertSame('I-0000000000', $latestInvoiceNumber);
    }
}
