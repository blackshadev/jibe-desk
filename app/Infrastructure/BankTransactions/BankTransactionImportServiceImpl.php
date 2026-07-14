<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionImportService;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\CreateBankTransaction;
use InvalidArgumentException;
use Kingsquare\Banking\Statement;
use Kingsquare\Banking\Transaction;
use Kingsquare\Parser\Banking\Mt940;
use Override;

final readonly class BankTransactionImportServiceImpl implements BankTransactionImportService
{
    public function __construct(
        private BankTransactionRepository $repository,
    ) {}

    #[Override]
    public function importFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $parser = new Mt940();
        $statements = $parser->parse($content);

        $imported = 0;
        $skipped = 0;

        foreach ($statements as $statement) {
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
                    bankingAccountNumber: $statement->getAccount(),
                    importHash: $hash,
                ));

                $imported++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function computeHash(Transaction $transaction, Statement $statement): string
    {
        $data = implode('|', [
            $transaction->getValueTimestamp('Y-m-d'),
            number_format($transaction->getPrice(), 2, '.', ''),
            $this->normalizeDescription($transaction->getDescription()),
            $statement->getAccount(),
        ]);

        return hash('sha256', $data);
    }

    private function normalizeDescription(string $description): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $description));
    }

    private function formatDate(string $date): ?string
    {
        if (empty($date) || $date === '1970-01-01') {
            return null;
        }

        return $date;
    }
}
