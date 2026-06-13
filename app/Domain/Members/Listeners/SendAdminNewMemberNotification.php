<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Members\Events\NewMemberRegistration;
use App\Mail\NewMemberAdminNotification;
use Illuminate\Support\Facades\Mail;

final readonly class SendAdminNewMemberNotification
{
    public function handle(NewMemberRegistration $event): void
    {
        Mail::to(config('mail.admin.address'))
            ->send(new NewMemberAdminNotification(
                memberId: $event->memberId,
                memberName: $event->memberName,
                membershipData: $event->membershipData,
            ));
    }
}
