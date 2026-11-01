<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use Carbon\CarbonImmutable;

final readonly class MemberSpreadsheetValidator
{
    private const int MINIMUM_INSTRUCTOR_AGE = 16;

    public function __construct(
        private MemberSpreadsheetReader $reader,
        private SpreadsheetRowMapper $rowMapper,
    ) {}

    /**
     * @return list<MappedMemberRow>
     *
     * @throws MemberImportException when a row cannot be mapped
     */
    public function validate(string $path): array
    {
        $rows = [];

        foreach ($this->reader->read($path) as $spreadsheetRow) {
            $row = $this->rowMapper->map($spreadsheetRow->values, $spreadsheetRow->number);
            self::assertSailInstructorIsAdult($row, $spreadsheetRow->number);

            $rows[] = $row;
        }

        return $rows;
    }

    private static function assertSailInstructorIsAdult(MappedMemberRow $row, int $rowNumber): void
    {
        if (!$row->isSailInstructor()) {
            return;
        }

        $ageAtMembershipStart = CarbonImmutable::instance($row->birthdate)->diffInYears($row->startDate);

        if ($ageAtMembershipStart < self::MINIMUM_INSTRUCTOR_AGE) {
            throw MemberImportException::instructorTooYoung($rowNumber, $row->birthdate->format('Y-m-d'));
        }
    }
}
