<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\TrackingId;
use App\Domain\Mail\TrackingIdGenerator;
use Override;

final readonly class TrackingIdGeneratorImpl implements TrackingIdGenerator
{
    #[Override]
    public function generate(): TrackingId
    {
        return TrackingId::generate();
    }
}
