<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankAccounts\BankAccountRepository;
use App\Domain\BankStatements\BankStatementRepository;
use App\Domain\BankStatements\CreateBankStatement;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use App\Domain\BankTransactions\BankTransactionImportService;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\BankTransactions\UnknownBankAccountException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Kingsquare\Banking\Statement;
use Kingsquare\Banking\Transaction;
use Kingsquare\Parser\Banking\Mt940;
use Override;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class BankTransactionImportServiceImpl implements BankTransactionImportService
{
    public function __construct(
        private BankTransactionRepository $repository,
        private BankAccountRepository $bankAccountRepository,
        private BankStatementRepository $bankStatementRepository,
    ) {}

    #[Override]
    public function importFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        Mt940::$removeIBAN = false;
        $content = file_get_contents($filePath);
        $parser = new Mt940();
        $statements = $parser->parse($content);

        $statements = $this->prepareStatements($statements);

        $this->assertKnownAccounts($statements);

        return DB::transaction(function () use ($statements, $filePath): array {
            $imported = 0;
            $skipped = 0;
            $integrityWarnings = 0;

            foreach ($statements as $statement) {
                $accountId = $this->bankAccountRepository->getByIban($statement->getAccount());

                $statementId = $this->bankStatementRepository->upsert(new CreateBankStatement(
                    bankAccountId: $accountId->value,
                    statementNumber: $statement->getNumber(),
                    startDate: $this->formatDate($statement->getStartTimestamp('Y-m-d')) ?? '',
                    endDate: $this->formatDate($statement->getEndTimestamp('Y-m-d')) ?? '',
                    openingBalance: $statement->getStartPrice(),
                    closingBalance: $statement->getEndPrice(),
                    currency: $statement->getCurrency(),
                    filePath: $filePath,
                ));

                if ($this->hasIntegrityMismatch($statement)) {
                    $integrityWarnings++;
                    $this->bankStatementRepository->updateIntegrity(
                        $statementId,
                        StatementIntegrityStatus::Mismatch,
                        $this->balanceDifference($statement),
                    );
                }

                $chainStatus = $this->determineChainStatus($accountId, $statement);
                if ($chainStatus === StatementChainStatus::Broken) {
                    $integrityWarnings++;
                }
                $this->bankStatementRepository->updateChain($statementId, $chainStatus);

                foreach ($statement->getTransactions() as $transaction) {
                    $hash = $this->computeHash($transaction, $statement);

                    if ($this->repository->existsByHash($hash)) {
                        $skipped++;
                        continue;
                    }

                    $this->repository->create(new CreateBankTransaction(
                        date: $this->formatDate($transaction->getValueTimestamp('Y-m-d')) ?? '',
                        amount: $transaction->getRelativePrice(),
                        description: $this->normalizeDescription($transaction->getDescription()),
                        bankingAccountNumber: $transaction->getAccount(),
                        bankAccountId: $accountId->value,
                        bankStatementId: $statementId->value,
                        importHash: $hash,
                    ));

                    $imported++;
                }
            }

            return ['imported' => $imported, 'skipped' => $skipped, 'integrity_warnings' => $integrityWarnings];
        });
    }

    /**
     * @param array<int, Statement> $statements
     * @return array<int, Statement>
     */
    private function prepareStatements(array $statements): array
    {
        $nonEmpty = array_filter($statements, static fn (Statement $statement): bool => count($statement->getTransactions()) > 0);
        usort($nonEmpty, static fn (Statement $a, Statement $b): int => $a->getStartTimestamp('U') <=> $b->getStartTimestamp('U'));

        return $nonEmpty;
    }

    /**
     * @param array<int, Statement> $statements
     * @throws UnknownBankAccountException
     */
    private function assertKnownAccounts(array $statements): void
    {
        foreach ($statements as $statement) {
            $this->bankAccountRepository->getByIban($statement->getAccount());
        }
    }

    private function hasIntegrityMismatch(Statement $statement): bool
    {
        return abs($this->balanceDifference($statement)) >= 0.01;
    }

    private function balanceDifference(Statement $statement): float
    {
        $transactionSum = 0.0;
        foreach ($statement->getTransactions() as $transaction) {
            $transactionSum += $transaction->getRelativePrice();
        }

        return $statement->getStartPrice() + $transactionSum - $statement->getEndPrice();
    }

    private function determineChainStatus(BankAccountId $accountId, Statement $statement): StatementChainStatus
    {
        $startDate = $this->formatDate($statement->getStartTimestamp('Y-m-d')) ?? '';

        $previous = $this->bankStatementRepository->findPrevious($accountId, $startDate);

        if ($previous === null) {
            return StatementChainStatus::Baseline;
        }

        return abs($previous->closingBalance - $statement->getStartPrice()) < 0.01
            ? StatementChainStatus::Ok
            : StatementChainStatus::Broken;
    }

    private function computeHash(Transaction $transaction, Statement $statement): string
    {
        $data = implode('|', [
            $transaction->getValueTimestamp('Y-m-d'),
            number_format($transaction->getPrice(), 2, '.', ''),
            $this->normalizeDescription($transaction->getDescription()),
            $transaction->getAccount(),
            $statement->getAccount(),
        ]);

        return hash('sha256', $data);
    }

    private function normalizeDescription(string $description): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $description));
    }

    private function formatDate(string|bool $date): ?string
    {
        if ($date === '' || $date === '1970-01-01' || $date === false) {
            return null;
        }

        return $date;
    }
}
