<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchRepository;
use App\Domain\Billing\Payment\SepaCategory;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Billing\Payment\SepaExport;
use App\Domain\Billing\Payment\SepaExportPurchaseOrder;
use App\Domain\Billing\Payment\SepaExportService;
use App\Domain\Billing\Payment\SequenceType;
use Digitick\Sepa\Exception\InvalidArgumentException;
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
        $invoices = $this->batchRepository->getInvoicesForExport($batchId);

        /** @var list<SepaExportPurchaseOrder> $purchaseOrders */
        $purchaseOrders = $this->batchRepository->getPurchaseOrdersForExport($batchId);

        $creditPaymentInfoId = 'payment-batch-' . $batchId->value;
        $uniqueMessageId = 'BATCH-' . $batchId->value;

        $credit = TransferFileFacadeFactory::createCustomerCredit(
            $uniqueMessageId,
            $this->configuration->creditorName,
        );

        $credit->addPaymentInfo($creditPaymentInfoId, [
            'id' => $creditPaymentInfoId,
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

        $hasCreditTransfer = $invoices->has(SepaCategory::Credit);
        $hasDirectDebit = $invoices->has(SepaCategory::DebitFirst) || $invoices->has(SepaCategory::DebitRecurring);

        foreach ($invoices->forCategory(SepaCategory::Credit) as $invoice) {
            $credit->addTransfer($creditPaymentInfoId, [
                'amount' => abs($invoice->amountInCents()),
                'creditorIban' => $invoice->iban,
                'creditorName' => $invoice->recipientName,
                'creditorBic' => $invoice->bic,
                'endToEndId' => $invoice->invoiceNumber,
                'remittanceInformation' => $invoice->invoiceNumber,
            ]);
        }

        foreach (SequenceType::cases() as $sequenceType) {
            $debits = $invoices->forCategory(SepaCategory::forSequenceType($sequenceType));

            if ($debits === []) {
                continue;
            }

            $directDebitPaymentInfoId = sprintf('BATCH-%d-%s', $batchId->value, $sequenceType->value);

            $directDebit->addPaymentInfo($directDebitPaymentInfoId, [
                'id' => $directDebitPaymentInfoId,
                'creditorName' => $this->configuration->creditorName,
                'creditorAccountIBAN' => $this->configuration->creditorIban,
                'creditorAgentBIC' => $this->configuration->creditorBic,
                'seqType' => $sequenceType->value,
                'creditorId' => $this->configuration->creditorId,
                'batchBooking' => true,
                'dueDate' => $dueDate,
            ]);

            foreach ($debits as $invoice) {
                $directDebit->addTransfer($directDebitPaymentInfoId, [
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
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->amountInCents() === 0) {
                continue;
            }

            $hasCreditTransfer = true;
            $credit->addTransfer($creditPaymentInfoId, [
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
