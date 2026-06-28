<?php

declare(strict_types=1);

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchEmailData;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailLine;
use App\Domain\Invoices\Mails\InvoiceBatchCreatedMail;
use App\Domain\Invoices\Mails\InvoiceMail;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Mail\Recipient;
use App\Domain\Members\MemberId;
use App\Domain\Registration\Mails\NewMemberAdminNotification;
use App\Domain\Registration\Mails\NewMemberWelcome;
use App\Domain\Registration\MembershipData;
use App\Infrastructure\Mail\MailMailable;
use Illuminate\Contracts\Mail\Mailable;
use Xammie\Mailbook\Facades\Mailbook;

Mailbook::add(NewMemberAdminNotification::class)
    ->variant('New member admin notification', static fn (): Mailable =>
        new MailMailable(new NewMemberAdminNotification(
            MemberId::create(1),
            "Jan de Vries",
            new MembershipData(
                true,
                false,
                true,
                true,
                "123"
            ),
            new Recipient("admin", "admin@admin.nl"),
        ))
    );

Mailbook::add(NewMemberWelcome::class)
    ->variant('New member welcome', static fn (): Mailable =>
        new MailMailable(new NewMemberWelcome(
            new Recipient("Jan de Vries", "jan@devries.nl"),
        ))
    );

Mailbook::add(InvoiceMail::class)
    ->variant('With SEPA transfer', static function (SepaConfiguration $config): Mailable {
        $data = new InvoiceMailData(
            invoiceId: 2,
            invoiceNumber: '1234',
            recipient: new Recipient('Jan de Vries', 'jan@devries.nl'),
            recipientIban: 'NL12ABCD1234567890',
            recipientAddress: "Kerkstraat 12\n1111 AA Amtersdam",
            invoiceDate: new DateTimeImmutable('2024-01-01'),
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
            sepaTransferDate: new DateTimeImmutable('2024-01-02')
        );

        return new MailMailable(new InvoiceMail($data, $config));
    })
    ->variant('Without SEPA transfer', static function (SepaConfiguration $config): Mailable {
        $data = new InvoiceMailData(
            invoiceId: 2,
            invoiceNumber: '1234',
            recipient: new Recipient('Jan de Vries', 'jan@devries.nl'),
            recipientIban: 'NL12ABCD1234567890',
            recipientAddress: "Kerkstraat 12\n1111 AA Amtersdam",
            invoiceDate: new DateTimeImmutable('2024-01-01'),
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
            sepaTransferDate: null
        );

        return new MailMailable(new InvoiceMail($data, $config));
    })
    ->variant('Credit', static function (SepaConfiguration $config): Mailable {
        $data = new InvoiceMailData(
            invoiceId: 2,
            invoiceNumber: '1234',
            recipient: new Recipient('Jan de Vries', 'jan@devries.nl'),
            recipientIban: 'NL12ABCD1234567890',
            recipientAddress: "Kerkstraat 12\n1111 AA Amtersdam",
            invoiceDate: new DateTimeImmutable('2024-01-01'),
            total: new CompoundPrice(-120, -25.2),
            lines: [
                new InvoiceMailLine(
                    description: 'Lidmaatschap 2024',
                    quantity: 1,
                    price: new CompoundPrice(-100, -21),
                    subTotal: new CompoundPrice(-100, -21),
                ),

                new InvoiceMailLine(
                    description: 'Lessen 2024',
                    quantity: 2,
                    price: new CompoundPrice(-10, -2.1),
                    subTotal: new CompoundPrice(-20, -4.2),
                ),
            ],
            sepaTransferDate: null
        );

        return new MailMailable(new InvoiceMail($data, $config));
    });

Mailbook::add(InvoiceBatchCreatedMail::class)
    ->variant('Invoice batch created', static function (): Mailable {
        return new MailMailable(new InvoiceBatchCreatedMail(
            new InvoiceBatchEmailData(
                id: InvoiceBatchId::create(1),
                invoiceDate: new DateTimeImmutable('2026-06-15'),
                invoiceCount: 12,
                total: new CompoundPrice(1500.00, 315.00),
            ),
            new Recipient('Financiële administratie', 'financieel@domain.nl')
        ));
    });
