<?php

namespace App\Support;

final class Display
{
    public static function money(string $value): string
    {
        $cents = Money::signedCents($value);
        $whole = (string) intdiv(abs($cents), 100);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);

        return ($cents < 0 ? '-R$ ' : 'R$ ').$whole.','.sprintf('%02d', abs($cents) % 100);
    }

    public static function status(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'partial' => 'Parcial',
            'paid' => 'Pago',
            'overdue' => 'Vencido',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }
}
