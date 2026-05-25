<?php

declare(strict_types=1);

namespace App\Domain\Members;

use Webmozart\Assert\Assert;

final class MemberIdList
{
    /** @param MemberId[] $ids */
    public function __construct(public array $ids)
    {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, MemberId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(
            array_map(
                static fn (int $id) => MemberId::create($id),
                $array
            )
        );
    }
}
