<?php

namespace Tests\Unit;

use App\Support\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public static function validAmounts(): array
    {
        return [
            ['0', 0, '0.00'], ['0.01', 1, '0.01'], ['10.1', 1010, '10.10'],
            [10, 1000, '10.00'], ['9999999999999.99', Money::MAX_CENTS, '9999999999999.99'],
        ];
    }

    #[DataProvider('validAmounts')]
    public function test_values_convert_to_exact_cents(mixed $valor, int $centavos, string $decimal): void
    {
        $this->assertSame($centavos, Money::cents($valor));
        $this->assertSame($decimal, Money::decimal($centavos));
    }

    public static function invalidAmounts(): array
    {
        return [[0.1], ['-1'], ['1.001'], ['1,00'], ['1e3'], ['10000000000000'], [null], [true], [' 10'], ['']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_or_float_amounts_are_rejected(mixed $valor): void
    {
        $this->expectException(ValidationException::class);
        Money::cents($valor);
    }

    public function test_out_of_range_cent_values_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Money::decimal(Money::MAX_CENTS + 1);
    }

    public static function signedAmounts(): array
    {
        return [
            ['-10.1', -1010, '-10.10'],
            [-10, -1000, '-10.00'],
            ['-0.00', 0, '0.00'],
            ['-9999999999999.99', -Money::MAX_CENTS, '-9999999999999.99'],
        ];
    }

    #[DataProvider('signedAmounts')]
    public function test_signed_balances_keep_exact_cents(mixed $valor, int $centavos, string $decimal): void
    {
        $this->assertSame($centavos, Money::signedCents($valor));
        $this->assertSame($decimal, Money::signedDecimal($centavos));
    }

    public function test_signed_decimal_rejects_negative_overflow(): void
    {
        $this->expectException(ValidationException::class);
        Money::signedDecimal(-Money::MAX_CENTS - 1);
    }

    public function test_signed_input_still_rejects_floats(): void
    {
        $this->expectException(ValidationException::class);
        Money::signedCents(-10.1);
    }
}
