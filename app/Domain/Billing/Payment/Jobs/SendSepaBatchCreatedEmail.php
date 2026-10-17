<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Jobs;

use App\Domain\Billing\Payment\Mails\SepaBatchCreatedMail;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchRepository;
use App\Domain\Jobs\BaseJob;
use App\Domain\Mail\FinancialAdministrationRecipient;
use App\Domain\Mail\MailSender;

final class SendSepaBatchCreatedEmail extends BaseJob
{
    public function __construct(
        private readonly SepaBatchId $sepaBatchId,
    ) {}

    public function handle(SepaBatchRepository $batchRepository, MailSender $mailSender, FinancialAdministrationRecipient $admin): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $batchRepository->getBatchEmailData($this->sepaBatchId);

        $mailSender->send(new SepaBatchCreatedMail($mailData, $admin->recipient));
    }
}
