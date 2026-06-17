<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Events;

use App\Domain\Invoices\Events\InvoiceBatchClosed;
use App\Domain\Invoices\InvoiceBatchId;
use Tests\UnitTestCase;

final class InvoiceBatchClosedTest extends UnitTestCase
{
    public function test_it_stores_batch_id(): void
    {
        $event = new InvoiceBatchClosed(InvoiceBatchId::create(42));

        static::assertEquals(InvoiceBatchId::create(42), $event->batchId);
    }
}
