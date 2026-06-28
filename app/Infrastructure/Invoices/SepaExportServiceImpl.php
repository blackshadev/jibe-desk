<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Invoices\SepaExport;
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
    public function export(InvoiceBatchId $batchId): SepaExport
    {
        $dueDate = $this->batchRepository->getBatchDate($batchId);

        /** @var list<SepaExportInvoice> $invoices */
        $invoices = $this->batchRepository->getInvoicesForExport($batchId);

        $paymentInfoId = 'payment-batch-' . $batchId->value;
        $uniqueMessageId = 'BATCH-' . $batchId->value;

        $credit = TransferFileFacadeFactory::createCustomerCredit(
            $uniqueMessageId,
            $this->configuration->creditorName,
        );

        $credit->addPaymentInfo($paymentInfoId, [
            'id' => $paymentInfoId,
            'debtorName' => $this->configuration->creditorName,
            'debtorAccountIBAN' => $this->configuration->creditorIban,
            'debtorAgentBIC' => $this->configuration->creditorBic,
            'batchBooking' => true,
            'dueDate' => $dueDate,
        ]);

        $directDebit = TransferFileFacadeFactory::createDirectDebit(
            $uniqueMessageId,
            $this->configuration->creditorName,
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

        $hasCreditTransfer = false;
        $hasDirectDebit = false;
        foreach ($invoices as $invoice) {
            if ($invoice->amountInCents() === 0) {
                continue;
            }

            if ($invoice->amountInCents() > 0) {
                $hasDirectDebit = true;
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

            if ($invoice->amountInCents() < 0) {
                $hasCreditTransfer = true;
                $credit->addTransfer($paymentInfoId, [
                    'amount' => abs($invoice->amountInCents()),
                    'creditorIban' => $invoice->iban,
                    'creditorName' => $invoice->recipientName,
                    'creditorBic' => $invoice->bic,
                    'endToEndId' => $invoice->invoiceNumber,
                    'remittanceInformation' => $invoice->invoiceNumber,
                ]);
            }
        }

        return new SepaExport(
            creditTransfers: $hasCreditTransfer ? $credit->asXML() : '',
            directDebit: $hasDirectDebit ? $directDebit->asXML() : '',
        );
    }
}
