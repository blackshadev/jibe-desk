<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchEmailData;
use App\Domain\Invoices\InvoiceBatchId;
use App\Mail\Invoices\InvoiceBatchCreatedMail;
use Carbon\CarbonImmutable;
use Tests\FeatureTestCase;

final class InvoiceBatchCreatedMailTest extends FeatureTestCase
{
    public function test_it_renders_correctly(): void
    {
        $mail = new InvoiceBatchCreatedMail(
            batch: new InvoiceBatchEmailData(
                id: InvoiceBatchId::create(42),
                invoiceDate: CarbonImmutable::parse('2026-06-15'),
                invoiceCount: 12,
                total: new CompoundPrice(345.50, 72.56),
            ),
        );

        $rendered = $mail->render();

        static::assertStringContainsString('Nieuwe facturatieronde aangemaakt', $rendered);
        static::assertStringContainsString('42', $rendered);
        static::assertStringContainsString('15-06-2026', $rendered);
        static::assertStringContainsString('Aantal facturen:</strong> 12', $rendered);
        static::assertStringContainsString('€ 345,50', $rendered);
        static::assertStringContainsString('Bekijk facturatieronde', $rendered);
        static::assertStringContainsString('/admin/invoice-batches/42/edit', $rendered);
    }
}
