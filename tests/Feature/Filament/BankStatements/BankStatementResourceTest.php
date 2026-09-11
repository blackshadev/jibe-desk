<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BankStatements;

use App\Filament\Admin\Resources\BankStatements\Pages\ListBankStatements;
use App\Filament\Admin\Resources\BankStatements\Pages\ViewBankStatement;
use App\Filament\Admin\Resources\BankStatements\RelationManagers\BankStatementTransactionsRelationManager;
use App\Models\BankingTransaction;
use App\Models\BankStatement;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class BankStatementResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_list_page_is_accessible(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListBankStatements::class)
            ->assertSuccessful();
    }

    public function test_can_list_bank_statements(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();

        Livewire::test(ListBankStatements::class)
            ->assertCanSeeTableRecords([$statement]);
    }

    public function test_list_page_has_mt940_import_action(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListBankStatements::class)
            ->assertActionVisible('importMt940');
    }

    public function test_matched_percentage_is_null_for_empty_statement(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();

        static::assertNull($statement->matched_percentage);
    }

    public function test_matched_percentage_calculates_correctly(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();

        BankingTransaction::factory()
            ->forStatement($statement)
            ->create(['amount' => 100.00]);

        BankingTransaction::factory()
            ->forStatement($statement)
            ->create(['amount' => 50.00]);

        $statement->load('transactions');

        static::assertSame(0.0, $statement->matched_percentage);
    }

    public function test_view_page_is_accessible(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();

        Livewire::test(ViewBankStatement::class, ['record' => $statement->id])
            ->assertSuccessful();
    }

    public function test_view_page_shows_statement_transactions(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();
        $transaction = BankingTransaction::factory()->forStatement($statement)->create();

        Livewire::test(ViewBankStatement::class, ['record' => $statement->id])
            ->assertSuccessful();

        Livewire::test(BankStatementTransactionsRelationManager::class, [
            'ownerRecord' => $statement,
            'pageClass' => ViewBankStatement::class,
        ])
            ->assertCanSeeTableRecords([$transaction]);
    }

    public function test_transaction_row_links_to_banking_transaction_view(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();
        $transaction = BankingTransaction::factory()->forStatement($statement)->create();

        Livewire::test(BankStatementTransactionsRelationManager::class, [
            'ownerRecord' => $statement,
            'pageClass' => ViewBankStatement::class,
        ])
            ->assertSuccessful()
            ->assertSee($transaction->description);
    }
}
