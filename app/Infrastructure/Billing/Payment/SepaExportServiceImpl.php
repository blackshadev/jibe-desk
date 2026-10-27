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

        foreach (SequenceType::cases() as $sequenceType) {
            $category = SepaCategory::forSequenceType($sequenceType);

            if (! $invoices->hasForCategory($category)) {
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

            foreach ($invoices->forCategory($category) as $invoice) {
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

        $exportablePurchaseOrders = array_values(array_filter(
            $purchaseOrders,
            static fn (SepaExportPurchaseOrder $purchaseOrder): bool => $purchaseOrder->amountInCents() !== 0,
        ));

        foreach ($exportablePurchaseOrders as $purchaseOrder) {
            $credit->addTransfer($creditPaymentInfoId, [
                'amount' => $purchaseOrder->amountInCents(),
                'creditorIban' => $purchaseOrder->creditorIban,
                'creditorName' => $purchaseOrder->creditorName,
                'endToEndId' => $purchaseOrder->reference(),
                'remittanceInformation' => $purchaseOrder->reference(),
            ]);
        }

        $hasCreditTransfer = $invoices->hasCredit() || $exportablePurchaseOrders !== [];

        return new SepaExport(
            creditTransfers: $hasCreditTransfer ? $credit->asXML() : '',
            directDebit: $invoices->hasDebit() ? $directDebit->asXML() : '',
        );
    }
}
