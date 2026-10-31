<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use RuntimeException;

final class MemberImportException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $rowNumber = null,
        public readonly ?string $column = null,
        public readonly ?string $value = null,
    ) {
        parent::__construct($message);
    }

    public static function membersTableNotEmpty(): self
    {
        return new self('The members table is not empty. This import only runs against a fresh database.');
    }

    public static function noReport(): self
    {
        return new self('The import did not produce a report.');
    }

    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('Spreadsheet not found: %s', $path));
    }

    public static function unreadableSpreadsheet(string $path): self
    {
        return new self(sprintf('Spreadsheet could not be read: %s', $path));
    }

    public static function sheetMissing(string $sheetName): self
    {
        return new self(sprintf('Spreadsheet does not contain a sheet named "%s".', $sheetName));
    }

    /**
     * @param list<string> $missingHeaders
     */
    public static function headerMismatch(array $missingHeaders): self
    {
        return new self(sprintf('Spreadsheet is missing required columns: %s', implode(', ', $missingHeaders)));
    }

    public static function invalidDateOption(string $option, string $value): self
    {
        return new self(sprintf('Option --%s must be a date (Y-m-d), got "%s".', $option, $value));
    }

    public static function missingMembership(string $membershipName): self
    {
        return new self(sprintf('Membership tier "%s" does not exist and could not be created.', $membershipName));
    }

    public static function missingValue(int $rowNumber, string $column): self
    {
        return self::forRow($rowNumber, $column, '', 'is required but empty');
    }

    public static function invalidDate(int $rowNumber, string $column, string $value): self
    {
        return self::forRow($rowNumber, $column, $value, 'is not a valid date');
    }

    public static function invalidIban(int $rowNumber, string $value): self
    {
        return self::forRow($rowNumber, 'Banknummer 1', $value, 'is not a valid IBAN');
    }

    public static function invalidBic(int $rowNumber, string $value): self
    {
        return self::forRow($rowNumber, 'BIC 1', $value, 'is not a valid BIC');
    }

    public static function invalidPostalCode(int $rowNumber, string $value): self
    {
        return self::forRow($rowNumber, 'Postcode', $value, 'is not a valid Dutch postcode');
    }

    public static function unknownGender(int $rowNumber, string $value): self
    {
        return self::forRow($rowNumber, 'Geslacht', $value, 'is not a known gender');
    }

    public static function unknownMembershipType(int $rowNumber, string $value): self
    {
        return self::forRow($rowNumber, 'Soortlid', $value, 'is not a known membership type');
    }

    public static function instructorTooYoung(int $rowNumber, string $value): self
    {
        return self::forRow($rowNumber, 'Geb.datum', $value, 'is too young to be a sailing instructor');
    }

    public static function forRow(int $rowNumber, string $column, string $value, string $reason): self
    {
        return new self(
            sprintf('Row %d, column "%s", value "%s" %s.', $rowNumber, $column, $value, $reason),
            $rowNumber,
            $column,
            $value,
        );
    }
}
