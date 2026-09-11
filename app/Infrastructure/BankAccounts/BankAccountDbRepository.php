<?php

declare(strict_types=1);

namespace App\Infrastructure\BankAccounts;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankAccounts\BankAccountRepository;
use App\Domain\BankTransactions\UnknownBankAccountException;
use App\Models\BankAccount;
use App\Models\BankAccountYearBalance;
use Override;

final readonly class BankAccountDbRepository implements BankAccountRepository
{
    #[Override]
    public function getByIban(string $iban): BankAccountId
    {
        $account = BankAccount::query()
            ->where('iban', $iban)
            ->first();

        if ($account === null) {
            throw new UnknownBankAccountException($iban);
        }

        return BankAccountId::create($account->id);
    }

    #[Override]
    public function getOwnAccounts(): array
    {
        return BankAccount::query()
            ->where('active', true)
            ->pluck('iban')
            ->all();
    }

    #[Override]
    public function getOpeningAmount(BankAccountId $id, int $year): ?float
    {
        $opening = BankAccountYearBalance::query()
            ->where('bank_account_id', $id->value)
            ->where('year', $year)
            ->value('opening_amount');

        return $opening === null ? null : (float) $opening;
    }
}
