<?php

namespace Tests\Unit;

use App\Support\Display;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DecimalInputTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            ['0,50', '0.50', 50], ['1,25', '1.25', 125], ['10,50', '10.50', 1050],
            ['100,99', '100.99', 10099], ['1.234,56', '1234.56', 123456],
            ['-10,50', '-10.50', -1050], ['0', '0', 0], ['10,5', '10.5', 1050],
            ['-0,00', '-0.00', 0], ['9999999999999,99', '9999999999999.99', Money::MAX_CENTS],
            ['9.999.999.999.999,99', '9999999999999.99', Money::MAX_CENTS],
            ['10.50', '10.50', 1050],
        ];
    }

    #[DataProvider('amounts')]
    public function test_ui_values_convert_without_float_or_precision_loss(string $input, string $internal, int $cents): void
    {
        $this->assertSame($internal, Display::internalDecimal($input));
        $this->assertSame($cents, Money::signedCents(Display::internalDecimal($input)));
        $this->assertSame(str_replace('.', ',', $internal), Display::decimalInput($internal));
    }

    public function test_generic_conversion_preserves_extra_precision_without_rounding(): void
    {
        $this->assertSame('3.1415', Display::internalDecimal('3,1415'));
        $this->assertSame('3,1415', Display::decimalInput('3.1415'));
        $this->expectException(ValidationException::class);
        Money::cents(Display::internalDecimal('3,1415'));
    }

    public static function invalidAmounts(): array
    {
        return [['1,234.56'], ['12.34,56'], ['1,,50'], ['1,'], ['1e3'], [' 1,50'],
            ['10000000000000,00'], ['-1,50'], [''], [null], [0.5], [true]];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_or_disallowed_money_is_not_silently_coerced(mixed $value): void
    {
        $this->expectException(ValidationException::class);
        Money::cents(Display::internalDecimal($value));
    }

    public function test_empty_and_null_values_remain_unchanged(): void
    {
        foreach (['', null, 0] as $value) {
            $this->assertSame($value, Display::internalDecimal($value));
            $this->assertSame($value, Display::decimalInput($value));
        }
    }
}
