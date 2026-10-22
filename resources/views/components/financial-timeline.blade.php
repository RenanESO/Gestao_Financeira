@props(['daily'])
@php
    $max = max(1, max(array_map(fn ($v) => max(abs($v['income']), abs($v['expense'])), $daily)));
    $days = count($daily);
    $points = [];
    foreach ($daily as $date => $values) {
        $i = count($points);
        $points[] = [
            'x' => 30 + ($days === 1 ? 450 : $i * 900 / ($days - 1)),
            'incomeY' => 110 - 85 * $values['income'] / $max,
            'expenseY' => 110 - 85 * $values['expense'] / $max,
            'date' => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y'),
            'income' => \App\Support\Display::money(\App\Support\Money::signedDecimal($values['income'])),
            'expense' => \App\Support\Display::money(\App\Support\Money::signedDecimal($values['expense'])),
        ];
    }
@endphp
<div
    wire:key="timeline-{{ hash('sha256', json_encode($daily)) }}"
    x-data="{
        points: {{ \Illuminate\Support\Js::from($points) }},
        active: null,
        select(event) {
            const rect = this.$refs.chart.getBoundingClientRect();
            const x = (event.clientX - rect.left) * 960 / rect.width;
            const index = this.points.length === 1 ? 0 : Math.round((x - 30) / 900 * (this.points.length - 1));
            this.active = this.points[Math.max(0, Math.min(this.points.length - 1, index))];
        },
        move(step) {
            const index = this.active ? this.points.indexOf(this.active) : 0;
            this.active = this.points[Math.max(0, Math.min(this.points.length - 1, index + step))];
        }
    }"
>
    <p class="muted mb-2">Passe o mouse, toque no gráfico ou use as setas do teclado para consultar uma data.</p>
    <svg x-ref="chart" class="w-full" viewBox="0 0 960 230" role="img" tabindex="0"
        aria-label="Grafico temporal de entradas e saidas"
        @pointermove="select($event)" @pointerdown="select($event)"
        @pointerleave="if ($event.pointerType === 'mouse') active = null"
        @focus="active = points[0]" @blur="active = null"
        @keydown.right.prevent="move(1)" @keydown.left.prevent="move(-1)" @keydown.escape="active = null">
        <line x1="30" x2="930" y1="110" y2="110" stroke="currentColor" opacity=".2" />
        <polyline points="{{ implode(' ', array_map(fn ($p) => $p['x'].','.$p['incomeY'], $points)) }}" fill="none" stroke="#10b981" stroke-width="3" />
        <polyline points="{{ implode(' ', array_map(fn ($p) => $p['x'].','.$p['expenseY'], $points)) }}" fill="none" stroke="#ef4444" stroke-width="3" />
        <g x-show="active !== null" x-cloak pointer-events="none">
            <line :x1="active?.x ?? 30" :x2="active?.x ?? 30" y1="15" y2="200" stroke="currentColor" opacity=".35" stroke-dasharray="4 4" />
            <circle :cx="active?.x ?? 30" :cy="active?.incomeY ?? 110" r="5" fill="#10b981" />
            <circle :cx="active?.x ?? 30" :cy="active?.expenseY ?? 110" r="5" fill="#ef4444" />
            <g :transform="'translate(' + Math.min(710, Math.max(30, (active?.x ?? 30) - 110)) + ', 12)'">
                <rect width="220" height="78" rx="8" fill="#ffffff" stroke="#cbd5e1" />
                <text x="12" y="21" fill="#172033" font-size="13" font-weight="bold" x-text="active?.date ?? ''"></text>
                <text x="12" y="43" fill="#047857" font-size="12" x-text="'Entradas: ' + (active?.income ?? '')"></text>
                <text x="12" y="65" fill="#be123c" font-size="12" x-text="'Saídas: ' + (active?.expense ?? '')"></text>
            </g>
        </g>
        <text x="30" y="225" fill="currentColor" font-size="12">{{ array_key_first($daily) }}</text>
        <text x="845" y="225" fill="currentColor" font-size="12">{{ array_key_last($daily) }}</text>
    </svg>
    <p class="muted min-h-6" aria-live="polite" x-text="active ? active.date + ' | Entradas: ' + active.income + ' | Saídas: ' + active.expense : ''"></p>
</div>
