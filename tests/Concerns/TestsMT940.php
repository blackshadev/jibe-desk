<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Filament\Admin\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Admin\Resources\BankTransactions\Pages\ViewBankTransaction;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Domain\Invoices\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

trait TestsMT940
{
    /**
     * Build the contents of a single-statement MT940 file (Rabo format).
     *
     * @param list<array{date: string, debitCredit: 'C'|'D', amount: float, counterpartyIban: string, description: string}> $transactions
     */
    protected function mt940Statement(BankAccount $bankAccount, array $transactions, float $openingBalance = 1000.00): string
    {
        $statementDate = CarbonImmutable::parse($transactions[0]['date'])->format('ymd');

        $lines = [
            ':940:',
            ':20:WSV-SCENARIO-001',
            sprintf(':25:%s', $bankAccount->iban),
            ':28C:1/1',
            sprintf(':60F:C%sEUR%s', $statementDate, self::formatMt940Amount($openingBalance)),
        ];

        foreach ($transactions as $index => $transaction) {
            $lines[] = sprintf(
                ':61:%s%s%sNMSC//%03d',
                CarbonImmutable::parse($transaction['date'])->format('ymd'),
                $transaction['debitCredit'],
                self::formatMt940Amount($transaction['amount']),
                $index + 1,
            );
            // The line below :61: is parsed as the counterparty account (Rabo engine).
            $lines[] = $transaction['counterpartyIban'];
            $lines[] = sprintf(':86:/NAME/Scenario/REMI/%s', $transaction['description']);
        }

        $closingBalance = $openingBalance;
        foreach ($transactions as $transaction) {
            $closingBalance += $transaction['debitCredit'] === 'C' ? $transaction['amount'] : -$transaction['amount'];
        }

        $lines[] = sprintf(':62F:C%sEUR%s', $statementDate, self::formatMt940Amount($closingBalance));
        $lines[] = '-';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Import an MT940 statement through the admin import action.
     *
     * The action dispatches MatchBankTransactionsJob, which runs inline (sync queue).
     */
    protected function importMt940Statement(string $content): void
    {
        Livewire::test(ListBankTransactions::class)
            ->callAction('importMt940', data: [
                'mt940_file' => UploadedFile::fake()->createWithContent('statement.mta', $content),
            ])
            ->assertHasNoActionErrors();
    }

    private static function formatMt940Amount(float $amount): string
    {
        return str_replace('.', ',', sprintf('%.2f', $amount));
    }
}
