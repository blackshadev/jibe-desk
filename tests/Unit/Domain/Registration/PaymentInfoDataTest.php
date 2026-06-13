<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\PaymentInfoData;
use Tests\UnitTestCase;

final class PaymentInfoDataTest extends UnitTestCase
{
    private const array DEFAULT_DATA = [
        'bankingAccountNumber' => 'NL91ABNA0417164300',
        'bankingBic' => 'ABNANL2A',
        'bankingAccountHolderName' => 'J. de Vries',
        'mandateAcceptedDate' => '2024-02-01T03:04:05+00:00',
    ];

    public function test_create_default_returns_empty_values(): void
    {
        $data = PaymentInfoData::createDefault();

        static::assertSame('', $data->bankingAccountNumber);
        static::assertSame('', $data->bankingBic);
        static::assertSame('', $data->bankingAccountHolderName);
        static::assertNull($data->mandateAcceptedDate);
    }

    public function test_create_from_array_hydrates_all_fields(): void
    {
        $data = PaymentInfoData::createFromArray(self::DEFAULT_DATA);

        static::assertSame('NL91ABNA0417164300', $data->bankingAccountNumber);
        static::assertSame('ABNANL2A', $data->bankingBic);
        static::assertSame('J. de Vries', $data->bankingAccountHolderName);
        static::assertSame('2024-02-01T03:04:05+00:00', $data->mandateAcceptedDate?->format('c'));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $data = PaymentInfoData::createFromArray(self::DEFAULT_DATA);

        static::assertSame(self::DEFAULT_DATA, $data->toArray());
    }

    public function test_to_and_from_array_roundtrip(): void
    {
        $original = PaymentInfoData::createFromArray(self::DEFAULT_DATA);
        $data = PaymentInfoData::createFromArray($original->toArray());

        static::assertSame($original->toArray(), $data->toArray());
    }
}
