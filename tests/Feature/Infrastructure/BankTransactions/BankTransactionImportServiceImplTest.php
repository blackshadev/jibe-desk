<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\BankTransactions;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankStatements\BankStatementId;
use App\Domain\BankStatements\CreateBankStatement;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankTransactions\BankTransactionId;
use App\Infrastructure\BankTransactions\BankTransactionImportServiceImpl;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\BankAccounts\BankAccountRepositoryExpectation;
use Tests\Unit\Domain\BankStatements\BankStatementRepositoryExpectation;
use Tests\Unit\Domain\BankTransactions\BankTransactionRepositoryExpectation;

final class BankTransactionImportServiceImplTest extends FeatureTestCase
{
    private BankTransactionRepositoryExpectation $repo;
    private BankAccountRepositoryExpectation $bankAccountRepository;
    private BankStatementRepositoryExpectation $bankStatementRepository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = BankTransactionRepositoryExpectation::create();
        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();
        $this->bankStatementRepository = BankStatementRepositoryExpectation::create();
    }

    #[Test]
    public function test_it_imports_transactions_from_a_valid_mt940_file(): void
    {
        $accountId = BankAccountId::create(1);
        $statementId = BankStatementId::create(10);
        $filePath = base_path('tests/Fixtures/mt940/sample.mta');

        $this->bankAccountRepository->expectsGetByIban('NL35RABO3010166281', $accountId);
        $this->bankStatementRepository->expectsUpsert(new CreateBankStatement(
            bankAccountId: $accountId->value,
            statementNumber: '1/1',
            startDate: '2023-01-01',
            endDate: '2023-01-03',
            openingBalance: 1000.0,
            closingBalance: 1300.0,
            currency: 'EUR',
            filePath: $filePath,
        ), $statementId);
        $this->bankStatementRepository->expectsFindPrevious($accountId, '2023-01-01', null);
        $this->bankStatementRepository->expectsUpdateChain($statementId, StatementChainStatus::Baseline);

        $this->repo->expectsExistsByHashAlways(false);
        $this->repo->expectsCreateAlways(BankTransactionId::create(1));

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
        );
        $result = $service->importFromFile($filePath);

        static::assertArrayHasKey('imported', $result);
        static::assertArrayHasKey('skipped', $result);
        static::assertSame(2, $result['imported']);
        static::assertSame(0, $result['skipped']);
    }

    #[Test]
    public function test_it_skips_duplicates_on_second_import(): void
    {
        $accountId = BankAccountId::create(1);
        $statementId = BankStatementId::create(10);
        $filePath = base_path('tests/Fixtures/mt940/sample.mta');

        $this->bankAccountRepository->expectsGetByIban('NL35RABO3010166281', $accountId);
        $this->bankStatementRepository->expectsUpsert(new CreateBankStatement(
            bankAccountId: $accountId->value,
            statementNumber: '1/1',
            startDate: '2023-01-01',
            endDate: '2023-01-03',
            openingBalance: 1000.0,
            closingBalance: 1300.0,
            currency: 'EUR',
            filePath: $filePath,
        ), $statementId);
        $this->bankStatementRepository->expectsFindPrevious($accountId, '2023-01-01', null);
        $this->bankStatementRepository->expectsUpdateChain($statementId, StatementChainStatus::Baseline);

        $this->repo->expectsExistsByHashAlways(true);
        $this->repo->expectsCreateNever();

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
        );
        $result = $service->importFromFile($filePath);

        static::assertSame(0, $result['imported']);
        static::assertGreaterThanOrEqual(1, $result['skipped']);
    }

    #[Test]
    public function test_it_filters_out_empty_statements(): void
    {
        $accountId = BankAccountId::create(1);
        $filePath = base_path('tests/Fixtures/mt940/sample-with-empty-statements.mta');

        $this->bankAccountRepository->expectsGetByIban('NL35RABO3010166281', $accountId);
        $this->repo->expectsExistsByHashAlways(false);
        $this->repo->expectsCreateAlways(BankTransactionId::create(1));
        $this->bankStatementRepository->expectsUpsertAlways(BankStatementId::create(10));
        $this->bankStatementRepository->expectsFindPreviousAlways(null);
        $this->bankStatementRepository->expectsUpdateChainAlways();
        $this->bankStatementRepository->expectsUpdateIntegrityNever();

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
        );
        $result = $service->importFromFile($filePath);

        static::assertSame(2, $result['imported']);
        static::assertSame(0, $result['skipped']);
    }

    #[Test]
    public function test_it_sorts_statements_by_start_date(): void
    {
        $accountId = BankAccountId::create(1);
        $filePath = base_path('tests/Fixtures/mt940/sample-unsorted.mta');

        $this->bankAccountRepository->expectsGetByIban('NL35RABO3010166281', $accountId);
        $this->repo->expectsExistsByHashAlways(false);
        $this->repo->expectsCreateAlways(BankTransactionId::create(1));
        $this->bankStatementRepository->expectsUpsertAlways(BankStatementId::create(10));
        $this->bankStatementRepository->expectsUpdateChainAlways();

        $findPreviousDates = [];
        $this->bankStatementRepository->expectsFindPreviousCapturingDates($findPreviousDates);

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
        );
        $result = $service->importFromFile($filePath);

        static::assertSame(3, $result['imported']);
        static::assertSame(0, $result['skipped']);
        static::assertSame(['2023-01-01', '2023-01-02', '2023-01-03'], $findPreviousDates);
    }

    #[Test]
    public function test_it_throws_for_nonexistent_file(): void
    {
        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
        );

        $this->expectException(InvalidArgumentException::class);
        $service->importFromFile('/nonexistent/path.mta');
    }
}
