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

trait TestsBankingLifecycle
{
    /**
     * Create an invoice with explicit lines and return it.
     *
     * @param list<array{description: string, price: float, quantity?: float, vat?: float, cost_center_id?: int}> $lines
     * @param array<string, mixed>                                                                                  $overrides
     */
    protected function createInvoice(Member $member, array $lines, array $overrides = []): Invoice
    {
        $factory = Invoice::factory()->forMember($member);

        foreach ($lines as $line) {
            $factory = $factory->has(
                InvoiceLine::factory()->state([
                    'description' => $line['description'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'] ?? 1,
                    'vat' => $line['vat'] ?? $line['price'] * 0.21,
                    'cost_center_id' => $line['cost_center_id'] ?? CostCenter::query()->firstOrFail()->id,
                ]),
                'lines',
            );
        }

        return $factory->create([
            'date' => now()->format('Y-m-d'),
            'status' => InvoiceStatus::Open,
            ...$overrides,
        ]);
    }

    /**
     * Create a purchase order through the admin create page and return it.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createPurchaseOrder(array $overrides = []): PurchaseOrder
    {
        $data = array_merge([
            'creditor_name' => 'Sloepenhandel BV',
            'creditor_iban' => 'NL02INGB0002447973',
            'description' => 'Inkoop bootmateriaal',
            'date' => now()->format('Y-m-d'),
            'image_path' => UploadedFile::fake()->image('factuur.jpg'),
            'lines' => [
                [
                    'description' => 'Vlot',
                    'price' => 150.00,
                    'price_vat' => 31.50,
                    'cost_center_id' => CostCenter::query()->firstOrFail()->id,
                ],
            ],
        ], $overrides);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        return PurchaseOrder::query()
            ->where('description', $data['description'])
            ->orderByDesc('id')
            ->firstOrFail();
    }

    protected function approvePurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
            ->callAction('markAsApproved');
    }

    protected function markInvoiceAsPending(Invoice $invoice): void
    {
        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('markAsPending');
    }


    protected function completeTransaction(BankTransaction $transaction): void
    {
        Livewire::test(ViewBankTransaction::class, ['record' => $transaction->getRouteKey()])
            ->callAction('complete');
    }
}
