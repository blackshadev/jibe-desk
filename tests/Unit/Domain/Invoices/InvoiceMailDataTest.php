<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailLine;
use App\Domain\Mail\Recipient;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class InvoiceMailDataTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $recipient = new Recipient('Jan de Vries', 'jan@example.com');
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $sepaTransferDate = CarbonImmutable::parse('2026-06-01');
        $total = new CompoundPrice(100.0, 21.0);
        $lines = [
            new InvoiceMailLine('Membership fee', 1.0, new CompoundPrice(50.0, 10.5), new CompoundPrice(50.0, 10.5)),
        ];

        $subject = new InvoiceMailData(
            invoiceId: 42,
            invoiceNumber: 'INV-2026-001',
            recipient: $recipient,
            recipientIban: 'NL91ABNA0417164300',
            recipientAddress: 'Surfstrand 2, 1324CT Almere',
            invoiceDate: $invoiceDate,
            total: $total,
            lines: $lines,
            sepaTransferDate: $sepaTransferDate,
        );

        static::assertSame(42, $subject->invoiceId);
        static::assertSame('INV-2026-001', $subject->invoiceNumber);
        static::assertSame($recipient, $subject->recipient);
        static::assertSame('NL91ABNA0417164300', $subject->recipientIban);
        static::assertSame('Surfstrand 2, 1324CT Almere', $subject->recipientAddress);
        static::assertSame($invoiceDate, $subject->invoiceDate);
        static::assertSame($total, $subject->total);
        static::assertSame($lines, $subject->lines);
        static::assertSame($sepaTransferDate, $subject->sepaTransferDate);
    }

    public function test_it_allows_null_sepa_transfer_date(): void
    {
        $subject = new InvoiceMailData(
            invoiceId: 1,
            invoiceNumber: 'INV-001',
            recipient: new Recipient('Test', 'test@example.com'),
            recipientIban: 'NL91ABNA0417164300',
            recipientAddress: 'Test 1',
            invoiceDate: CarbonImmutable::parse('2026-01-01'),
            total: new CompoundPrice(0.0, 0.0),
            lines: [],
            sepaTransferDate: null,
        );

        static::assertNull($subject->sepaTransferDate);
    }
}
