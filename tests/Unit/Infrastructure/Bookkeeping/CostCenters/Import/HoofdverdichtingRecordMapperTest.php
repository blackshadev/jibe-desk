<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Infrastructure\Bookkeeping\CostCenters\Import\HoofdverdichtingRecordMapper;
use Override;
use Tests\Fixtures\CostCenterImport\CostCenterImportFixture;
use Tests\UnitTestCase;

final class HoofdverdichtingRecordMapperTest extends UnitTestCase
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

    public function test_it_maps_the_hoofdverdichting_rows_into_records(): void
    {
        $path = $this->track(CostCenterImportFixture::hoofdverdichtingen([
            ['20', 'Kosten'],
        ]));

        $records = new HoofdverdichtingRecordMapper()->map($path);

        static::assertCount(1, $records);
        static::assertSame(2, $records[0]->lineNumber);
        static::assertSame('20', $records[0]->number);
        static::assertSame('Kosten', $records[0]->name);
    }

    public function test_it_rejects_duplicate_hoofdverdichting_numbers(): void
    {
        $path = $this->track(CostCenterImportFixture::hoofdverdichtingen([
            ['20', 'Kosten'],
            ['20', 'Kosten'],
        ]));

        $this->expectException(CostCenterImportFormatException::class);
        $this->expectExceptionMessage('duplicate numbers: 20');

        new HoofdverdichtingRecordMapper()->map($path);
    }

    public function test_it_rejects_a_row_without_a_name(): void
    {
        $path = $this->track(CostCenterImportFixture::hoofdverdichtingen([
            ['20', ''],
        ]));

        $this->expectException(CostCenterImportFormatException::class);
        $this->expectExceptionMessage('column "Hoofdverdichting" is required but empty');

        new HoofdverdichtingRecordMapper()->map($path);
    }

    private function track(string $path): string
    {
        $this->paths[] = $path;

        return $path;
    }
}
