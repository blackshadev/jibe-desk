<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Jobs;

use App\Domain\Billing\Payment\Jobs\SendSepaBatchCreatedEmail;
use App\Domain\Billing\Payment\Mails\SepaBatchCreatedMail;
use App\Domain\Billing\Payment\SepaBatchEmailData;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Mail\FinancialAdministrationRecipient;
use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Billing\Payment\SepaBatchRepositoryExpectation;
use Tests\Unit\Domain\Mail\MailSenderExpectation;
use Tests\UnitTestCase;

final class SendSepaBatchCreatedEmailTest extends UnitTestCase
{
    private SepaBatchRepositoryExpectation $batchRepository;
    private MailSenderExpectation $mailSender;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->batchRepository = SepaBatchRepositoryExpectation::create();
        $this->mailSender = MailSenderExpectation::create();
    }

    public function test_handle_sends_sepa_batch_created_mail(): void
    {
        $batchId = SepaBatchId::create(10);
        $batchData = new SepaBatchEmailData(
            id: $batchId,
            invoiceDate: CarbonImmutable::parse('2026-06-15'),
            invoiceCount: 5,
            total: new CompoundPrice(250.0, 52.5),
        );
        $recipient = new Recipient('Admin', 'admin@example.com');
        $admin = new FinancialAdministrationRecipient($recipient);

        $this->batchRepository->expectsGetBatchEmailData($batchId, $batchData);
        $this->mailSender->expectsSend(new SepaBatchCreatedMail($batchData, $admin->recipient));

        $job = new SendSepaBatchCreatedEmail($batchId);

        $job->handle($this->batchRepository->mock, $this->mailSender->mock, $admin);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $batchId = SepaBatchId::create(10);
        $admin = new FinancialAdministrationRecipient(
            new Recipient('Admin', 'admin@example.com'),
        );

        $job = new SendSepaBatchCreatedEmail($batchId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->batchRepository->mock, $this->mailSender->mock, $admin);

        $this->batchRepository->mock->shouldNotHaveReceived('getBatchEmailData');
        $this->mailSender->mock->shouldNotHaveReceived('send');
    }
}
