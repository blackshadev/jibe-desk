<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;

final readonly class SpreadsheetRowMapper
{
    private const array MEMBERSHIP_NAME_BY_TYPE = [
        'Volwassenlid' => 'Windsurfer',
        'Jeugdlid' => 'Windsurfer',
        'Gezinslid volwassen' => 'Windsurfer',
        'Gezinslid jeugd' => 'Windsurfer',
        'eredivisiezeilen lid' => 'Windsurfer',
        'bestuurslid' => 'Bestuurslid',
        'commissielid' => 'Windsurfer',
        'zeil-instructeur - 2026' => 'Zeiler',
        'Erelid' => 'Erelid',
    ];

    private const array VOLUNTEER_TYPES = ['bestuurslid', 'commissielid'];

    private const string DEFAULT_MEMBERSHIP_NAME = 'Windsurfer';

    public function __construct(
        private SpreadsheetValueReader $values = new SpreadsheetValueReader(),
        private SpreadsheetDateReader $dates = new SpreadsheetDateReader(),
        private SpreadsheetValueParser $parser = new SpreadsheetValueParser(),
    ) {}

    /**
     * @param array<string, mixed> $values
     */
    public function map(array $values, int $rowNumber): MappedMemberRow
    {
        $membershipType = $this->values->optionalText($values, 'Soortlid');
        $firstName = $this->values->requiredText($values, 'Voornaam', $rowNumber);
        $lastName = $this->values->requiredText($values, 'Achternaam', $rowNumber);
        $privateEmail = $this->values->text($values, 'E-mail privé');
        $contactEmail = $this->values->optionalText($values, 'Contact.email');
        $fullName = $this->values->fullName(
            $this->values->text($values, 'Naamvoluit'),
            $firstName,
            $this->values->text($values, 'Tussenv.'),
            $lastName,
        );

        return new MappedMemberRow(
            lidnr: $this->values->requiredText($values, 'Lidnr', $rowNumber),
            firstName: $firstName,
            infixName: $this->values->text($values, 'Tussenv.'),
            lastName: $lastName,
            fullName: $fullName,
            email: $this->values->email($privateEmail, $contactEmail, $rowNumber),
            contactEmail: $contactEmail,
            birthdate: $this->dates->date($values, 'Geb.datum', $rowNumber),
            gender: $this->parser->gender(
                $this->values->requiredText($values, 'Geslacht', $rowNumber),
                $rowNumber,
            ),
            addressStreet: $this->values->requiredText($values, 'Straat', $rowNumber),
            addressHousenumber: $this->values->requiredText($values, 'Huisnr', $rowNumber),
            addressHousenumberAddition: $this->values->optionalText($values, 'Huisnr.toev.'),
            addressPostalcode: $this->parser->postalCode(
                $this->values->requiredText($values, 'Postcode', $rowNumber),
                $rowNumber,
            ),
            addressCity: $this->values->requiredText($values, 'Plaats', $rowNumber),
            membershipType: $membershipType,
            membershipName: $this->membershipName($membershipType, $rowNumber),
            isVolunteer: $this->isVolunteer($membershipType),
            startDate: $this->dates->date($values, 'Startdat. lid', $rowNumber),
            stopDate: $this->dates->optionalDate($values, 'Stopdat. lid', $rowNumber),
            iban: $this->parser->iban(
                $this->values->requiredText($values, 'Banknummer 1', $rowNumber),
                $rowNumber,
            ),
            bic: $this->parser->bic(
                $this->values->requiredText($values, 'BIC 1', $rowNumber),
                $rowNumber,
            ),
            accountHolderName: $this->values->accountHolderName(
                $this->values->text($values, 'Afw. Tenaamst. incasso'),
                $fullName,
            ),
        );
    }

    private function membershipName(?string $membershipType, int $rowNumber): string
    {
        if ($membershipType === null) {
            return self::DEFAULT_MEMBERSHIP_NAME;
        }

        return self::MEMBERSHIP_NAME_BY_TYPE[$membershipType] ?? throw MemberImportException::unknownMembershipType($rowNumber, $membershipType);
    }

    private function isVolunteer(?string $membershipType): bool
    {
        return $membershipType !== null && in_array($membershipType, self::VOLUNTEER_TYPES, true);
    }
}
