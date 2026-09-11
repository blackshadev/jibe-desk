<?php

declare(strict_types=1);

namespace App\Domain\BankAccounts;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankAccountRepository
{
    public function getByIban(string $iban): BankAccountId;

    /** @return list<string> */
    public function getOwnAccounts(): array;

    public function getOpeningAmount(BankAccountId $id, int $year): ?float;
}
