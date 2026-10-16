<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public const MAX_CENTS = 999999999999999;

    public static function cents(mixed $valor, string $campo = 'value'): int
    {
        if ((! is_string($valor) && ! is_int($valor))
            || ! preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D', (string) $valor, $partes)) {
            throw ValidationException::withMessages([
                $campo => 'Informe um valor nao negativo, sem separador de milhar e com ate duas casas decimais. Use string, nao float.',
            ]);
        }

        return ((int) $partes[1] * 100) + (int) str_pad($partes[2] ?? '', 2, '0');
    }

    public static function decimal(int $centavos, string $campo = 'value'): string
    {
        if ($centavos < 0 || $centavos > self::MAX_CENTS) {
            throw ValidationException::withMessages([$campo => 'Valor fora do limite DECIMAL(15,2).']);
        }

        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }

    public static function signedCents(mixed $valor, string $campo = 'value'): int
    {
        if (is_string($valor) && str_starts_with($valor, '-')) {
            return -self::cents(substr($valor, 1), $campo);
        }

        if (is_int($valor) && $valor < 0) {
            return -self::cents(substr((string) $valor, 1), $campo);
        }

        return self::cents($valor, $campo);
    }

    public static function signedDecimal(int $centavos, string $campo = 'value'): string
    {
        if ($centavos < -self::MAX_CENTS || $centavos > self::MAX_CENTS) {
            throw ValidationException::withMessages([$campo => 'Saldo fora do limite DECIMAL(15,2).']);
        }

        return ($centavos < 0 ? '-' : '').self::decimal(abs($centavos), $campo);
    }
}
