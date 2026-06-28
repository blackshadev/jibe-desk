<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Jobs;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchEmailData;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\Jobs\SendInvoiceBatchCreatedEmail;
use App\Domain\Invoices\Mails\InvoiceBatchCreatedMail;
use App\Domain\Mail\FinancialAdministrationRecipient;
use App\Domain\Mail\Recipient;
use Carbon\CarbonImmutable;
use Tests\Unit\Domain\Invoices\InvoiceBatchRepositoryExpectation;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendInvoiceBatchCreatedEmailTest extends UnitTestCase
{
    private InvoiceBatchRepositoryExpectation $batchRepository;
    private MailSenderExpectation $mailSender;

    protected function setup(): void
    {
        parent::setup();

        $this->batchRepository = InvoiceBatchRepositoryExpectation::create();
        $this->mailSender = MailSenderExpectation::create();
    }

    public function test_handle_sends_invoice_batch_created_mail(): void
    {
        $batchId = InvoiceBatchId::create(10);
        $batchData = new InvoiceBatchEmailData(
            id: $batchId,
            invoiceDate: CarbonImmutable::parse('2026-06-15'),
            invoiceCount: 5,
            total: new CompoundPrice(250.0, 52.5),
        );
        $recipient = new Recipient('Admin', 'admin@example.com');
        $admin = new FinancialAdministrationRecipient($recipient);

        $this->batchRepository->expectsGetBatchEmailData($batchId, $batchData);
        $this->mailSender->expectsSend(new InvoiceBatchCreatedMail($batchData, $admin->recipient));

        $job = new SendInvoiceBatchCreatedEmail($batchId);

        $job->handle($this->batchRepository->mock, $this->mailSender->mock, $admin);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $batchId = InvoiceBatchId::create(10);
        $admin = new FinancialAdministrationRecipient(
            new Recipient('Admin', 'admin@example.com'),
        );

        $job = new SendInvoiceBatchCreatedEmail($batchId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->batchRepository->mock, $this->mailSender->mock, $admin);

        $this->batchRepository->mock->shouldNotHaveReceived('getBatchEmailData');
        $this->mailSender->mock->shouldNotHaveReceived('send');
    }
}
