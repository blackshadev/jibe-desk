<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Domain\Members\Import\MemberImportException;
use App\Infrastructure\Members\Import\SpreadsheetFieldValidator;
use Override;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\UnitTestCase;

final class SpreadsheetFieldValidatorTest extends UnitTestCase
{
    private SpreadsheetFieldValidator $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SpreadsheetFieldValidator();
    }

    #[TestWith(['Man', Gender::Male])]
    #[TestWith(['Vrouw', Gender::Female])]
    #[TestWith(['other', Gender::Other])]
    public function test_gender_maps_a_spreadsheet_value(string $value, Gender $expected): void
    {
        static::assertSame($expected, $this->subject->gender($value, 3));
    }

    public function test_gender_rejects_an_unknown_value(): void
    {
        try {
            $this->subject->gender('onbekend', 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('Geslacht', $exception->column);
            static::assertSame('onbekend', $exception->value);
        }
    }

    #[TestWith(['1359 KJ', '1359 KJ'])]
    #[TestWith(['1359kj', '1359 KJ'])]
    #[TestWith([' 1359   kj ', '1359 KJ'])]
    public function test_postal_code_normalizes_the_value(string $value, string $expected): void
    {
        static::assertSame($expected, $this->subject->postalCode($value, 3));
    }

    #[TestWith(['135 KJ'])]
    #[TestWith(['1359 K'])]
    #[TestWith(['1359 KJJ'])]
    #[TestWith(['ABCD EF'])]
    public function test_postal_code_rejects_an_invalid_value(string $value): void
    {
        try {
            $this->subject->postalCode($value, 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('Postcode', $exception->column);
            static::assertSame($value, $exception->value);
        }
    }

    public function test_iban_removes_spaces_and_uppercases_the_value(): void
    {
        static::assertSame(
            'NL75ABNA0510419224',
            $this->subject->iban('nl75 abna 0510 4192 24', 3),
        );
    }

    public function test_iban_rejects_an_invalid_value(): void
    {
        try {
            $this->subject->iban('NL00BANK0123456789', 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('Banknummer 1', $exception->column);
            static::assertSame('NL00BANK0123456789', $exception->value);
        }
    }

    public function test_bic_uppercases_the_value(): void
    {
        static::assertSame('ABNANL2A', $this->subject->bic('abnanl2a', 3));
        static::assertSame('ABNANL2AXXX', $this->subject->bic('abnanl2axxx', 3));
    }

    #[TestWith(['NOPE'])]
    #[TestWith(['ABNANL'])]
    #[TestWith(['ABNANL2AXX'])]
    public function test_bic_rejects_an_invalid_value(string $value): void
    {
        try {
            $this->subject->bic($value, 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('BIC 1', $exception->column);
            static::assertSame($value, $exception->value);
        }
    }
}
