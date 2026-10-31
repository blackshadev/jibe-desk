<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use App\Infrastructure\Members\Import\SpreadsheetValueReader;
use Override;
use Tests\UnitTestCase;

final class SpreadsheetValueReaderTest extends UnitTestCase
{
    private SpreadsheetValueReader $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SpreadsheetValueReader();
    }

    public function test_text_trims_a_string_value(): void
    {
        static::assertSame('Almere', $this->subject->text(['Plaats' => '  Almere  '], 'Plaats'));
    }

    public function test_text_returns_an_empty_string_for_a_missing_column(): void
    {
        static::assertSame('', $this->subject->text([], 'Plaats'));
    }

    public function test_text_renders_an_integral_float_without_decimals(): void
    {
        static::assertSame('124277', $this->subject->text(['Lidnr' => 124_277.0], 'Lidnr'));
    }

    public function test_text_keeps_the_decimals_of_a_non_integral_float(): void
    {
        static::assertSame('12.5', $this->subject->text(['Bedrag' => 12.5], 'Bedrag'));
    }

    public function test_required_text_returns_the_value(): void
    {
        static::assertSame('Djoeke', $this->subject->requiredText(['Voornaam' => 'Djoeke'], 'Voornaam', 3));
    }

    public function test_required_text_throws_when_the_value_is_empty(): void
    {
        try {
            $this->subject->requiredText(['Voornaam' => '  '], 'Voornaam', 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('Voornaam', $exception->column);
            static::assertSame('Row 7, column "Voornaam", value "" is required but empty.', $exception->getMessage());
        }
    }

    public function test_optional_text_returns_null_for_an_empty_value(): void
    {
        static::assertNull($this->subject->optionalText(['Tussenv.' => ' '], 'Tussenv.'));
        static::assertNull($this->subject->optionalText([], 'Tussenv.'));
    }

    public function test_optional_text_returns_the_trimmed_value(): void
    {
        static::assertSame('de', $this->subject->optionalText(['Tussenv.' => ' de '], 'Tussenv.'));
    }

    public function test_is_yes_only_accepts_ja(): void
    {
        static::assertTrue($this->subject->isYes(['Deelname aan RTC' => 'Ja'], 'Deelname aan RTC'));
        static::assertFalse($this->subject->isYes(['Deelname aan RTC' => 'Nee'], 'Deelname aan RTC'));
        static::assertFalse($this->subject->isYes([], 'Deelname aan RTC'));
    }

    public function test_email_prefers_the_private_email(): void
    {
        static::assertSame(
            'private@example.com',
            $this->subject->email('private@example.com', 'contact@example.com', 3),
        );
    }

    public function test_email_falls_back_to_the_contact_email(): void
    {
        static::assertSame('contact@example.com', $this->subject->email('', 'contact@example.com', 3));
    }

    public function test_email_throws_when_both_columns_are_empty(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 3, column "E-mail privé", value "" is required but empty.');

        $this->subject->email('', null, 3);
    }

    public function test_full_name_prefers_the_full_name_column(): void
    {
        static::assertSame(
            'Djoeke Adriaanse',
            $this->subject->fullName('Djoeke Adriaanse', 'Djoeke', '', 'Adriaanse'),
        );
    }

    public function test_full_name_falls_back_to_the_separate_name_columns(): void
    {
        static::assertSame('Jan de Vries', $this->subject->fullName('', 'Jan', 'de', 'Vries'));
        static::assertSame('Jan Vries', $this->subject->fullName('', 'Jan', '', 'Vries'));
    }

    public function test_account_holder_name_falls_back_to_the_full_name(): void
    {
        static::assertSame('Djoeke Adriaanse', $this->subject->accountHolderName('', 'Djoeke Adriaanse'));
        static::assertSame('J. Adriaanse', $this->subject->accountHolderName('J. Adriaanse', 'Djoeke Adriaanse'));
    }
}
