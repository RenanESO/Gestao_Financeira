<?php

namespace App\Support;

class Branding
{
    public const COLORS = [
        'primary' => 'Primária', 'secondary' => 'Secundária', 'accent' => 'Destaque',
        'neutral' => 'Neutra', 'info' => 'Informação', 'success' => 'Sucesso',
        'warning' => 'Aviso', 'error' => 'Erro', 'base-100' => 'Fundo', 'base-content' => 'Texto',
    ];

    public static function name(): string
    {
        return auth()->user()?->branding['name'] ?? 'MoneyControl';
    }

    public static function style(): string
    {
        $declarations = [];
        foreach (auth()->user()?->branding['colors'] ?? [] as $key => $color) {
            if (! isset(self::COLORS[$key]) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                continue;
            }
            $declarations[] = "--color-{$key}:{$color}";
            if (! str_starts_with($key, 'base-')) {
                $declarations[] = "--color-{$key}-content:".self::contrast($color);
            }
            if ($key === 'base-100') {
                $declarations[] = "--color-base-200:color-mix(in srgb, {$color} 95%, black)";
                $declarations[] = "--color-base-300:color-mix(in srgb, {$color} 90%, black)";
            }
        }

        return implode(';', $declarations);
    }

    private static function contrast(string $color): string
    {
        $channels = array_map(function ($offset) use ($color) {
            $value = hexdec(substr($color, $offset, 2)) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, [1, 3, 5]);
        $luminance = $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;

        return ($luminance + 0.05) / 0.05 >= 1.05 / ($luminance + 0.05) ? '#000000' : '#ffffff';
    }
}
