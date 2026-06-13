<?php

declare(strict_types=1);

namespace App\Domain\Members;

final class MemberNameFormatter
{
    /** @return non-falsy-string */
    public static function displayName(string $firstName, ?string $infixName, string $lastName): string
    {
        $firstName = empty($infixName) ? $firstName : "{$firstName} {$infixName}";

        return sprintf('%s, %s', $lastName, $firstName);
    }

    /** @return non-falsy-string */
    public static function presentationName(string $firstName, ?string $infixName, string $lastName): string
    {
        $firstName = empty($infixName) ? $firstName : "{$firstName} {$infixName}";

        return sprintf('%s %s', $firstName, $lastName);
    }
}
