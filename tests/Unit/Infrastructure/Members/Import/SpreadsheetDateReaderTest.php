<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use App\Infrastructure\Members\Import\SpreadsheetDateReader;
use DateTimeImmutable;
use Override;
use Tests\UnitTestCase;

final class SpreadsheetDateReaderTest extends UnitTestCase
{
    private SpreadsheetDateReader $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SpreadsheetDateReader();
    }

    public function test_date_parses_a_date_string(): void
    {
        $date = $this->subject->date(['Geb.datum' => '2003-06-26'], 'Geb.datum', 4);

        static::assertSame('2003-06-26', $date->format('Y-m-d'));
    }

    public function test_date_accepts_a_datetime_interface_and_strips_the_time(): void
    {
        $date = $this->subject->date(
            ['Geb.datum' => new DateTimeImmutable('2003-06-26 13:45:12')],
            'Geb.datum',
            4,
        );

        static::assertSame('2003-06-26', $date->format('Y-m-d'));
        static::assertSame('00:00:00', $date->format('H:i:s'));
    }

    public function test_date_throws_when_the_value_is_missing(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 4, column "Geb.datum", value "" is required but empty.');

        $this->subject->date([], 'Geb.datum', 4);
    }

    public function test_date_throws_when_the_value_is_blank(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 4, column "Geb.datum", value "" is required but empty.');

        $this->subject->date(['Geb.datum' => '   '], 'Geb.datum', 4);
    }

    public function test_date_throws_when_the_value_is_not_a_date(): void
    {
        try {
            $this->subject->date(['Geb.datum' => 'not-a-date'], 'Geb.datum', 4);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(4, $exception->rowNumber);
            static::assertSame('Geb.datum', $exception->column);
            static::assertSame('not-a-date', $exception->value);
            static::assertSame(
                'Row 4, column "Geb.datum", value "not-a-date" is not a valid date.',
                $exception->getMessage(),
            );
        }
    }

    public function test_optional_date_returns_null_for_a_missing_or_blank_value(): void
    {
        static::assertNull($this->subject->optionalDate([], 'Stopdat. lid', 4));
        static::assertNull($this->subject->optionalDate(['Stopdat. lid' => ' '], 'Stopdat. lid', 4));
    }

    public function test_optional_date_parses_a_date_string(): void
    {
        $date = $this->subject->optionalDate(['Stopdat. lid' => '2026-12-31'], 'Stopdat. lid', 4);

        static::assertNotNull($date);
        static::assertSame('2026-12-31', $date->format('Y-m-d'));
    }

    public function test_optional_date_throws_when_the_value_is_not_a_date(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 4, column "Stopdat. lid", value "later" is not a valid date.');

        $this->subject->optionalDate(['Stopdat. lid' => 'later'], 'Stopdat. lid', 4);
    }
}
