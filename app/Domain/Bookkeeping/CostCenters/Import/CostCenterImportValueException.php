<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters\Import;

final class CostCenterImportValueException extends CostCenterImportException
{
    /**
     * @param list<string> $verdichtingNumbers
     */
    public static function unknownParent(array $verdichtingNumbers): self
    {
        return new self(sprintf(
            'The verdichtingen file references a hoofdverdichting that is missing from the hoofdverdichtingen file: %s',
            implode(', ', self::uniqueSorted($verdichtingNumbers)),
        ));
    }

    /**
     * @param list<string> $verdichtingNumbers
     */
    public static function parentNameMismatch(array $verdichtingNumbers): self
    {
        return new self(sprintf(
            'The verdichtingen and hoofdverdichtingen files disagree about the hierarchy for: %s',
            implode(', ', self::uniqueSorted($verdichtingNumbers)),
        ));
    }

    /**
     * @param list<string> $accountNumbers
     * @param list<string> $verdichtingNumbers
     */
    public static function unknownVerdichting(array $accountNumbers, array $verdichtingNumbers): self
    {
        return new self(sprintf(
            'Accounts %s reference unknown verdichting numbers: %s',
            implode(', ', self::uniqueSorted($accountNumbers)),
            implode(', ', self::uniqueSorted($verdichtingNumbers)),
        ));
    }

    /**
     * @param list<string> $accountNumbers
     */
    public static function accountVerdichtingNameMismatch(array $accountNumbers): self
    {
        return new self(sprintf(
            'The grootboek and verdichtingen files disagree about the hierarchy for accounts: %s',
            implode(', ', self::uniqueSorted($accountNumbers)),
        ));
    }

    /**
     * @param list<string> $numbers
     */
    public static function duplicateQualifiedNumbers(array $numbers): self
    {
        return new self(sprintf(
            'The files produce duplicate qualified group numbers: %s',
            implode(', ', self::uniqueSorted($numbers)),
        ));
    }

    /**
     * @param list<string> $numbers
     * @return list<string>
     */
    private static function uniqueSorted(array $numbers): array
    {
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }
}
