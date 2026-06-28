<?php

declare(strict_types=1);

namespace App\Domain\Mail;

final readonly class Related
{
    /** @param class-string<\Illuminate\Database\Eloquent\Model> $class */
    public function __construct(
        public string $class,
        public int $id,
    ) {}
}
