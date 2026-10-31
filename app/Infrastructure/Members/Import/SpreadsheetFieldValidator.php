<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Domain\Members\Import\MemberImportException;

final readonly class SpreadsheetFieldValidator
{
    public function gender(string $value, int $rowNumber): Gender
    {
        return match ($value) {
            'Man' => Gender::Male,
            'Vrouw' => Gender::Female,
            'other' => Gender::Other,
            default => throw MemberImportException::unknownGender($rowNumber, $value),
        };
    }

    public function postalCode(string $value, int $rowNumber): string
    {
        $compact = strtoupper(str_replace(' ', '', $value));

        $matched = preg_match('/^(\d{4})([A-Z]{2})$/', $compact, $matches);

        if ($matched !== 1) {
            throw MemberImportException::invalidPostalCode($rowNumber, $value);
        }

        return sprintf('%s %s', $matches[1], $matches[2]);
    }

    public function iban(string $value, int $rowNumber): string
    {
        $iban = strtoupper(str_replace(' ', '', $value));

        if (!$this->isValidIban($iban)) {
            throw MemberImportException::invalidIban($rowNumber, $value);
        }

        return $iban;
    }

    public function bic(string $value, int $rowNumber): string
    {
        $bic = strtoupper($value);

        if (preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic) !== 1) {
            throw MemberImportException::invalidBic($rowNumber, $value);
        }

        return $bic;
    }

    private function isValidIban(string $iban): bool
    {
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $iban) !== 1) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;

            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
