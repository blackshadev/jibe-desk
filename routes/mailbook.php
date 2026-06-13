<?php

declare(strict_types=1);

use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use App\Mail\NewMemberAdminNotification;
use App\Mail\MailbookMail;
use App\Mail\NewMemberWelcome;
use Xammie\Mailbook\Facades\Mailbook;

Mailbook::add(MailbookMail::class);
Mailbook::add(static fn (): NewMemberAdminNotification =>
    new NewMemberAdminNotification(
        MemberId::create(1),
        "Jan de Vries",
        new MembershipData(
            true,
            false,
            true,
            true,
            "123"
        )
    )
);
Mailbook::add(static fn (): NewMemberWelcome =>
    new NewMemberWelcome(
        "Jan de Vries",
    )
);
