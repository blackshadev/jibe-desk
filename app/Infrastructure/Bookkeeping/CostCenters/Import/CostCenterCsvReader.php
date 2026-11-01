<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFileException;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;

final readonly class CostCenterCsvReader
{
    /**
     * @param non-empty-list<string> $expectedHeaders
     * @return list<array{lineNumber: int, values: list<string>}>
     */
    public function read(string $path, array $expectedHeaders): array
    {
        if (!is_file($path)) {
            throw CostCenterImportFileException::fileNotFound($path);
        }

        if (!is_readable($path)) {
            throw CostCenterImportFileException::unreadableFile($path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw CostCenterImportFileException::unreadableFile($path);
        }

        $contents = mb_convert_encoding($contents, 'UTF-8', 'ISO-8859-1');
        $lines = explode("\n", rtrim($contents, "\r\n"));

        if ($lines[0] === '') {
            throw CostCenterImportFormatException::emptyFile($path);
        }

        $headerValues = $this->parseLine($lines[0]);

        if ($headerValues !== $expectedHeaders) {
            throw CostCenterImportFormatException::headerMismatch($path, $expectedHeaders);
        }

        $rows = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = [
                'lineNumber' => $index + 2,
                'values' => $this->parseLine($line),
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function parseLine(string $line): array
    {
        return array_map(
            static fn (mixed $value): string => (string) $value,
            str_getcsv($line, ';', '"', ''),
        );
    }
}
