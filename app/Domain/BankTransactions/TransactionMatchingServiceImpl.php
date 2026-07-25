<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceRepository;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use Override;

final readonly class TransactionMatchingServiceImpl implements TransactionMatchingService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private PurchaseOrderRepository $purchaseOrderRepository,
    ) {}

    #[Override]
    public function findMatch(MatchCriteria $criteria): MatchResult
    {
        if ($criteria->amount > 0) {
            return $this->findMatchingInvoice($criteria);
        }

        return $this->findMatchingPurchaseOrder($criteria);
    }

    private function findMatchingInvoice(MatchCriteria $criteria): MatchResult
    {
        $invoiceId = $this->invoiceRepository->findMatchingCredit(
            bankingAccountNumber: $criteria->bankingAccountNumber,
            amount: $criteria->amount,
            date: $criteria->date,
        );

        if ($invoiceId === null) {
            return MatchResult::none();
        }

        return MatchResult::foundInvoice($invoiceId);
    }

    private function findMatchingPurchaseOrder(MatchCriteria $criteria): MatchResult
    {
        $purchaseOrderId = $this->purchaseOrderRepository->findMatchingDebit(
            creditorIban: $criteria->bankingAccountNumber,
            amount: abs($criteria->amount),
            date: $criteria->date,
        );

        if ($purchaseOrderId === null) {
            return MatchResult::none();
        }

        return MatchResult::foundPurchaseOrder($purchaseOrderId);
    }
}
