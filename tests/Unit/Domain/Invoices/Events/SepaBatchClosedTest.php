<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Events;

use App\Domain\Invoices\Events\SepaBatchClosed;
use App\Domain\Invoices\SepaBatchId;
use Tests\UnitTestCase;

final class SepaBatchClosedTest extends UnitTestCase
{
    public function test_it_stores_batch_id(): void
    {
        $event = new SepaBatchClosed(SepaBatchId::create(42));

        static::assertEquals(SepaBatchId::create(42), $event->batchId);
    }
}
