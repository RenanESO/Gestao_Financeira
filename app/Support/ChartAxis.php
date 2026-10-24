<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class ChartAxis
{
    /** Posicao horizontal do indice dentro da area de plotagem; um unico ponto fica centralizado. */
    public static function x(int $index, int $count, float $left, float $right): float
    {
        return round($left + ($count === 1 ? ($right - $left) / 2 : $index * ($right - $left) / ($count - 1)), 2);
    }

    /**
     * Ate $max rotulos de data distribuidos uniformemente; inclui o ano quando o periodo cruza anos.
     *
     * @param  list<string>  $dates  datas Y-m-d em ordem
     * @return list<array{x: float, label: string, anchor: string}>
     */
    public static function dateTicks(array $dates, float $left, float $right, int $max = 7): array
    {
        $count = count($dates);
        if ($count === 0) {
            return [];
        }
        $sameYear = substr($dates[0], 0, 4) === substr($dates[$count - 1], 0, 4);
        $slots = min($count, $max);
        $indexes = array_unique(array_map(
            fn ($k) => $slots === 1 ? 0 : (int) round($k * ($count - 1) / ($slots - 1)),
            range(0, $slots - 1),
        ));

        return array_map(fn ($index) => [
            'x' => self::x($index, $count, $left, $right),
            'label' => CarbonImmutable::parse($dates[$index])->format($sameYear ? 'd/m' : 'd/m/y'),
            'anchor' => $count > 1 && $index === 0 ? 'start' : ($count > 1 && $index === $count - 1 ? 'end' : 'middle'),
        ], array_values($indexes));
    }
}
