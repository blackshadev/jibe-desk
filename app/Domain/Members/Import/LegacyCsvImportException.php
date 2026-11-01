<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use RuntimeException;

/**
 * Failure of one of the one-time legacy CSV imports. Every row-level failure
 * carries the row number and, when known, the lidnr of the offending row.
 */
final class LegacyCsvImportException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $rowNumber = null,
        public readonly ?string $lidnr = null,
    ) {
        parent::__construct($message);
    }

    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('CSV file not found: %s', $path));
    }

    public static function unreadableFile(string $path): self
    {
        return new self(sprintf('CSV file could not be read: %s', $path));
    }

    /**
     * @param list<string> $missingHeaders
     */
    public static function headerMismatch(array $missingHeaders): self
    {
        return new self(sprintf('CSV is missing required columns: %s', implode(', ', $missingHeaders)));
    }

    public static function missingValue(int $rowNumber, string $column, ?string $lidnr = null): self
    {
        return self::forRow($rowNumber, sprintf('column "%s" is required but empty.', $column), $lidnr);
    }

    public static function invalidDate(int $rowNumber, string $column, string $value, ?string $lidnr = null): self
    {
        return self::forRow($rowNumber, sprintf('column "%s", value "%s" is not a valid Y-m-d date.', $column, $value), $lidnr);
    }

    public static function unknownLidnr(int $rowNumber, string $lidnr): self
    {
        return self::forRow($rowNumber, sprintf('lidnr "%s" does not belong to a member.', $lidnr), $lidnr);
    }

    public static function duplicateRecord(int $rowNumber, string $lidnr, string $value): self
    {
        return self::forRow($rowNumber, sprintf('"%s" appears more than once for this member.', $value), $lidnr);
    }

    public static function endBeforeStart(int $rowNumber, string $lidnr, string $startDate, string $endDate): self
    {
        return self::forRow(
            $rowNumber,
            sprintf('end_date "%s" is before start_date "%s".', $endDate, $startDate),
            $lidnr,
        );
    }

    public static function openEndForStoppedMember(int $rowNumber, string $lidnr, string $subject): self
    {
        return self::forRow(
            $rowNumber,
            sprintf('an open-ended %s is not allowed because the member has stopped.', $subject),
            $lidnr,
        );
    }

    public static function missingMemberObjectType(string $name): self
    {
        return new self(sprintf('Member object type "%s" does not exist.', $name));
    }

    public static function tagAlreadyRecorded(int $rowNumber, string $lidnr, string $tagNumber): self
    {
        return self::forRow($rowNumber, sprintf('tag "%s" is already recorded.', $tagNumber), $lidnr);
    }

    public static function invalidObjectNumber(int $rowNumber, string $lidnr, string $value): self
    {
        return self::forRow($rowNumber, sprintf('object number "%s" must look like 5-01.', $value), $lidnr);
    }

    public static function unknownStorageSpaceLocation(int $rowNumber, string $lidnr, string $locationName): self
    {
        return self::forRow($rowNumber, sprintf('storage location "%s" does not exist.', $locationName), $lidnr);
    }

    public static function unknownStorageSpace(int $rowNumber, string $lidnr, string $objectNumber): self
    {
        return self::forRow($rowNumber, sprintf('storage space "%s" does not exist.', $objectNumber), $lidnr);
    }

    public static function overlappingRental(int $rowNumber, string $lidnr, string $objectNumber): self
    {
        return self::forRow(
            $rowNumber,
            sprintf('storage space "%s" is already rented in this period.', $objectNumber),
            $lidnr,
        );
    }

    public static function invalidDateOption(string $option, string $value): self
    {
        return new self(sprintf('Option --%s must be a date (Y-m-d), got "%s".', $option, $value));
    }

    private static function forRow(int $rowNumber, string $reason, ?string $lidnr = null): self
    {
        $context = $lidnr === null
            ? sprintf('Row %d', $rowNumber)
            : sprintf('Row %d (lidnr %s)', $rowNumber, $lidnr);

        return new self(sprintf('%s: %s', $context, $reason), $rowNumber, $lidnr);
    }
}
