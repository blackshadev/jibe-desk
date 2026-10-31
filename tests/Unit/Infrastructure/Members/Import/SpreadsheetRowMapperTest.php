<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Domain\Members\Import\MemberImportException;
use App\Infrastructure\Members\Import\SpreadsheetRowMapper;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\UnitTestCase;

final class SpreadsheetRowMapperTest extends UnitTestCase
{
    private SpreadsheetRowMapper $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SpreadsheetRowMapper();
    }

    public function test_it_maps_all_fields_from_a_spreadsheet_row(): void
    {
        $row = $this->row();

        $mapped = $this->subject->map($row, 3);

        static::assertSame('124277', $mapped->lidnr);
        static::assertSame('Djoeke', $mapped->firstName);
        static::assertSame('', $mapped->infixName);
        static::assertSame('Adriaanse', $mapped->lastName);
        static::assertSame('Djoeke Adriaanse', $mapped->fullName);
        static::assertSame('djoeke@example.com', $mapped->email);
        static::assertSame('djoeke@example.com', $mapped->contactEmail);
        static::assertSame('2009-02-20', $mapped->birthdate->format('Y-m-d'));
        static::assertSame(Gender::Female, $mapped->gender);
        static::assertSame('Krachtveld', $mapped->addressStreet);
        static::assertSame('30', $mapped->addressHousenumber);
        static::assertNull($mapped->addressHousenumberAddition);
        static::assertSame('1359KJ', $mapped->addressPostalcode);
        static::assertSame('Almere', $mapped->addressCity);
        static::assertSame('Volwassenlid', $mapped->membershipType);
        static::assertSame('Windsurfer', $mapped->membershipName);
        static::assertFalse($mapped->isVolunteer);
        static::assertSame('2024-04-24', $mapped->startDate->format('Y-m-d'));
        static::assertNull($mapped->stopDate);
        static::assertFalse($mapped->isLeaver());
        static::assertSame('NL75ABNA0510419224', $mapped->iban);
        static::assertSame('ABNANL2A', $mapped->bic);
        static::assertSame('Djoeke Adriaanse', $mapped->accountHolderName);
        static::assertTrue($mapped->hasMembershipType());
    }

    public function test_it_defaults_an_empty_soortlid_to_windsurfer(): void
    {
        $mapped = $this->subject->map($this->row(['Soortlid' => '']), 3);

        static::assertNull($mapped->membershipType);
        static::assertSame('Windsurfer', $mapped->membershipName);
        static::assertFalse($mapped->isVolunteer);
        static::assertFalse($mapped->hasMembershipType());
    }

    #[TestWith(['Volwassenlid', 'Windsurfer', false])]
    #[TestWith(['Jeugdlid', 'Windsurfer', false])]
    #[TestWith(['Gezinslid volwassen', 'Windsurfer', false])]
    #[TestWith(['Gezinslid jeugd', 'Windsurfer', false])]
    #[TestWith(['eredivisiezeilen lid', 'Windsurfer', false])]
    #[TestWith(['bestuurslid', 'Bestuurslid', true])]
    #[TestWith(['commissielid', 'Windsurfer', true])]
    #[TestWith(['zeil-instructeur - 2026', 'Zeiler', false])]
    #[TestWith(['Erelid', 'Erelid', false])]
    public function test_it_maps_each_membership_type(string $type, string $membershipName, bool $isVolunteer): void
    {
        $mapped = $this->subject->map($this->row(['Soortlid' => $type]), 3);

        static::assertSame($type, $mapped->membershipType);
        static::assertSame($membershipName, $mapped->membershipName);
        static::assertSame($isVolunteer, $mapped->isVolunteer);
    }

    public function test_it_maps_the_other_gender(): void
    {
        $mapped = $this->subject->map($this->row(['Geslacht' => 'other']), 3);

        static::assertSame(Gender::Other, $mapped->gender);
    }

    public function test_it_normalizes_the_postcode_and_trims_the_address(): void
    {
        $mapped = $this->subject->map($this->row([
            'Straat' => '  Frederik van Eedenweg  ',
            'Postcode' => '1359  kj ',
            'Huisnr.toev.' => ' A ',
        ]), 3);

        static::assertSame('Frederik van Eedenweg', $mapped->addressStreet);
        static::assertSame('1359KJ', $mapped->addressPostalcode);
        static::assertSame('A', $mapped->addressHousenumberAddition);
        static::assertSame('frederik van eedenweg|30|a|1359KJ', $mapped->addressKey());
    }

    public function test_it_falls_back_to_the_contact_email(): void
    {
        $mapped = $this->subject->map($this->row([
            'E-mail privé' => '',
            'Contact.email' => 'contact@example.com',
        ]), 3);

        static::assertSame('contact@example.com', $mapped->email);
    }

    public function test_it_marks_a_stop_date_as_a_leaver(): void
    {
        $mapped = $this->subject->map($this->row(['Stopdat. lid' => '2026-12-31']), 3);

        static::assertTrue($mapped->isLeaver());
        static::assertSame('2026-12-31', $mapped->stopDate?->format('Y-m-d'));
    }

    public function test_it_uses_the_incasso_account_holder_when_present(): void
    {
        $mapped = $this->subject->map($this->row(['Afw. Tenaamst. incasso' => 'J. Bergansius Adriaanse']), 3);

        static::assertSame('J. Bergansius Adriaanse', $mapped->accountHolderName);
    }

    public function test_it_rejects_an_invalid_iban(): void
    {
        try {
            $this->subject->map($this->row(['Banknummer 1' => 'NL00BANK0123456789']), 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('Banknummer 1', $exception->column);
            static::assertSame('NL00BANK0123456789', $exception->value);
        }
    }

    public function test_it_rejects_an_invalid_bic(): void
    {
        try {
            $this->subject->map($this->row(['BIC 1' => 'NOPE']), 7);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(7, $exception->rowNumber);
            static::assertSame('BIC 1', $exception->column);
        }
    }

    public function test_it_rejects_an_unknown_membership_type(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 7, column "Soortlid", value "Onbekend" is not a known membership type.');

        $this->subject->map($this->row(['Soortlid' => 'Onbekend']), 7);
    }

    public function test_it_rejects_an_unknown_gender(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 7, column "Geslacht"');

        $this->subject->map($this->row(['Geslacht' => 'onbekend']), 7);
    }

    public function test_it_accepts_a_datetime_object_for_dates(): void
    {
        $mapped = $this->subject->map($this->row([
            'Geb.datum' => new DateTimeImmutable('2003-06-26 00:00:00.000000'),
            'Startdat. lid' => new DateTimeImmutable('2016-02-06 00:00:00.000000'),
        ]), 3);

        static::assertSame('2003-06-26', $mapped->birthdate->format('Y-m-d'));
        static::assertSame('2016-02-06', $mapped->startDate->format('Y-m-d'));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return [
            'Lidnr' => '124277',
            'Soortlid' => 'Volwassenlid',
            'Startdat. lid' => '2024-04-24',
            'Stopdat. lid' => '',
            'Voornaam' => 'Djoeke',
            'Tussenv.' => '',
            'Achternaam' => 'Adriaanse',
            'Naamvoluit' => 'Djoeke Adriaanse',
            'Geb.datum' => '2009-02-20',
            'Geslacht' => 'Vrouw',
            'Straat' => 'Krachtveld ',
            'Huisnr' => '30',
            'Huisnr.toev.' => '',
            'Postcode' => '1359 KJ',
            'Plaats' => 'Almere ',
            'E-mail privé' => 'djoeke@example.com',
            'Contact.email' => 'djoeke@example.com',
            'Afw. Tenaamst. incasso' => '',
            'Banknummer 1' => 'NL75ABNA0510419224',
            'BIC 1' => 'ABNANL2A',
            ...$overrides,
        ];
    }
}
