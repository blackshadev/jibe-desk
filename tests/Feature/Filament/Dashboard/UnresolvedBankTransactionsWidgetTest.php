<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionStatus;
use App\Domain\Bookkeeping\Banking\BankTransactions\ResolveStatus;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\UnresolvedBankTransactionsWidget;
use App\Models\BankTransaction;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class UnresolvedBankTransactionsWidgetTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_it_counts_unresolved_open_transactions_and_sums_amounts(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        BankTransaction::factory()->createQuietly([
            'status' => BankTransactionStatus::Open,
            'resolve_status' => ResolveStatus::Unresolved,
            'amount' => 100.0,
        ]);
        BankTransaction::factory()->createQuietly([
            'status' => BankTransactionStatus::Open,
            'resolve_status' => ResolveStatus::Unresolved,
            'amount' => 50.0,
        ]);
        BankTransaction::factory()->createQuietly([
            'status' => BankTransactionStatus::Open,
            'resolve_status' => ResolveStatus::Resolved,
            'amount' => 999.0,
        ]);
        BankTransaction::factory()->createQuietly([
            'status' => BankTransactionStatus::Completed,
            'resolve_status' => ResolveStatus::Unresolved,
            'amount' => 999.0,
        ]);

        Livewire::test(UnresolvedBankTransactionsWidget::class)
            ->assertSee('2')
            ->assertSee(PriceFormatter::format(150.0));
    }

    public function test_it_is_only_visible_for_financial_administration(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        static::assertTrue(UnresolvedBankTransactionsWidget::canView());

        $this->withUserHavingRole(RoleName::MemberAdministration);
        static::assertFalse(UnresolvedBankTransactionsWidget::canView());
    }
}
