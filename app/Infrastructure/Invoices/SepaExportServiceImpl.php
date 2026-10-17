<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchRepository;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Invoices\SepaExport;
use App\Domain\Invoices\SepaExportInvoice;
use App\Domain\Invoices\SepaExportService;
use App\Domain\PurchaseOrders\SepaExportPurchaseOrder;
use Digitick\Sepa\Exception\InvalidArgumentException;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use DOMException;
use Override;

final readonly class SepaExportServiceImpl implements SepaExportService
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private SepaConfiguration $configuration,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws DOMException
     */
    #[Override]
    public function export(SepaBatchId $batchId): SepaExport
    {
        $dueDate = $this->batchRepository->getSepaTransferDate($batchId);

        /** @var list<SepaExportInvoice> $invoices */
        $invoices = $this->batchRepository->getInvoicesForExport($batchId);

        /** @var list<SepaExportPurchaseOrder> $purchaseOrders */
        $purchaseOrders = $this->batchRepository->getPurchaseOrdersForExport($batchId);

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

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->amountInCents() === 0) {
                continue;
            }

            $hasCreditTransfer = true;
            $credit->addTransfer($paymentInfoId, [
                'amount' => $purchaseOrder->amountInCents(),
                'creditorIban' => $purchaseOrder->creditorIban,
                'creditorName' => $purchaseOrder->creditorName,
                'endToEndId' => $purchaseOrder->reference(),
                'remittanceInformation' => $purchaseOrder->reference(),
            ]);
        }

        return new SepaExport(
            creditTransfers: $hasCreditTransfer ? $credit->asXML() : '',
            directDebit: $hasDirectDebit ? $directDebit->asXML() : '',
        );
    }
}
