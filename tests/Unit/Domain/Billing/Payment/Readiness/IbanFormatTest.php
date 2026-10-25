<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\Readiness\IbanFormat;
use Tests\UnitTestCase;

final class IbanFormatTest extends UnitTestCase
{
    public function test_accepts_a_valid_dutch_iban(): void
    {
        static::assertTrue(IbanFormat::isWellFormed('NL91ABNA0417164300'));
    }

    public function test_accepts_an_iban_with_spaces_and_lowercase(): void
    {
        static::assertTrue(IbanFormat::isWellFormed('nl91 abna 0417 1643 00'));
    }

    public function test_rejects_an_empty_iban(): void
    {
        static::assertFalse(IbanFormat::isWellFormed(''));
        static::assertFalse(IbanFormat::isWellFormed(null));
    }

    public function test_rejects_a_too_short_iban(): void
    {
        static::assertFalse(IbanFormat::isWellFormed('NL91ABNA041716430'));
    }

    public function test_rejects_an_invalid_checksum(): void
    {
        static::assertFalse(IbanFormat::isWellFormed('NL91ABNA0417164301'));
    }

    public function test_rejects_non_alphanumeric_characters(): void
    {
        static::assertFalse(IbanFormat::isWellFormed('NL91-ABNA-0417164300'));
    }

    public function test_rejects_an_iban_with_a_valid_checksum_but_the_wrong_country_length(): void
    {
        static::assertFalse(IbanFormat::isWellFormed('NL33ABNA04171643000'));
    }
}
