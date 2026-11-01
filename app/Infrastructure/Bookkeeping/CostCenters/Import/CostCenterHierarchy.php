<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportValueException;
use LogicException;

final readonly class CostCenterHierarchy
{
    /** @var list<CostCenterGroupRecord> */
    private array $groups;

    /** @var array<string, string> */
    private array $qualifiedNumberByVerdichtingNumber;

    /** @var array<string, string> */
    private array $nameByVerdichtingNumber;

    /**
     * @param list<HoofdverdichtingRecord> $hoofdverdichtingen
     * @param list<VerdichtingRecord> $verdichtingen
     */
    public function __construct(
        array $hoofdverdichtingen,
        array $verdichtingen,
    ) {
        CostCenterHierarchyValidator::assertTreesAgree($hoofdverdichtingen, $verdichtingen);

        $groups = array_map(
            static fn (HoofdverdichtingRecord $record): CostCenterGroupRecord => new CostCenterGroupRecord(
                number: $record->number,
                externalNumber: $record->number,
                name: $record->name,
                parentNumber: null,
            ),
            $hoofdverdichtingen,
        );

        $qualifiedNumberByVerdichtingNumber = [];
        $nameByVerdichtingNumber = [];

        foreach ($verdichtingen as $verdichting) {
            $qualifiedNumber = $verdichting->parentNumber . '.' . $verdichting->number;

            $groups[] = new CostCenterGroupRecord(
                number: $qualifiedNumber,
                externalNumber: $verdichting->number,
                name: $verdichting->name,
                parentNumber: $verdichting->parentNumber,
            );

            $qualifiedNumberByVerdichtingNumber[$verdichting->number] = $qualifiedNumber;
            $nameByVerdichtingNumber[$verdichting->number] = $verdichting->name;
        }

        $duplicates = self::duplicates(array_map(
            static fn (CostCenterGroupRecord $group): string => $group->number,
            $groups,
        ));

        if ($duplicates !== []) {
            throw CostCenterImportValueException::duplicateQualifiedNumbers($duplicates);
        }

        $this->groups = $groups;
        $this->qualifiedNumberByVerdichtingNumber = $qualifiedNumberByVerdichtingNumber;
        $this->nameByVerdichtingNumber = $nameByVerdichtingNumber;
    }

    /** @return list<CostCenterGroupRecord> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** @return list<string> */
    public function groupNumbers(): array
    {
        return array_map(
            static fn (CostCenterGroupRecord $group): string => $group->number,
            $this->groups,
        );
    }

    public function groupNumberForVerdichting(string $verdichtingNumber): string
    {
        return $this->qualifiedNumberByVerdichtingNumber[$verdichtingNumber] ?? throw new LogicException(sprintf('Unknown verdichting number "%s".', $verdichtingNumber));
    }

    /** @param list<GrootboekRecord> $grootboek */
    public function assertAccountsBelong(array $grootboek): void
    {
        CostCenterHierarchyValidator::assertAccountsBelong($grootboek, $this->nameByVerdichtingNumber);
    }

    /**
     * @param list<string> $numbers
     * @return list<string>
     */
    private static function duplicates(array $numbers): array
    {
        $counts = array_count_values($numbers);

        return array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));
    }
}
