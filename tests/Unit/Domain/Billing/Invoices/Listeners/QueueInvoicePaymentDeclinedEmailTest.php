<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices\Listeners;

use App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined;
use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\Jobs\SendInvoicePaymentDeclinedEmail;
use App\Domain\Billing\Invoices\Listeners\QueueInvoicePaymentDeclinedEmail;
use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Billing\Invoices\InvoiceMailRepositoryExpectation;
use Tests\Unit\Domain\Jobs\JobDispatcherExpectation;
use Tests\UnitTestCase;

final class QueueInvoicePaymentDeclinedEmailTest extends UnitTestCase
{
    private InvoiceMailRepositoryExpectation $mailRepository;
    private JobDispatcherExpectation $dispatcher;
    private QueueInvoicePaymentDeclinedEmail $listener;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mailRepository = InvoiceMailRepositoryExpectation::create();
        $this->dispatcher = JobDispatcherExpectation::create();

        $this->listener = new QueueInvoicePaymentDeclinedEmail(
            $this->mailRepository->mock,
            $this->dispatcher->mock,
        );
    }

    public function test_handle_dispatches_the_notice_job(): void
    {
        $invoiceId = InvoiceId::create(42);

        $this->mailRepository->expectsGetInvoicePaymentDeclinedMailData($invoiceId, $this->declinedMailData());
        $this->dispatcher->expectsDispatch(new SendInvoicePaymentDeclinedEmail($invoiceId));

        $this->listener->handle(new InvoicePaymentDeclined($invoiceId));
    }

    public function test_handle_does_not_dispatch_when_the_invoice_cannot_be_notified(): void
    {
        $invoiceId = InvoiceId::create(42);

        $this->mailRepository->expectsGetInvoicePaymentDeclinedMailDataUnavailable(
            InvoicePaymentDeclinedMailUnavailableException::invoiceNotDeclined($invoiceId),
        );
        $this->dispatcher->expectsNoDispatch();

        $this->listener->handle(new InvoicePaymentDeclined($invoiceId));
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
