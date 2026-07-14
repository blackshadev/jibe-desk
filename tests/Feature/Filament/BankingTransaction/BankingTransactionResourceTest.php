<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BankingTransaction;

use App\Filament\Admin\Resources\BankingTransactions\Pages\CreateBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\EditBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ListBankingTransactions;
use App\Models\BankingTransaction;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class BankingTransactionResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_list_page_is_accessible(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListBankingTransactions::class)
            ->assertSuccessful();
    }

    public function test_can_list_banking_transactions(): void
    {
        $this->withAuthorizedUser();

        BankingTransaction::factory()->create(['description' => 'Payment from John']);
        BankingTransaction::factory()->create(['description' => 'Invoice payment']);

        Livewire::test(ListBankingTransactions::class)
            ->assertCanSeeTableRecords(BankingTransaction::all());
    }

    public function test_can_create_banking_transaction(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateBankingTransaction::class)
            ->fillForm([
                'date' => '2024-01-15',
                'description' => 'Test payment',
                'amount' => 100.50,
                'banking_account_number' => 'NL91ABNA0417164300',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('banking_transactions', [
            'description' => 'Test payment',
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);
    }
}
