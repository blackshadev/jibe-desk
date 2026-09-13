<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BankStatements;

use App\Domain\BankStatements\StatementChainStatus;
use App\Filament\Admin\Resources\BankStatements\Pages\ListBankStatements;
use App\Filament\Admin\Resources\BankStatements\Pages\ViewBankStatement;
use App\Filament\Admin\Resources\BankStatements\RelationManagers\BankStatementTransactionsRelationManager;
use App\Models\BankAccount;
use App\Models\BankingTransaction;
use App\Models\BankStatement;
use Filament\Actions\Testing\TestAction;
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

    public function test_view_page_has_determine_chain_status_action(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();

        Livewire::test(ViewBankStatement::class, ['record' => $statement->id])
            ->assertActionVisible('determineChainStatus');
    }

    public function test_list_page_has_determine_chain_status_record_action(): void
    {
        $this->withAuthorizedUser();

        $statement = BankStatement::factory()->create();

        Livewire::test(ListBankStatements::class)
            ->assertActionVisible(TestAction::make('determineChainStatus')->table($statement));
    }

    public function test_can_determine_chain_status_from_view_page(): void
    {
        $this->withAuthorizedUser();

        $account = BankAccount::factory()->create();
        BankStatement::factory()->for($account, 'bankAccount')->create([
            'start_date' => '2023-01-01',
            'closing_balance' => 1000.00,
        ]);
        $statement = BankStatement::factory()->for($account, 'bankAccount')->create([
            'start_date' => '2023-01-02',
            'opening_balance' => 1000.00,
            'chain_status' => StatementChainStatus::Broken,
        ]);

        Livewire::test(ViewBankStatement::class, ['record' => $statement->id])
            ->callAction('determineChainStatus');

        $statement->refresh();

        static::assertSame(StatementChainStatus::Ok, $statement->chain_status);
    }

    public function test_can_determine_chain_status_from_table_record_action(): void
    {
        $this->withAuthorizedUser();

        $account = BankAccount::factory()->create();
        BankStatement::factory()->for($account, 'bankAccount')->create([
            'start_date' => '2023-01-01',
            'closing_balance' => 900.00,
        ]);
        $statement = BankStatement::factory()->for($account, 'bankAccount')->create([
            'start_date' => '2023-01-02',
            'opening_balance' => 1000.00,
            'chain_status' => StatementChainStatus::Ok,
        ]);

        Livewire::test(ListBankStatements::class)
            ->callAction(TestAction::make('determineChainStatus')->table($statement));

        $statement->refresh();

        static::assertSame(StatementChainStatus::Broken, $statement->chain_status);
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
