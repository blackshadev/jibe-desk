<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\SepaExport;
use Tests\UnitTestCase;

final class SepaExportTest extends UnitTestCase
{
    public function test_it_stores_credit_transfers_and_direct_debit(): void
    {
        $subject = new SepaExport(
            creditTransfers: '<xml>credit</xml>',
            directDebit: '<xml>debit</xml>',
        );

        static::assertSame('<xml>credit</xml>', $subject->creditTransfers);
        static::assertSame('<xml>debit</xml>', $subject->directDebit);
    }
}
