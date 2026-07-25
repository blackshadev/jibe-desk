<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceRepository;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class InvoiceMatchingRepositoryExpectation
{
    private function __construct(
        public MockInterface&InvoiceRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceRepository::class));
    }

    public function expectsFindMatchingCredit(string $bankingAccountNumber, float $amount, DateTimeInterface $date, ?InvoiceId $return): void
    {
        $this->mock
            ->expects('findMatchingCredit')
            ->with(equalTo($bankingAccountNumber), equalTo($amount), equalTo($date))
            ->andReturn($return);
    }
}
