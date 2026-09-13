<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\BankTransactions;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankStatements\BankStatementId;
use App\Domain\BankStatements\CreateBankStatement;
use App\Domain\BankStatements\DetermineBankStatementChainStatusInput;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankTransactions\BankTransactionId;
use App\Infrastructure\BankTransactions\BankTransactionImportServiceImpl;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\BankAccounts\BankAccountRepositoryExpectation;
use Tests\Unit\Domain\BankStatements\BankStatementRepositoryExpectation;
use Tests\Unit\Domain\BankStatements\DetermineBankStatementChainStatusExpectation;
use Tests\Unit\Domain\BankTransactions\BankTransactionRepositoryExpectation;

final class BankTransactionImportServiceImplTest extends FeatureTestCase
{
    private BankTransactionRepositoryExpectation $repo;
    private BankAccountRepositoryExpectation $bankAccountRepository;
    private BankStatementRepositoryExpectation $bankStatementRepository;
    private DetermineBankStatementChainStatusExpectation $chainStatusService;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = BankTransactionRepositoryExpectation::create();
        $this->bankAccountRepository = BankAccountRepositoryExpectation::create();
        $this->bankStatementRepository = BankStatementRepositoryExpectation::create();
        $this->chainStatusService = DetermineBankStatementChainStatusExpectation::create();
    }

    #[Test]
    public function test_it_imports_transactions_from_a_valid_mt940_file(): void
    {
        $accountId = BankAccountId::create(1);
        $statementId = BankStatementId::create(10);
        $startDate = new DateTimeImmutable('2023-01-01');
        $endDate = new DateTimeImmutable('2023-01-03');
        $filePath = base_path('tests/Fixtures/mt940/sample.mta');

        $this->bankAccountRepository->expectsGetByIban('NL35RABO3010166281', $accountId);
        $this->bankStatementRepository->expectsUpsert(new CreateBankStatement(
            bankAccountId: $accountId->value,
            statementNumber: '1/1',
            startDate: $startDate,
            endDate: $endDate,
            openingBalance: 1000.0,
            closingBalance: 1300.0,
            currency: 'EUR',
            filePath: $filePath,
        ), $statementId);
        $this->chainStatusService->expectsDetermine(new DetermineBankStatementChainStatusInput(
            id: $statementId,
            accountId: $accountId,
            startDate: $startDate,
            openingBalance: 1000.0,
        ), StatementChainStatus::Baseline);

        $this->repo->expectsExistsByHashAlways(false);
        $this->repo->expectsCreateAlways(BankTransactionId::create(1));

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
            $this->chainStatusService->mock,
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
        $startDate = new DateTimeImmutable('2023-01-01');
        $endDate = new DateTimeImmutable('2023-01-03');
        $filePath = base_path('tests/Fixtures/mt940/sample.mta');

        $this->bankAccountRepository->expectsGetByIban('NL35RABO3010166281', $accountId);
        $this->bankStatementRepository->expectsUpsert(new CreateBankStatement(
            bankAccountId: $accountId->value,
            statementNumber: '1/1',
            startDate: $startDate,
            endDate: $endDate,
            openingBalance: 1000.0,
            closingBalance: 1300.0,
            currency: 'EUR',
            filePath: $filePath,
        ), $statementId);
        $this->chainStatusService->expectsDetermine(new DetermineBankStatementChainStatusInput(
            id: $statementId,
            accountId: $accountId,
            startDate: $startDate,
            openingBalance: 1000.0,
        ), StatementChainStatus::Baseline);

        $this->repo->expectsExistsByHashAlways(true);
        $this->repo->expectsCreateNever();

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
            $this->chainStatusService->mock,
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
        $this->bankStatementRepository->expectsUpdateIntegrityNever();
        $this->chainStatusService->expectsDetermineAlways(StatementChainStatus::Baseline);

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
            $this->chainStatusService->mock,
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

        $determineDates = [];
        $this->chainStatusService->expectsDetermineCapturingDates($determineDates, StatementChainStatus::Baseline);

        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
            $this->chainStatusService->mock,
        );
        $result = $service->importFromFile($filePath);

        static::assertSame(3, $result['imported']);
        static::assertSame(0, $result['skipped']);
        static::assertSame(['2023-01-01', '2023-01-02', '2023-01-03'], $determineDates);
    }

    #[Test]
    public function test_it_throws_for_nonexistent_file(): void
    {
        $service = new BankTransactionImportServiceImpl(
            $this->repo->mock,
            $this->bankAccountRepository->mock,
            $this->bankStatementRepository->mock,
            $this->chainStatusService->mock,
        );

        $this->expectException(InvalidArgumentException::class);
        $service->importFromFile('/nonexistent/path.mta');
    }
}
