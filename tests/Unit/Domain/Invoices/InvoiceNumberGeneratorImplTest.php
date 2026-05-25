<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceNumberGeneratorImpl;
use DateTimeImmutable;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\UnitTestCase;

final class InvoiceNumberGeneratorImplTest extends UnitTestCase
{
    private ClockExpectation $clock;

    private InvoiceRepositoryExpectation $repo;

    private InvoiceNumberGeneratorImpl $subject;

    protected function setup(): void
    {
        parent::setup();

        $this->clock = ClockExpectation::create();
        $this->repo = InvoiceRepositoryExpectation::create();

        $this->subject = new InvoiceNumberGeneratorImpl($this->repo->mock, $this->clock->mock);
    }

    public function test_it_generates_first_number_for_current_year_when_latest_is_from_previous_year(): void
    {
        $this->clock->expectsNow(new DateTimeImmutable('2026-05-16'));
        $this->repo->expectsGetLatestInvoiceNumber('I-2025000199');

        self::assertSame('I-2026000001', $this->subject->generate()->value);
    }

    public function test_it_increments_latest_number_when_same_year(): void
    {
        $this->clock->expectsNow(new DateTimeImmutable('2026-01-01'));
        $this->repo->expectsGetLatestInvoiceNumber('I-2026000012');

        self::assertSame('I-2026000013', $this->subject->generate()->value);
    }
}
