<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\CsvFileException;
use App\Domain\Members\Import\CsvFormatException;
use App\Infrastructure\Members\Import\CsvFileReader;
use Override;
use RuntimeException;
use Tests\UnitTestCase;

final class CsvFileReaderTest extends UnitTestCase
{
    /** @var list<string> */
    private array $paths = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_reads_a_latin1_file_with_a_semicolon_delimiter_as_utf8(): void
    {
        $path = $this->writeLatin1File("\"Nummer \";Verdichting\n10;café\n");

        $rows = (new CsvFileReader(delimiter: ';', sourceEncoding: 'ISO-8859-1'))
            ->read($path, ['Nummer', 'Verdichting']);

        static::assertCount(1, $rows);
        static::assertSame(2, $rows[0]->number);
        static::assertSame('10', $rows[0]->values['Nummer']);
        static::assertSame('café', $rows[0]->values['Verdichting']);
    }

    public function test_it_rejects_a_file_that_misses_a_required_header(): void
    {
        $path = $this->writeLatin1File("Nummer\n10\n");

        $this->expectException(CsvFormatException::class);

        (new CsvFileReader(delimiter: ';'))->read($path, ['Nummer', 'Verdichting']);
    }

    public function test_it_rejects_a_file_that_does_not_exist(): void
    {
        $path = sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(8)) . '.csv';

        $this->expectException(CsvFileException::class);

        (new CsvFileReader())->read($path, ['Nummer']);
    }

    private function writeLatin1File(string $utf8Contents): string
    {
        $path = sys_get_temp_dir() . '/csv-reader-' . bin2hex(random_bytes(8)) . '.csv';
        $contents = mb_convert_encoding($utf8Contents, 'ISO-8859-1', 'UTF-8');

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write %s', $path));
        }

        $this->paths[] = $path;

        return $path;
    }
}
