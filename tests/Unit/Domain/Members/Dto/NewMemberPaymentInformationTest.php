<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Dto;

use App\Domain\Members\Dto\NewMemberPaymentInformation;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class NewMemberPaymentInformationTest extends UnitTestCase
{
    public function test_constructor_stores_all_properties(): void
    {
        $mandateDate = new DateTimeImmutable('2024-02-01');

        $dto = new NewMemberPaymentInformation(
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            accountHolderName: 'J. de Vries',
            mandateAcceptedDate: $mandateDate,
        );

        self::assertSame('NL91ABNA0417164300', $dto->iban);
        self::assertSame('ABNANL2A', $dto->bic);
        self::assertSame('J. de Vries', $dto->accountHolderName);
        self::assertSame($mandateDate, $dto->mandateAcceptedDate);
    }
}
