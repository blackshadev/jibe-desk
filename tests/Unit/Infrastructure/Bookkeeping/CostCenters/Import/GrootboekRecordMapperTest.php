<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Infrastructure\Bookkeeping\CostCenters\Import\GrootboekRecordMapper;
use Override;
use Tests\Fixtures\CostCenterImport\CostCenterImportFixture;
use Tests\UnitTestCase;

final class GrootboekRecordMapperTest extends UnitTestCase
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

    public function test_it_maps_the_grootboek_rows_into_records(): void
    {
        $path = $this->track(CostCenterImportFixture::grootboek([
            ['100', 'Materiaal', 'B', 'K', 'D', '', '10', 'materiaal'],
        ]));

        $records = (new GrootboekRecordMapper())->map($path);

        static::assertCount(1, $records);
        static::assertSame(2, $records[0]->lineNumber);
        static::assertSame('100', $records[0]->accountNumber);
        static::assertSame('Materiaal', $records[0]->title);
        static::assertSame('10', $records[0]->verdichtingNumber);
        static::assertSame('materiaal', $records[0]->verdichtingName);
    }

    public function test_it_rejects_duplicate_account_numbers(): void
    {
        $path = $this->track(CostCenterImportFixture::grootboek([
            ['100', 'Materiaal', 'B', 'K', 'D', '', '10', 'materiaal'],
            ['100', 'Materiaal', 'B', 'K', 'D', '', '10', 'materiaal'],
        ]));

        $this->expectException(CostCenterImportFormatException::class);
        $this->expectExceptionMessage('duplicate account numbers: 100');

        (new GrootboekRecordMapper())->map($path);
    }

    public function test_it_rejects_a_row_without_an_account_number(): void
    {
        $path = $this->track(CostCenterImportFixture::grootboek([
            ['', 'Materiaal', 'B', 'K', 'D', '', '10', 'materiaal'],
        ]));

        $this->expectException(CostCenterImportFormatException::class);
        $this->expectExceptionMessage('column "Rekening" is required but empty');

        (new GrootboekRecordMapper())->map($path);
    }

    private function track(string $path): string
    {
        $this->paths[] = $path;

        return $path;
    }
}
