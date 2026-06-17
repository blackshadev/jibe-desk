<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\SepaExportInvoice;
use App\Domain\Invoices\SepaExportService;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Override;

final readonly class SepaExportServiceImpl implements SepaExportService
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
        private SepaConfiguration $configuration,
    ) {}

    #[Override]
    public function export(InvoiceBatchId $batchId): string
    {
        $dueDate = $this->batchRepository->getBatchDate($batchId);

        /** @var list<SepaExportInvoice> $invoices */
        $invoices = $this->batchRepository->getInvoicesForExport($batchId);

        $paymentInfoId = 'payment-batch-' . $batchId->value;

        $directDebit = TransferFileFacadeFactory::createDirectDebit(
            'BATCH-' . $batchId->value,
            $this->configuration->creditorName,
            $this->configuration->painFormat,
        );

        $directDebit->addPaymentInfo($paymentInfoId, [
            'id' => $paymentInfoId,
            'creditorName' => $this->configuration->creditorName,
            'creditorAccountIBAN' => $this->configuration->creditorIban,
            'creditorAgentBIC' => $this->configuration->creditorBic,
            'seqType' => PaymentInformation::S_RECURRING,
            'creditorId' => $this->configuration->creditorId,
            'batchBooking' => true,
            'dueDate' => $dueDate,
        ]);

        foreach ($invoices as $invoice) {
            $directDebit->addTransfer($paymentInfoId, [
                'amount' => $invoice->amountInCents(),
                'debtorIban' => $invoice->iban,
                'debtorName' => $invoice->recipientName,
                'debtorBic' => $invoice->bic,
                'debtorMandate' => $invoice->mandateId->value,
                'debtorMandateSignDate' => $invoice->mandateDate,
                'endToEndId' => $invoice->invoiceNumber,
                'remittanceInformation' => $invoice->invoiceNumber,
            ]);
        }

        return $directDebit->asXML();
    }
}
