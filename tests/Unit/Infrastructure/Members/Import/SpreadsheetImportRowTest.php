<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Infrastructure\Members\Import\SpreadsheetImportRow;
use Tests\UnitTestCase;

final class SpreadsheetImportRowTest extends UnitTestCase
{
    public function test_it_stores_the_number_and_values(): void
    {
        $values = ['Lidnr' => '1001', 'Voornaam' => 'Anna'];

        $subject = new SpreadsheetImportRow(3, $values);

        static::assertSame(3, $subject->number);
        static::assertSame($values, $subject->values);
    }
}
