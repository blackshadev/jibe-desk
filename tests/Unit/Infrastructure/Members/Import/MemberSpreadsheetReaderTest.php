<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use App\Infrastructure\Members\Import\MemberSpreadsheetReader;
use Override;
use Tests\Fixtures\MemberImport\MemberImportFixture;
use Tests\UnitTestCase;

final class MemberSpreadsheetReaderTest extends UnitTestCase
{
    /** @var list<string> */
    private array $spreadsheetPaths = [];

    private MemberSpreadsheetReader $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new MemberSpreadsheetReader();
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->spreadsheetPaths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }

        parent::tearDown();
    }

    public function test_read_returns_the_data_rows_with_their_spreadsheet_number(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row(),
            MemberImportFixture::row(['Lidnr' => '1002', 'Voornaam' => 'Bob']),
        ]);

        $rows = $this->subject->read($path);

        static::assertCount(2, $rows);
        static::assertSame(3, $rows[0]->number);
        static::assertSame('1001', $rows[0]->values['Lidnr']);
        static::assertSame('Anna', $rows[0]->values['Voornaam']);
        static::assertSame(4, $rows[1]->number);
        static::assertSame('1002', $rows[1]->values['Lidnr']);
        static::assertSame('Bob', $rows[1]->values['Voornaam']);
    }

    public function test_read_skips_empty_rows(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row(),
            [],
            MemberImportFixture::row(['Lidnr' => '1003']),
        ]);

        $rows = $this->subject->read($path);

        static::assertCount(2, $rows);
        static::assertSame('1001', $rows[0]->values['Lidnr']);
        static::assertSame('1003', $rows[1]->values['Lidnr']);
    }

    public function test_read_throws_when_the_file_does_not_exist(): void
    {
        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Spreadsheet not found: /does/not/exist.xlsx');

        $this->subject->read('/does/not/exist.xlsx');
    }

    public function test_read_throws_when_the_worksheet_is_missing(): void
    {
        $path = $this->spreadsheet([MemberImportFixture::row()], 'AndereSheet');

        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Spreadsheet does not contain a sheet named "Worksheet".');

        $this->subject->read($path);
    }

    public function test_read_throws_when_a_required_header_is_missing(): void
    {
        $path = $this->spreadsheet([], 'Worksheet', ['Lidnr', 'Soortlid']);

        try {
            $this->subject->read($path);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertStringContainsString(
                'Spreadsheet is missing required columns:',
                $exception->getMessage(),
            );
            static::assertStringContainsString('Achternaam', $exception->getMessage());
        }
    }

    /** @param list<array<string, mixed>> $rows @param list<string>|null $headers */
    private function spreadsheet(
        array $rows,
        string $sheetName = 'Worksheet',
        ?array $headers = null,
    ): string {
        $path = MemberImportFixture::create($rows, $sheetName, $headers);
        $this->spreadsheetPaths[] = $path;

        return $path;
    }
}
