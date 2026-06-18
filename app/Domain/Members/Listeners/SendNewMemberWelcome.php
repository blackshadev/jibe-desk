<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Members\Events\NewMemberRegistration;
use App\Mail\Registration\NewMemberWelcome;
use Illuminate\Support\Facades\Mail;

final readonly class SendNewMemberWelcome
{
    public function handle(NewMemberRegistration $event): void
    {
        Mail::to($event->memberEmail)
            ->send(new NewMemberWelcome(
                memberName: $event->memberName,
            ));
    }
}
