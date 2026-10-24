@props(['accounts', 'dates'])
@php
    // Mesma area de plotagem do gráfico de entradas e saídas; a escala Y e calculada no navegador
    // para se ajustar somente as contas selecionadas.
    $width = 960;
    $height = 300;
    $left = 88;
    $right = 944;
    $top = 16;
    $bottom = 256;
    $xTicks = \App\Support\ChartAxis::dateTicks($dates, $left, $right);
    $labels = array_map(fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y'), $dates);
    $money = fn (int $cents) => \App\Support\Display::money(\App\Support\Money::signedDecimal($cents));
@endphp
@if(empty($accounts))
    <p class="muted">Nenhuma conta financeira para exibir com os filtros aplicados.</p>
@else
<div
    wire:key="account-balance-{{ hash('sha256', json_encode([$accounts, $dates])) }}"
    x-data="{
        accounts: {{ \Illuminate\Support\Js::from(array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['name'], 'color' => $a['color'], 'values' => $a['values']], $accounts)) }},
        dates: {{ \Illuminate\Support\Js::from($labels) }},
        selected: {{ \Illuminate\Support\Js::from(array_column($accounts, 'id')) }},
        active: null,
        axis: { min: 0, max: 1, step: 1, ticks: [] },
        paths: {},
        brl: new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }),
        init() { this.rescale(); },
        isOn(id) { return this.selected.includes(id); },
        toggle(id) {
            this.selected = this.isOn(id) ? this.selected.filter(s => s !== id) : [...this.selected, id];
            this.rescale();
        },
        all(on) { this.selected = on ? this.accounts.map(a => a.id) : []; this.rescale(); },
        visible() { return this.accounts.filter(a => this.isOn(a.id)); },
        x(i) {
            const n = this.dates.length;
            return n === 1 ? ({{ $left }} + {{ $right }}) / 2 : {{ $left }} + i * {{ $right - $left }} / (n - 1);
        },
        y(cents) { return {{ $bottom }} - (cents - this.axis.min) * {{ $bottom - $top }} / (this.axis.max - this.axis.min); },
        rescale() {
            const values = this.visible().flatMap(a => a.values);
            let min = values.length ? Math.min(...values) : 0;
            let max = values.length ? Math.max(...values) : 100;
            if (min === max) {
                const pad = Math.max(Math.abs(min) * 0.1, 100);
                min -= pad;
                max += pad;
            }
            // Escala redonda (1, 2, 2.5, 5 x 10^n) com cerca de 4 intervalos.
            const rough = (max - min) / 4;
            const magnitude = 10 ** Math.floor(Math.log10(rough));
            const normalized = rough / magnitude;
            const step = magnitude * (normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 2.5 ? 2.5 : normalized <= 5 ? 5 : 10);
            const axisMin = Math.floor(min / step) * step;
            const axisMax = Math.ceil(max / step) * step;
            const largest = Math.max(Math.abs(axisMin), Math.abs(axisMax));
            const [divisor, suffix] = largest >= 1e8 ? [1e8, ' mi'] : largest >= 1e5 ? [1e5, ' mil'] : [100, ''];
            let decimals = 0;
            while (decimals < 2 && Math.abs(Number((step / divisor).toFixed(decimals)) - step / divisor) > 1e-9) decimals++;
            if (suffix === '' && decimals > 0) decimals = 2;
            const label = v => Math.abs(v) < step / 2 ? 'R$ 0' : (v < 0 ? '-' : '') + 'R$ '
                + (Math.abs(v) / divisor).toLocaleString('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
            this.axis = { min: axisMin, max: axisMax, step, ticks: [] };
            for (let k = 0; axisMin + k * step <= axisMax + step / 2; k++) {
                const v = axisMin + k * step;
                this.axis.ticks.push({ y: this.y(v), label: label(v), zero: Math.abs(v) < step / 2 });
            }
            this.paths = Object.fromEntries(this.accounts.map(a => [a.id, a.values.map((v, i) => this.x(i) + ',' + this.y(v)).join(' ')]));
        },
        select(event) {
            const rect = this.$refs.chart.getBoundingClientRect();
            const x = (event.clientX - rect.left) * {{ $width }} / rect.width;
            const n = this.dates.length;
            const index = n === 1 ? 0 : Math.round((x - {{ $left }}) / {{ $right - $left }} * (n - 1));
            this.active = Math.max(0, Math.min(n - 1, index));
        },
        move(step) { this.active = Math.max(0, Math.min(this.dates.length - 1, (this.active ?? 0) + step)); },
        money(cents) { return this.brl.format(cents / 100); },
        rows() {
            return this.visible().map(a => ({ ...a, value: a.values[this.active] })).sort((p, q) => q.value - p.value);
        },
        total() { return this.visible().reduce((sum, a) => sum + a.values[this.active], 0); },
        tooltipStyle() {
            // Acompanha a linha mais alta entre as contas selecionadas.
            const top = Math.min(...this.visible().map(a => this.y(a.values[this.active]))) / {{ $height }} * 100;
            const left = Math.min(85, Math.max(15, this.x(this.active) / {{ $width }} * 100));
            const shift = top < 45 ? '14px' : 'calc(-100% - 14px)';
            return `left: ${left}%; top: ${top}%; transform: translate(-50%, ${shift})`;
        }
    }"
>
    <p class="mb-3 text-xs text-base-content/50">Passe o mouse, toque no gráfico ou use as setas do teclado.</p>
    {{-- Largura minima mantem os eixos legiveis em telas estreitas; o gráfico rola na horizontal. --}}
    <div class="overflow-x-auto"><div class="relative min-w-[720px]">
        <svg x-ref="chart" class="w-full select-none outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded-lg"
            viewBox="0 0 {{ $width }} {{ $height }}" role="img" tabindex="0"
            aria-label="Gráfico do histórico de saldo por conta financeira"
            @pointermove="if (selected.length) select($event)" @pointerdown="if (selected.length) select($event)"
            @pointerleave="if ($event.pointerType === 'mouse') active = null"
            @focus="if (selected.length) active = 0" @blur="active = null"
            @keydown.right.prevent="if (selected.length) move(1)" @keydown.left.prevent="if (selected.length) move(-1)" @keydown.escape="active = null">
            {{-- Eixo Y: as faixas sao preenchidas pelo Alpine conforme as contas selecionadas --}}
            <g font-size="13" class="tabular-nums">
                @for($slot = 0; $slot < 8; $slot++)
                    <g x-show="axis.ticks[{{ $slot }}]">
                        <line x1="{{ $left }}" x2="{{ $right }}" :y1="axis.ticks[{{ $slot }}]?.y ?? 0" :y2="axis.ticks[{{ $slot }}]?.y ?? 0"
                            :class="axis.ticks[{{ $slot }}]?.zero ? 'stroke-base-content/30' : 'stroke-base-content/10'" stroke-width="1" vector-effect="non-scaling-stroke" />
                        <text x="{{ $left - 12 }}" :y="axis.ticks[{{ $slot }}]?.y ?? 0" text-anchor="end" dominant-baseline="middle"
                            class="fill-base-content/55" x-text="axis.ticks[{{ $slot }}]?.label ?? ''"></text>
                    </g>
                @endfor
            </g>

            {{-- Eixo X --}}
            <g font-size="13" class="tabular-nums">
                @foreach($xTicks as $tick)
                    <line x1="{{ $tick['x'] }}" x2="{{ $tick['x'] }}" y1="{{ $bottom }}" y2="{{ $bottom + 6 }}" class="stroke-base-content/30" vector-effect="non-scaling-stroke" />
                    <text x="{{ $tick['x'] }}" y="{{ $bottom + 24 }}" text-anchor="{{ $tick['anchor'] }}" class="fill-base-content/55">{{ $tick['label'] }}</text>
                @endforeach
            </g>

            {{-- Uma linha por conta --}}
            @foreach($accounts as $account)
                <polyline x-show="isOn({{ $account['id'] }})" :points="paths[{{ $account['id'] }}]" fill="none" stroke="{{ $account['color'] }}"
                    stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            @endforeach

            <rect x="0" y="0" width="{{ $width }}" height="{{ $height }}" fill="transparent" />

            {{-- Crosshair --}}
            <g x-show="active !== null && selected.length" x-cloak pointer-events="none">
                <line :x1="x(active ?? 0)" :x2="x(active ?? 0)" y1="{{ $top }}" y2="{{ $bottom }}" class="stroke-base-content/30" stroke-width="1" vector-effect="non-scaling-stroke" />
                @foreach($accounts as $account)
                    <circle x-show="isOn({{ $account['id'] }})" :cx="x(active ?? 0)" :cy="y(accounts[{{ $loop->index }}].values[active ?? 0])" r="5"
                        fill="{{ $account['color'] }}" class="stroke-base-100" stroke-width="2" />
                @endforeach
            </g>
        </svg>

        <div x-show="!selected.length" x-cloak class="absolute inset-0 grid place-items-center">
            <p class="rounded-lg bg-base-100/90 px-4 py-2 text-sm text-base-content/70">Selecione ao menos uma conta abaixo.</p>
        </div>

        <div x-show="active !== null && selected.length" x-cloak x-transition.opacity.duration.100ms
            class="pointer-events-none absolute z-10 min-w-56 rounded-xl border border-base-content/10 bg-base-100/95 px-3 py-2.5 text-sm shadow-lg backdrop-blur transition-[left,top] duration-75 ease-out"
            :style="active !== null && selected.length ? tooltipStyle() : ''">
            <p class="mb-1.5 font-semibold" x-text="dates[active ?? 0]"></p>
            <template x-for="row in (active !== null ? rows() : [])" :key="row.id">
                <p class="flex items-center justify-between gap-4">
                    <span class="flex min-w-0 items-center gap-2 text-base-content/70"><span class="size-2 shrink-0 rounded-full" :style="'background:' + row.color"></span><span class="truncate" x-text="row.name"></span></span>
                    <span class="tabular-nums font-medium" x-text="money(row.value)"></span>
                </p>
            </template>
            <p x-show="selected.length > 1" class="mt-1.5 flex items-center justify-between gap-4 border-t border-base-content/10 pt-1.5">
                <span class="whitespace-nowrap text-base-content/70">Total selecionado</span><span class="tabular-nums font-semibold" x-text="active !== null ? money(total()) : ''"></span>
            </p>
        </div>
    </div></div>
    <p class="sr-only" aria-live="polite" x-text="active !== null && selected.length ? dates[active] + ' | ' + rows().map(r => r.name + ': ' + money(r.value)).join(' | ') : ''"></p>

    {{-- Mini cards de filtro --}}
    <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium">Contas no gráfico <span class="text-base-content/50" x-text="'(' + selected.length + ' de ' + accounts.length + ')'"></span></p>
        <div class="flex gap-1">
            <button type="button" class="btn btn-ghost btn-xs" @click="all(true)" :disabled="selected.length === accounts.length">Todas</button>
            <button type="button" class="btn btn-ghost btn-xs" @click="all(false)" :disabled="!selected.length">Nenhuma</button>
        </div>
    </div>
    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($accounts as $account)
            @php
                $final = end($account['values']);
                $change = $final - $account['opening'];
            @endphp
            <button type="button" @click="toggle({{ $account['id'] }})" :aria-pressed="isOn({{ $account['id'] }})"
                class="group flex items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition hover:bg-base-content/5"
                :class="isOn({{ $account['id'] }}) ? 'border-base-content/20 bg-base-100 shadow-sm' : 'border-base-content/10 opacity-50'">
                <span class="mt-1 grid size-4 shrink-0 place-items-center rounded border-2" style="border-color: {{ $account['color'] }}"
                    :style="'border-color: {{ $account['color'] }};' + (isOn({{ $account['id'] }}) ? ' background: {{ $account['color'] }}' : '')">
                    <svg x-show="isOn({{ $account['id'] }})" viewBox="0 0 12 12" class="size-3 text-white" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.5 6.5l2.5 2.5 4.5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">{{ $account['name'] }}</span>
                    <span class="block tabular-nums text-base font-semibold">{{ $money($final) }}</span>
                    <span class="block text-xs tabular-nums {{ $change > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($change < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-base-content/50') }}">
                        {{ $change > 0 ? '▲ +' : ($change < 0 ? '▼ ' : '● ') }}{{ $money($change) }} no período
                    </span>
                </span>
            </button>
        @endforeach
    </div>
</div>
@endif
