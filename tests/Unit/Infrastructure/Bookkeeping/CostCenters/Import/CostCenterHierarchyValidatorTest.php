<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportValueException;
use App\Infrastructure\Bookkeeping\CostCenters\Import\CostCenterHierarchyValidator;
use App\Infrastructure\Bookkeeping\CostCenters\Import\GrootboekRecord;
use App\Infrastructure\Bookkeeping\CostCenters\Import\HoofdverdichtingRecord;
use App\Infrastructure\Bookkeeping\CostCenters\Import\VerdichtingRecord;
use Tests\UnitTestCase;

final class CostCenterHierarchyValidatorTest extends UnitTestCase
{
    public function test_it_accepts_trees_that_agree(): void
    {
        $this->expectNotToPerformAssertions();

        CostCenterHierarchyValidator::assertTreesAgree(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Kosten')],
        );
    }

    public function test_it_rejects_a_verdichting_with_an_unknown_parent(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('missing from the hoofdverdichtingen file: 10');

        CostCenterHierarchyValidator::assertTreesAgree(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '99', 'Kosten')],
        );
    }

    public function test_it_rejects_a_parent_name_that_disagrees(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('disagree about the hierarchy for: 10');

        CostCenterHierarchyValidator::assertTreesAgree(
            [new HoofdverdichtingRecord(2, '20', 'Kosten')],
            [new VerdichtingRecord(3, '10', 'materiaal', '20', 'Opbrengsten')],
        );
    }

    public function test_it_accepts_accounts_that_match_the_verdichtingen(): void
    {
        $this->expectNotToPerformAssertions();

        CostCenterHierarchyValidator::assertAccountsBelong(
            [new GrootboekRecord(2, '100', 'Materiaal', '10', 'materiaal')],
            ['10' => 'materiaal'],
        );
    }

    public function test_it_rejects_an_account_with_an_unknown_verdichting(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('Accounts 100 reference unknown verdichting numbers: 77');

        CostCenterHierarchyValidator::assertAccountsBelong(
            [new GrootboekRecord(2, '100', 'Materiaal', '77', 'Onbekend')],
            ['10' => 'materiaal'],
        );
    }

    public function test_it_rejects_an_account_whose_verdichting_name_disagrees(): void
    {
        $this->expectException(CostCenterImportValueException::class);
        $this->expectExceptionMessage('disagree about the hierarchy for accounts: 100');

        CostCenterHierarchyValidator::assertAccountsBelong(
            [new GrootboekRecord(2, '100', 'Materiaal', '10', 'Anders')],
            ['10' => 'materiaal'],
        );
    }
}
