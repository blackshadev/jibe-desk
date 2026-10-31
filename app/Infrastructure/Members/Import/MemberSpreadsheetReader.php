<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;

final readonly class MemberSpreadsheetReader
{
    private const string SHEET_NAME = 'Worksheet';

    private const int HEADER_ROW = 2;

    private const array REQUIRED_HEADERS = [
        'Lidnr',
        'Soortlid',
        'Startdat. lid',
        'Stopdat. lid',
        'Voornaam',
        'Tussenv.',
        'Achternaam',
        'Naamvoluit',
        'Geb.datum',
        'Geslacht',
        'Straat',
        'Huisnr',
        'Huisnr.toev.',
        'Postcode',
        'Plaats',
        'E-mail privé',
        'Contact.email',
        'Afw. Tenaamst. incasso',
        'Banknummer 1',
        'BIC 1',
    ];

    /** @return list<SpreadsheetImportRow> */
    public function read(string $path): array
    {
        if (!is_file($path)) {
            throw MemberImportException::fileNotFound($path);
        }

        $reader = new Reader();

        try {
            $reader->open($path);
        } catch (IOException) {
            throw MemberImportException::unreadableSpreadsheet($path);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== self::SHEET_NAME) {
                    continue;
                }

                return $this->readSheet($sheet);
            }
        } finally {
            $reader->close();
        }

        throw MemberImportException::sheetMissing(self::SHEET_NAME);
    }

    /** @return list<SpreadsheetImportRow> */
    private function readSheet(Sheet $sheet): array
    {
        $rows = [];
        $headers = [];

        foreach ($sheet->getRowIterator() as $number => $row) {
            $values = array_map(
                static fn (Cell $cell): mixed => $cell->getValue(),
                $row->getCells(),
            );

            if ($number < self::HEADER_ROW) {
                continue;
            }

            if ($number === self::HEADER_ROW) {
                $headers = $this->headerNames($values);
                $this->assertRequiredHeaders($headers);
                continue;
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $rows[] = new SpreadsheetImportRow($number, $this->associate($headers, $values));
        }

        $this->assertRequiredHeaders($headers);

        return $rows;
    }

    /**
     * @param array<int, mixed> $values
     * @return list<string>
     */
    private function headerNames(array $values): array
    {
        return array_values(array_map(static fn (mixed $value): string => trim((string) $value), $values));
    }

    /** @param list<string> $headers */
    private function assertRequiredHeaders(array $headers): void
    {
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missingHeaders === []) {
            return;
        }

        throw MemberImportException::headerMismatch($missingHeaders);
    }

    /**
     * @param list<string> $headers
     * @param array<int, mixed> $values
     * @return array<string, mixed>
     */
    private function associate(array $headers, array $values): array
    {
        $associated = [];

        foreach ($headers as $index => $header) {
            $associated[$header] = $values[$index] ?? null;
        }

        return $associated;
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return array_all(
            $values,
            static fn (mixed $value): bool => $value === null || is_string($value) && trim($value) === '',
        );
    }
}
