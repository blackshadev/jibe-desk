<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices\Jobs;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\Jobs\SendInvoicePaymentDeclinedEmail;
use App\Domain\Billing\Invoices\Mails\InvoicePaymentDeclinedMail;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Billing\Invoices\InvoiceMailRepositoryExpectation;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendInvoicePaymentDeclinedEmailTest extends UnitTestCase
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
            noticePeriodDays: 14,
        );
    }

    public function test_handle_sends_the_declined_notice(): void
    {
        $invoiceId = InvoiceId::create(42);
        $mailData = $this->declinedMailData();

        $this->repository->expectsGetInvoicePaymentDeclinedMailData($invoiceId, $mailData);
        $this->mailSender->expectsSend(new InvoicePaymentDeclinedMail($mailData, $this->configuration));

        $job = new SendInvoicePaymentDeclinedEmail($invoiceId);

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);
    }

    public function test_handle_does_nothing_when_the_invoice_is_no_longer_declinable(): void
    {
        $invoiceId = InvoiceId::create(42);

        $this->repository->expectsGetInvoicePaymentDeclinedMailDataUnavailable(
            InvoicePaymentDeclinedMailUnavailableException::invoiceNotDeclined($invoiceId),
        );

        $job = new SendInvoicePaymentDeclinedEmail($invoiceId);

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);

        $this->mailSender->mock->shouldNotHaveReceived('send');
    }

    public function test_handle_does_nothing_when_the_batch_is_cancelled(): void
    {
        $invoiceId = InvoiceId::create(42);

        $job = new SendInvoicePaymentDeclinedEmail($invoiceId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->repository->mock, $this->configuration, $this->mailSender->mock);

        $this->repository->mock->shouldNotHaveReceived('getInvoicePaymentDeclinedMailData');
        $this->mailSender->mock->shouldNotHaveReceived('send');
    }

    private function declinedMailData(): InvoicePaymentDeclinedMailData
    {
        return new InvoicePaymentDeclinedMailData(
            invoiceId: InvoiceId::create(42),
            invoiceNumber: 'INV-2026-001',
            recipient: new Recipient('Vries, Jan de', 'jan@example.com'),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            total: new CompoundPrice(100.0, 21.0),
            mandateNeedsReauthorisation: false,
        );
    }
}
