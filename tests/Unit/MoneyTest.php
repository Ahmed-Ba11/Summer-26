<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_amounts_are_rounded_to_the_nearest_halalah(): void
    {
        $this->assertSame(1000, Money::toHalalas('10.00'));
        $this->assertSame(1001, Money::toHalalas('10.01'));
        $this->assertSame(1250, Money::toHalalas('12.50'));
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected(mixed $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toHalalas($amount);
    }

    /**
     * @return list<array{mixed}>
     */
    public static function invalidAmounts(): array
    {
        return [
            ['1e3'],
            ['10.005'],
            ['not-a-number'],
            [''],
            ['INF'],
            [INF],
            [NAN],
        ];
    }
}
