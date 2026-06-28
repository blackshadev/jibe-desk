<?php

declare(strict_types=1);

namespace App\Domain\Mail;

use Illuminate\Mail\Mailables\Content;

abstract readonly class BaseMail
{
    public function related(): ?Related
    {
        return null;
    }

    abstract public function subject(): string;

    abstract public function to(): Recipient;

    abstract public function content(): Content;
}
