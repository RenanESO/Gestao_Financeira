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

    /**
     * Icone e tom do selo de status; o texto continua sendo exibido junto ao icone.
     *
     * @return array{icon: string, tone: string}
     */
    public static function statusBadge(string $status): array
    {
        return match ($status) {
            'pending' => ['icon' => 'o-clock', 'tone' => 'info'],
            'partial' => ['icon' => 'o-chart-pie', 'tone' => 'warning'],
            'paid' => ['icon' => 'o-check-circle', 'tone' => 'success'],
            'overdue' => ['icon' => 'o-exclamation-triangle', 'tone' => 'error'],
            default => ['icon' => 'o-no-symbol', 'tone' => 'neutral'],
        };
    }
}
