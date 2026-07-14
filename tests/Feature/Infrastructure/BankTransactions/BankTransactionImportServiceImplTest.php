<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Infrastructure\BankTransactions\BankTransactionImportServiceImpl;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\BankTransactions\BankTransactionRepositoryExpectation;

final class BankTransactionImportServiceImplTest extends FeatureTestCase
{
    private BankTransactionRepositoryExpectation $repo;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = BankTransactionRepositoryExpectation::create();
    }

    #[Test]
    public function test_it_imports_transactions_from_a_valid_mt940_file(): void
    {
        $this->repo->expectsExistsByHashAlways(false);
        $this->repo->expectsCreateAlways(BankTransactionId::create(1));

        $service = new BankTransactionImportServiceImpl($this->repo->mock);
        $result = $service->importFromFile(base_path('tests/Fixtures/mt940/sample.mta'));

        static::assertArrayHasKey('imported', $result);
        static::assertArrayHasKey('skipped', $result);
        static::assertSame(2, $result['imported']);
        static::assertSame(0, $result['skipped']);
    }

    #[Test]
    public function test_it_skips_duplicates_on_second_import(): void
    {
        $this->repo->expectsExistsByHashAlways(true);
        $this->repo->expectsCreateNever();

        $service = new BankTransactionImportServiceImpl($this->repo->mock);
        $result = $service->importFromFile(base_path('tests/Fixtures/mt940/sample.mta'));

        static::assertSame(0, $result['imported']);
        static::assertGreaterThanOrEqual(1, $result['skipped']);
    }

    #[Test]
    public function test_it_throws_for_nonexistent_file(): void
    {
        $service = new BankTransactionImportServiceImpl($this->repo->mock);

        $this->expectException(InvalidArgumentException::class);
        $service->importFromFile('/nonexistent/path.mta');
    }
}
