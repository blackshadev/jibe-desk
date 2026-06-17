<?php

declare(strict_types=1);

namespace App\Domain\Mail;

use Ramsey\Uuid\Uuid;
use Webmozart\Assert\Assert;

final readonly class TrackingId
{
    private function __construct(
        public string $value,
    ) {
        Assert::uuid($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }
}
