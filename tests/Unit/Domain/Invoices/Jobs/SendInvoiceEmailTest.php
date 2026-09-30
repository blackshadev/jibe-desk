<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Jobs;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Invoices\Mails\InvoiceMail;
use App\Domain\Invoices\SendInvoiceEmailData;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Mail\Recipient;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;
use Tests\Unit\Domain\Invoices\InvoiceMailRepositoryExpectation;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendInvoiceEmailTest extends UnitTestCase
{
    private InvoiceMailRepositoryExpectation $repository;
    private MailSenderExpectation $mailSender;
    private SepaConfiguration $configuration;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->repository = InvoiceMailRepositoryExpectation::create();
        $this->mailSender = MailSenderExpectation::create();
        $this->configuration = new SepaConfiguration(
            creditorId: 'NL12ZZZ123456780000',
            creditorName: 'Watersportvereniging Almere Centraal',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
        );
    }

    public function test_handle_sends_invoice_mail_when_not_yet_sent(): void
    {
        $invoiceId = InvoiceId::create(42);
        $mailData = $this->invoiceMailData();

        $this->repository->expectsGetInvoiceMailData($invoiceId, $mailData);
        $this->mailSender->expectsSend(new InvoiceMail($mailData, $this->configuration));
        $this->repository->expectsMarkInvoiceAsSent($invoiceId);

        $job = new SendInvoiceEmail(new SendInvoiceEmailData($invoiceId));

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);
    }

    public function test_handle_does_not_send_when_already_sent(): void
    {
        $invoiceId = InvoiceId::create(42);
        $mailData = $this->invoiceMailData(sentAt: CarbonImmutable::parse('2026-05-26'));

        $this->repository->expectsGetInvoiceMailData($invoiceId, $mailData);

        $job = new SendInvoiceEmail(new SendInvoiceEmailData($invoiceId));

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);

        $this->mailSender->mock->shouldNotHaveReceived('send');
        $this->repository->mock->shouldNotHaveReceived('markInvoiceAsSent');
    }

    public function test_handle_sends_when_resent_even_if_already_sent(): void
    {
        $invoiceId = InvoiceId::create(42);
        $mailData = $this->invoiceMailData(sentAt: CarbonImmutable::parse('2026-05-26'));

        $this->repository->expectsGetInvoiceMailData($invoiceId, $mailData);
        $this->mailSender->expectsSend(new InvoiceMail($mailData, $this->configuration));
        $this->repository->expectsMarkInvoiceAsSent($invoiceId);

        $job = new SendInvoiceEmail(new SendInvoiceEmailData($invoiceId, isResend: true));

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $invoiceId = InvoiceId::create(42);

        $job = new SendInvoiceEmail(new SendInvoiceEmailData($invoiceId));
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);

        $this->repository->mock->shouldNotHaveReceived('getInvoiceMailData');
        $this->mailSender->mock->shouldNotHaveReceived('send');
    }

    private function invoiceMailData(?DateTimeInterface $sentAt = null): InvoiceMailData
    {
        return new InvoiceMailData(
            invoiceId: 42,
            invoiceNumber: 'INV-2026-001',
            recipient: new Recipient('Vries, Jan de', 'jan@example.com'),
            recipientIban: 'NL91ABNA0417164300',
            recipientAddress: 'Surfstrand 2, 1324CT Almere',
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            total: new CompoundPrice(100.0, 21.0),
            lines: [],
            sepaTransferDate: CarbonImmutable::parse('2026-06-01'),
            sentAt: $sentAt,
        );
    }
}
