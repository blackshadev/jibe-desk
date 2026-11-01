<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportValueException;

final readonly class CostCenterHierarchyValidator
{
    /**
     * @param list<HoofdverdichtingRecord> $hoofdverdichtingen
     * @param list<VerdichtingRecord> $verdichtingen
     */
    public static function assertTreesAgree(array $hoofdverdichtingen, array $verdichtingen): void
    {
        $hoofdverdichtingNames = [];

        foreach ($hoofdverdichtingen as $hoofdverdichting) {
            $hoofdverdichtingNames[$hoofdverdichting->number] = $hoofdverdichting->name;
        }

        $unknownParents = [];
        $nameMismatches = [];

        foreach ($verdichtingen as $verdichting) {
            if (!array_key_exists($verdichting->parentNumber, $hoofdverdichtingNames)) {
                $unknownParents[] = $verdichting->number;

                continue;
            }

            if (trim($hoofdverdichtingNames[$verdichting->parentNumber]) !== trim($verdichting->parentName)) {
                $nameMismatches[] = $verdichting->number;
            }
        }

        if ($unknownParents !== []) {
            throw CostCenterImportValueException::unknownParent($unknownParents);
        }

        if ($nameMismatches !== []) {
            throw CostCenterImportValueException::parentNameMismatch($nameMismatches);
        }
    }

    /**
     * @param list<GrootboekRecord> $grootboek
     * @param array<string, string> $nameByVerdichtingNumber
     */
    public static function assertAccountsBelong(array $grootboek, array $nameByVerdichtingNumber): void
    {
        $unknownAccountNumbers = [];
        $unknownVerdichtingNumbers = [];
        $mismatchedAccountNumbers = [];

        foreach ($grootboek as $record) {
            if (!array_key_exists($record->verdichtingNumber, $nameByVerdichtingNumber)) {
                $unknownAccountNumbers[] = $record->accountNumber;
                $unknownVerdichtingNumbers[] = $record->verdichtingNumber;

                continue;
            }

            $knownName = $nameByVerdichtingNumber[$record->verdichtingNumber];

            if (trim($knownName) !== trim($record->verdichtingName)) {
                $mismatchedAccountNumbers[] = $record->accountNumber;
            }
        }

        if ($unknownAccountNumbers !== []) {
            throw CostCenterImportValueException::unknownVerdichting(
                $unknownAccountNumbers,
                $unknownVerdichtingNumbers,
            );
        }

        if ($mismatchedAccountNumbers !== []) {
            throw CostCenterImportValueException::accountVerdichtingNameMismatch($mismatchedAccountNumbers);
        }
    }
}
