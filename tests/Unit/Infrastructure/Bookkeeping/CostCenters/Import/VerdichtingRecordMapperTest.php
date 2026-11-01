<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Infrastructure\Bookkeeping\CostCenters\Import\VerdichtingRecordMapper;
use Override;
use Tests\Fixtures\CostCenterImport\CostCenterImportFixture;
use Tests\UnitTestCase;

final class VerdichtingRecordMapperTest extends UnitTestCase
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

    public function test_it_maps_the_verdichting_rows_into_records(): void
    {
        $path = $this->track(CostCenterImportFixture::verdichtingen([
            ['10', 'materiaal', '20', 'Kosten'],
        ]));

        $records = new VerdichtingRecordMapper()->map($path);

        static::assertCount(1, $records);
        static::assertSame(2, $records[0]->lineNumber);
        static::assertSame('10', $records[0]->number);
        static::assertSame('materiaal', $records[0]->name);
        static::assertSame('20', $records[0]->parentNumber);
        static::assertSame('Kosten', $records[0]->parentName);
    }

    public function test_it_rejects_duplicate_verdichting_numbers(): void
    {
        $path = $this->track(CostCenterImportFixture::verdichtingen([
            ['10', 'materiaal', '20', 'Kosten'],
            ['10', 'materiaal', '20', 'Kosten'],
        ]));

        $this->expectException(CostCenterImportFormatException::class);
        $this->expectExceptionMessage('duplicate numbers: 10');

        new VerdichtingRecordMapper()->map($path);
    }

    public function test_it_rejects_a_row_without_a_parent_number(): void
    {
        $path = $this->track(CostCenterImportFixture::verdichtingen([
            ['10', 'materiaal', '', 'Kosten'],
        ]));

        $this->expectException(CostCenterImportFormatException::class);
        $this->expectExceptionMessage('column "Hoofdverdichting nummer" is required but empty');

        new VerdichtingRecordMapper()->map($path);
    }

    private function track(string $path): string
    {
        $this->paths[] = $path;

        return $path;
    }
}
