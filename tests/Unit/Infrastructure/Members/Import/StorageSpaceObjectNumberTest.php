<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvValueException;
use App\Infrastructure\Members\Import\StorageSpaceObjectNumber;
use Tests\UnitTestCase;

final class StorageSpaceObjectNumberTest extends UnitTestCase
{
    public function test_it_maps_a_padded_number_to_a_container_and_number(): void
    {
        $subject = StorageSpaceObjectNumber::from('5-01', 2, '1001');

        static::assertSame('Container 5', $subject->locationName);
        static::assertSame('1', $subject->number);
    }

    public function test_it_maps_a_multi_digit_number(): void
    {
        $subject = StorageSpaceObjectNumber::from('3-31', 2, '1001');

        static::assertSame('Container 3', $subject->locationName);
        static::assertSame('31', $subject->number);
    }

    public function test_it_rejects_a_value_without_a_separator(): void
    {
        $this->expectException(LegacyCsvValueException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): object number "501" must look like 5-01.');

        StorageSpaceObjectNumber::from('501', 2, '1001');
    }

    public function test_it_rejects_a_value_with_a_non_numeric_part(): void
    {
        $this->expectException(LegacyCsvValueException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): object number "5-A1" must look like 5-01.');

        StorageSpaceObjectNumber::from('5-A1', 2, '1001');
    }
}
