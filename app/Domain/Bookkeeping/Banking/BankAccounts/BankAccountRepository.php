<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankAccounts;

use App\Domain\Bookkeeping\Banking\BankTransactions\UnknownBankAccountException;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankAccountRepository
{
    /** @throws UnknownBankAccountException */
    public function getByIban(string $iban): BankAccountId;

    /** @return list<string> */
    public function getOwnAccounts(): array;

    public function getOpeningAmount(BankAccountId $id, int $year): ?float;
}
