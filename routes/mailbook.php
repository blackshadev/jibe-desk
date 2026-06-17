<?php

declare(strict_types=1);

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailLine;
use App\Domain\Invoices\InvoiceMailRepositoryDb;
use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use App\Mail\InvoiceMail;
use App\Mail\NewMemberAdminNotification;
use App\Mail\NewMemberWelcome;
use Xammie\Mailbook\Facades\Mailbook;

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
Mailbook::add(static function (): InvoiceMail {
    $data = new InvoiceMailData(
        invoiceId: 2,
        invoiceNumber: '1234',
        memberName: 'Jan de Vries',
        memberEmail: 'jan@devries.nl',
        invoiceDate: '2024-01-01',
        total: new CompoundPrice(120, 25.2),
        lines: [
            new InvoiceMailLine(
                description: 'Lidmaatschap 2024',
                quantity: 1,
                price: new CompoundPrice(100, 21),
                subTotal: new CompoundPrice(100, 21),
            ),

            new InvoiceMailLine(
                description: 'Lessen 2024',
                quantity: 2,
                price: new CompoundPrice(10, 2.1),
                subTotal: new CompoundPrice(20, 4.2),
            ),
        ],
        sepaTransferDate: '2024-01-02'
    );

    return new InvoiceMail($data);
});
