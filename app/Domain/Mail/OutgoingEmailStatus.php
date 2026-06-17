<?php

declare(strict_types=1);

namespace App\Domain\Mail;

enum OutgoingEmailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
