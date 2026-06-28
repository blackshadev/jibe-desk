<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Jobs;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Invoices\Mails\InvoiceMail;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Mail\Recipient;
use Carbon\CarbonImmutable;
use Tests\Unit\Domain\Invoices\InvoiceMailRepositoryExpectation;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendInvoiceEmailTest extends UnitTestCase
{
    private InvoiceMailRepositoryExpectation $repository;
    private MailSenderExpectation $mailSender;
    private SepaConfiguration $configuration;

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

    public function test_handle_sends_invoice_mail(): void
    {
        $invoiceId = InvoiceId::create(42);
        $mailData = new InvoiceMailData(
            invoiceId: 42,
            invoiceNumber: 'INV-2026-001',
            recipient: new Recipient('Vries, Jan de', 'jan@example.com'),
            recipientIban: 'NL91ABNA0417164300',
            recipientAddress: 'Surfstrand 2, 1324CT Almere',
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            total: new CompoundPrice(100.0, 21.0),
            lines: [],
            sepaTransferDate: CarbonImmutable::parse('2026-06-01'),
        );

        $this->repository->expectsGetInvoiceMailData($invoiceId, $mailData);
        $this->mailSender->expectsSend(new InvoiceMail($mailData, $this->configuration));

        $job = new SendInvoiceEmail($invoiceId);

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $invoiceId = InvoiceId::create(42);

        $job = new SendInvoiceEmail($invoiceId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);

        $this->repository->mock->shouldNotHaveReceived('getInvoiceMailData');
        $this->mailSender->mock->shouldNotHaveReceived('send');
    }

    public function test_middleware_returns_empty_array(): void
    {
        $job = new SendInvoiceEmail(InvoiceId::create(1));

        static::assertSame([], $job->middleware());
    }
}
