@props(['daily'])
@php
    // Area de plotagem dentro do viewBox (960 x 300); margem esquerda reservada aos rotulos do eixo Y.
    $width = 960;
    $height = 300;
    $left = 88;
    $right = 944;
    $top = 16;
    $bottom = 256;

    $values = array_merge(...array_map(fn ($v) => [$v['income'], $v['expense']], array_values($daily)));
    $min = min(0, min($values));
    $max = max(0, max($values));
    if ($min === $max) {
        $max = 100;
    }

    // Escala "redonda" (1, 2, 2.5, 5 x 10^n) com cerca de 4 intervalos.
    $rough = ($max - $min) / 4;
    $magnitude = 10 ** floor(log10($rough));
    $normalized = $rough / $magnitude;
    $step = $magnitude * match (true) {
        $normalized <= 1 => 1,
        $normalized <= 2 => 2,
        $normalized <= 2.5 => 2.5,
        $normalized <= 5 => 5,
        default => 10,
    };
    $axisMin = floor($min / $step) * $step;
    $axisMax = ceil($max / $step) * $step;

    $scaleY = fn ($cents) => $bottom - ($cents - $axisMin) * ($bottom - $top) / ($axisMax - $axisMin);
    // Unidade única para todo o eixo (R$, mil, mi) e casas decimais suficientes para o passo.
    $largest = max(abs($axisMin), abs($axisMax));
    [$divisor, $suffix] = match (true) {
        $largest >= 100_000_000 => [100_000_000, ' mi'],
        $largest >= 100_000 => [100_000, ' mil'],
        default => [100, ''],
    };
    $decimals = 0;
    while ($decimals < 2 && abs(round($step / $divisor, $decimals) - $step / $divisor) > 1e-9) {
        $decimals++;
    }
    if ($suffix === '' && $decimals > 0) {
        $decimals = 2;
    }
    $compact = fn (float $cents): string => (round($cents) < 0 ? '-' : '').'R$ '
        .(abs($cents) < $step / 2 ? '0' : number_format(abs($cents) / $divisor, $decimals, ',', '.').$suffix);

    $yTicks = [];
    for ($tick = $axisMin; $tick <= $axisMax + $step / 2; $tick += $step) {
        $yTicks[] = ['y' => round($scaleY($tick), 2), 'label' => $compact($tick), 'zero' => abs($tick) < $step / 2];
    }
    $zeroY = round($scaleY(0), 2);

    $dates = array_keys($daily);
    $days = count($dates);
    $span = $right - $left;
    $points = [];
    foreach ($daily as $date => $item) {
        $points[] = [
            'x' => \App\Support\ChartAxis::x(count($points), $days, $left, $right),
            'incomeY' => round($scaleY($item['income']), 2),
            'expenseY' => round($scaleY($item['expense']), 2),
            'date' => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y'),
            'income' => \App\Support\Display::money(\App\Support\Money::signedDecimal($item['income'])),
            'expense' => \App\Support\Display::money(\App\Support\Money::signedDecimal($item['expense'])),
            'balance' => \App\Support\Display::money(\App\Support\Money::signedDecimal($item['income'] - $item['expense'])),
        ];
    }

    $xTicks = \App\Support\ChartAxis::dateTicks($dates, $left, $right);

    $line = fn (string $key) => implode(' ', array_map(fn ($p) => $p['x'].','.$p[$key], $points));
    $area = fn (string $key) => 'M'.$points[0]['x'].','.$zeroY.' L'.$line($key).' L'.$points[$days - 1]['x'].','.$zeroY.' Z';
@endphp
<div
    wire:key="timeline-{{ hash('sha256', json_encode($daily)) }}"
    x-data="{
        points: {{ \Illuminate\Support\Js::from($points) }},
        active: null,
        select(event) {
            const rect = this.$refs.chart.getBoundingClientRect();
            const x = (event.clientX - rect.left) * {{ $width }} / rect.width;
            const index = this.points.length === 1 ? 0 : Math.round((x - {{ $left }}) / {{ $span }} * (this.points.length - 1));
            this.active = this.points[Math.max(0, Math.min(this.points.length - 1, index))];
        },
        move(step) {
            const index = this.active ? this.points.indexOf(this.active) : 0;
            this.active = this.points[Math.max(0, Math.min(this.points.length - 1, index + step))];
        },
        tooltipStyle() {
            // Acompanha o ponto mais alto entre entradas e saídas; abaixo dele quando não ha espaço acima.
            const top = Math.min(this.active?.incomeY ?? 0, this.active?.expenseY ?? 0) / {{ $height }} * 100;
            const left = Math.min(85, Math.max(15, (this.active?.x ?? 0) / {{ $width }} * 100));
            const shift = top < 45 ? '14px' : 'calc(-100% - 14px)';
            return `left: ${left}%; top: ${top}%; transform: translate(-50%, ${shift})`;
        }
    }"
>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <ul class="flex items-center gap-5 text-sm text-base-content/80" aria-label="Legenda">
            <li class="flex items-center gap-2"><span class="h-0.5 w-5 rounded-full bg-emerald-500"></span>Entradas</li>
            <li class="flex items-center gap-2"><span class="h-0.5 w-5 rounded-full bg-rose-500"></span>Saídas</li>
        </ul>
        <p class="text-xs text-base-content/50">Passe o mouse, toque no gráfico ou use as setas do teclado.</p>
    </div>
    {{-- Largura minima mantem os eixos legiveis em telas estreitas; o gráfico rola na horizontal. --}}
    <div class="overflow-x-auto"><div class="relative min-w-[720px]">
        <svg x-ref="chart" class="w-full select-none outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded-lg"
            viewBox="0 0 {{ $width }} {{ $height }}" role="img" tabindex="0"
            aria-label="Gráfico temporal de entradas e saídas"
            @pointermove="select($event)" @pointerdown="select($event)"
            @pointerleave="if ($event.pointerType === 'mouse') active = null"
            @focus="active = points[0]" @blur="active = null"
            @keydown.right.prevent="move(1)" @keydown.left.prevent="move(-1)" @keydown.escape="active = null">
            {{-- Eixo Y: grade horizontal e rotulos de valor --}}
            <g font-size="13" class="tabular-nums">
                @foreach($yTicks as $tick)
                    <line x1="{{ $left }}" x2="{{ $right }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"
                        class="{{ $tick['zero'] ? 'stroke-base-content/30' : 'stroke-base-content/10' }}" stroke-width="1" vector-effect="non-scaling-stroke" />
                    <text x="{{ $left - 12 }}" y="{{ $tick['y'] }}" text-anchor="end" dominant-baseline="middle" class="fill-base-content/55">{{ $tick['label'] }}</text>
                @endforeach
            </g>

            {{-- Eixo X: marcas e rotulos de data --}}
            <g font-size="13" class="tabular-nums">
                @foreach($xTicks as $tick)
                    <line x1="{{ $tick['x'] }}" x2="{{ $tick['x'] }}" y1="{{ $bottom }}" y2="{{ $bottom + 6 }}" class="stroke-base-content/30" vector-effect="non-scaling-stroke" />
                    <text x="{{ $tick['x'] }}" y="{{ $bottom + 24 }}" text-anchor="{{ $tick['anchor'] }}" class="fill-base-content/55">{{ $tick['label'] }}</text>
                @endforeach
            </g>

            {{-- Series --}}
            <path d="{{ $area('incomeY') }}" class="fill-emerald-500/10" />
            <path d="{{ $area('expenseY') }}" class="fill-rose-500/10" />
            <polyline points="{{ $line('incomeY') }}" fill="none" class="stroke-emerald-500" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            <polyline points="{{ $line('expenseY') }}" fill="none" class="stroke-rose-500" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

            {{-- Area de captura do ponteiro sobre toda a plotagem --}}
            <rect x="0" y="0" width="{{ $width }}" height="{{ $height }}" fill="transparent" />

            {{-- Crosshair --}}
            <g x-show="active !== null" x-cloak pointer-events="none">
                <line :x1="active?.x ?? 0" :x2="active?.x ?? 0" y1="{{ $top }}" y2="{{ $bottom }}" class="stroke-base-content/30" stroke-width="1" vector-effect="non-scaling-stroke" />
                <circle :cx="active?.x ?? 0" :cy="active?.incomeY ?? 0" r="5" class="fill-emerald-500 stroke-base-100" stroke-width="2" />
                <circle :cx="active?.x ?? 0" :cy="active?.expenseY ?? 0" r="5" class="fill-rose-500 stroke-base-100" stroke-width="2" />
            </g>
        </svg>

        <div x-show="active !== null" x-cloak x-transition.opacity.duration.100ms
            class="pointer-events-none absolute z-10 min-w-48 rounded-xl border border-base-content/10 bg-base-100/95 px-3 py-2.5 text-sm shadow-lg backdrop-blur transition-[left,top] duration-75 ease-out"
            :style="tooltipStyle()">
            <p class="mb-1.5 font-semibold" x-text="active?.date ?? ''"></p>
            <p class="flex items-center justify-between gap-4"><span class="flex items-center gap-2 text-base-content/70"><span class="size-2 rounded-full bg-emerald-500"></span>Entradas</span><span class="tabular-nums font-medium" x-text="active?.income ?? ''"></span></p>
            <p class="flex items-center justify-between gap-4"><span class="flex items-center gap-2 text-base-content/70"><span class="size-2 rounded-full bg-rose-500"></span>Saídas</span><span class="tabular-nums font-medium" x-text="active?.expense ?? ''"></span></p>
            <p class="mt-1.5 flex items-center justify-between gap-4 border-t border-base-content/10 pt-1.5"><span class="text-base-content/70">Saldo do dia</span><span class="tabular-nums font-semibold" x-text="active?.balance ?? ''"></span></p>
        </div>
    </div></div>
    <p class="sr-only" aria-live="polite" x-text="active ? active.date + ' | Entradas: ' + active.income + ' | Saídas: ' + active.expense + ' | Saldo: ' + active.balance : ''"></p>
</div>
