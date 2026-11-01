<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportValueException;
use App\Infrastructure\Bookkeeping\CostCenters\Import\CostCenterHierarchy;
use App\Infrastructure\Bookkeeping\CostCenters\Import\GrootboekRecord;
use App\Infrastructure\Bookkeeping\CostCenters\Import\HoofdverdichtingRecord;
use App\Infrastructure\Bookkeeping\CostCenters\Import\VerdichtingRecord;
use Tests\UnitTestCase;

final class CostCenterHierarchyTest extends UnitTestCase
{
    public function test_it_builds_qualified_numbers_and_parents(): void
    {
        $subject = new CostCenterHierarchy(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Kosten')],
        );

        static::assertSame(['20', '20.10'], $subject->groupNumbers());
        static::assertSame('20.10', $subject->groupNumberForVerdichting('10'));

        $verdichting = $subject->groups()[1];

        static::assertSame('20.10', $verdichting->number);
        static::assertSame('10', $verdichting->externalNumber);
        static::assertSame('materiaal', $verdichting->name);
        static::assertSame('20', $verdichting->parentNumber);
    }

    public function test_it_rejects_a_verdichting_without_a_known_parent(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('missing from the hoofdverdichtingen file: 10');

        new CostCenterHierarchy(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '99', 'Kosten')],
        );
    }

    public function test_it_rejects_a_parent_name_that_disagrees(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('disagree about the hierarchy for: 10');

        new CostCenterHierarchy(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Opbrengsten')],
        );
    }

    public function test_it_rejects_duplicate_qualified_numbers(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('1.10');

        new CostCenterHierarchy(
            [
                new HoofdverdichtingRecord(2, '1', 'Kosten'),
                new HoofdverdichtingRecord(3, '1.10', 'Dubbel'),
            ],
            [new VerdichtingRecord(4, '10', 'materiaal', '1', 'Kosten')],
        );
    }

    public function test_it_accepts_accounts_that_match_the_hierarchy(): void
    {
        $subject = new CostCenterHierarchy(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Kosten')],
        );

        $subject->assertAccountsBelong([
            new GrootboekRecord(2, '100', 'Materiaal', '10', 'materiaal'),
        ]);

        static::assertCount(2, $subject->groups());
    }

    public function test_it_rejects_an_account_with_an_unknown_verdichting(): void
    {
        $subject = new CostCenterHierarchy(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Kosten')],
        );

        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('100');
        $this->expectExceptionMessage('77');

        $subject->assertAccountsBelong([
            new GrootboekRecord(2, '100', 'Materiaal', '77', 'Onbekend'),
        ]);
    }

    public function test_it_rejects_an_account_whose_verdichting_name_disagrees(): void
    {
        $subject = new CostCenterHierarchy(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Kosten')],
        );

        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('hierarchy for accounts: 100');

        $subject->assertAccountsBelong([
            new GrootboekRecord(2, '100', 'Materiaal', '10', 'Anders'),
        ]);
    }
}
