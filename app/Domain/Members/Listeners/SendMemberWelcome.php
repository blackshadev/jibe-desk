<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\MemberAccountProvisioned;
use App\Domain\Members\Mails\MemberWelcome;
use App\Domain\Members\MemberPasswordResetLink;
/** @phpstan-ignore domain.dependency */
use Filament\Facades\Filament;

final readonly class SendMemberWelcome
{
    public function __construct(
        private MailSender $mailSender,
        private MemberPasswordResetLink $memberPasswordResetLink,
    ) {}

    public function handle(MemberAccountProvisioned $event): void
    {
        $setPasswordUrl = $this->memberPasswordResetLink->generate($event->memberId);

        $loginUrl = Filament::getLoginUrl() ?? Filament::getUrl() ?? '/admin/login';

        $this->mailSender->send(new MemberWelcome(
            recipient: new Recipient(name: $event->memberName, email: $event->memberEmail),
            setPasswordUrl: $setPasswordUrl,
            loginUrl: $loginUrl,
        ));
    }
}
