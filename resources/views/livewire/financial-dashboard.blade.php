@php
    $money = fn (int $cents) => \App\Support\Display::money(\App\Support\Money::signedDecimal($cents));
    $date = fn (string $value) => \Carbon\CarbonImmutable::parse($value)->format('d/m/Y');
    // Participação com indicacao "<1%" para valores pequenos que arredondariam para zero.
    $share = function (int $part, int $base): array {
        $percent = $base ? abs($part) * 100 / $base : 0;

        return [$percent, $percent > 0 && $percent < 1 ? '<1%' : round($percent).'%'];
    };
@endphp
<div>
    <div class="financial-dashboard__controls print:hidden">
        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="page-title">Dashboard financeira</h1>
                <p class="muted mt-2 max-w-3xl">Movimentações realizadas por data da baixa e do estorno. Títulos ainda sem baixa não entram na análise.</p>
            </div>
            <div class="flex items-center gap-3">
                <span wire:loading class="loading loading-spinner loading-sm text-primary" aria-label="Atualizando"></span>
                <span class="period-badge"><x-mary-icon name="o-calendar-days" class="size-4 text-primary" />{{ $date($data['filters']['start']) }} — {{ $date($data['filters']['end']) }}</span>
            </div>
        </header>
        <x-feedback />

        <section class="panel mb-6 space-y-5" aria-label="Filtros">
            <div class="section-header !mb-0">
                <div>
                    <h2 class="section-title"><x-mary-icon name="o-funnel" />Filtros</h2>
                    <p class="section-subtitle">Chips marcados estão incluídos; todos marcados = consolidado. Contatos sem seleção = todos. Chips valem na hora; datas e agrupamento entram em vigor ao aplicar.</p>
                </div>
            </div>
            <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-3">
                <label class="field">Data inicial<x-date-input model="filters.start" :value="$filters['start']" class="input" required /></label>
                <label class="field">Data final<x-date-input model="filters.end" :value="$filters['end']" class="input" required /></label>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled"><x-mary-icon name="o-check" class="size-4" />Aplicar filtros</button>
                <button class="btn btn-ghost" type="button" wire:click="clearFilters" wire:loading.attr="disabled"><x-mary-icon name="o-arrow-path" class="size-4" />Limpar filtros</button>
            </form>
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach(['excluded_categories' => ['Categorias', 'categories', 'o-tag'], 'excluded_methods' => ['Formas de pagamento', 'methods', 'o-credit-card'], 'excluded_accounts' => ['Contas financeiras', 'accounts', 'o-building-library']] as $field => [$label, $optionKey, $icon])
                    <fieldset>
                        @php $included = $options[$optionKey]->reject(fn ($option) => in_array($option->id, $filters[$field]))->count(); @endphp
                        <legend class="filter-group__legend"><x-mary-icon :name="$icon" class="size-4 text-base-content/50" />{{ $label }} <small>{{ $included }} de {{ $options[$optionKey]->count() }}</small></legend>
                        <div class="flex flex-wrap gap-2">
                            @forelse($options[$optionKey] as $option)
                                @php $selected = !in_array($option->id, $filters[$field]); @endphp
                                <button class="filter-chip {{ $selected ? 'is-on' : '' }}" type="button" aria-pressed="{{ $selected ? 'true' : 'false' }}" wire:click="toggle('{{ $field }}', {{ $option->id }})" wire:loading.attr="disabled">
                                    <x-mary-icon :name="$selected ? 'o-check-circle' : 'o-minus-circle'" />{{ $option->name }}
                                </button>
                            @empty <span class="muted">Nenhum cadastro.</span> @endforelse
                        </div>
                    </fieldset>
                @endforeach
            </div>
            <div x-data="{ open: false }" @click.outside="open = false">
                <label class="field max-w-lg"><span class="filter-group__legend !mb-0"><x-mary-icon name="o-users" class="size-4 text-base-content/50" />Contatos / clientes</span>
                    {{-- Busca filtrada no navegador; o termo segue para o servidor apenas na proxima acao. --}}
                    <input class="input w-full" type="search" wire:model="contactSearch" placeholder="Buscar contato para selecionar" maxlength="100" @focus="open = true" @keydown.escape="open = false" role="combobox" aria-autocomplete="list" aria-controls="contact-options" :aria-expanded="open">
                </label>
                <div id="contact-options" x-show="open" x-cloak class="mt-2 flex max-h-40 flex-wrap gap-2 overflow-y-auto rounded-xl border border-base-content/10 bg-base-200/50 p-3" role="listbox" aria-multiselectable="true" aria-label="Seleção múltipla de contatos">
                    @forelse($contactOptions as $contact)
                        @php $selected = in_array($contact->id, $filters['contacts']); @endphp
                        <button class="filter-chip {{ $selected ? 'is-on' : '' }}" type="button" role="option" aria-selected="{{ $selected ? 'true' : 'false' }}" wire:click="toggle('contacts', {{ $contact->id }})"
                            x-show="!$wire.contactSearch || {{ \Illuminate\Support\Js::from(mb_strtolower($contact->name)) }}.includes($wire.contactSearch.toLowerCase())">
                            <x-mary-icon :name="$selected ? 'o-check-circle' : 'o-plus-circle'" />{{ $contact->name }}
                        </button>
                    @empty <p class="muted">Nenhum contato encontrado.</p> @endforelse
                </div>
                @if($filters['contacts'])
                    <div class="mt-3 flex flex-wrap gap-2" aria-label="Contatos selecionados">
                        @foreach($options['contacts']->whereIn('id', $filters['contacts']) as $selectedContact)
                            <button class="filter-chip is-on" type="button" wire:click="toggle('contacts', {{ $selectedContact->id }})" aria-label="Remover contato {{ $selectedContact->name }}">{{ $selectedContact->name }}<x-mary-icon name="o-x-mark" /></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['income', 'Total de entradas', 'o-arrow-trending-up', 'Recebimentos menos estornos'],
                ['expense', 'Total de saídas', 'o-arrow-trending-down', 'Pagamentos menos estornos'],
                ['balance', 'Saldo líquido', 'o-scale', 'Entradas menos saídas no período'],
                ['count', 'Lançamentos', 'o-queue-list', 'Baixas e estornos distintos, não suas formas'],
            ] as [$key, $label, $icon, $caption])
                @php $value = $data['totals'][$key]; @endphp
                <article class="kpi-card kpi-card--{{ $key }}">
                    <div class="kpi-card__head"><p class="kpi-card__label">{{ $label }}</p><span class="kpi-card__icon"><x-mary-icon :name="$icon" /></span></div>
                    <p class="kpi-card__value tabular-nums {{ $key === 'balance' ? ($value > 0 ? 'value-positive' : ($value < 0 ? 'value-negative' : '')) : '' }}">{{ $key === 'count' ? $value : $money($value) }}</p>
                    <p class="kpi-card__caption">{{ $caption }}</p>
                </article>
            @endforeach
        </div>

        <section class="panel mb-6">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><x-mary-icon name="o-presentation-chart-line" />Evolução de entradas e saídas</h2>
                    <p class="section-subtitle">Valores diários de todo o período, conforme todos os filtros. Estornos podem gerar valores negativos.</p>
                </div>
            </div>
            <x-financial-timeline :daily="$data['daily']" />
            <details class="group mt-4 rounded-xl border border-base-content/10">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium">
                    <span class="flex items-center gap-2"><x-mary-icon name="o-table-cells" class="size-4 text-base-content/50" />Ver valores por data <small class="font-normal text-base-content/50">{{ count($data['movement_days']) }} {{ count($data['movement_days']) === 1 ? 'data' : 'datas' }} com movimentação</small></span>
                    <x-mary-icon name="o-chevron-down" class="size-4 transition group-open:rotate-180" />
                </summary>
                <div class="table-wrap max-h-64 border-t border-base-content/10"><table class="table table-sm table-pin-rows" aria-label="Valores por data com movimentação"><thead><tr><th>Data</th><th class="text-right">Entradas</th><th class="text-right">Saídas</th><th class="text-right">Saldo</th></tr></thead><tbody>
                    @forelse($data['movement_days'] as $day => $values)
                        @php $net = $values['income'] - $values['expense']; @endphp
                        <tr><td class="tabular-nums">{{ $date($day) }}</td><td class="text-right tabular-nums">{{ $money($values['income']) }}</td><td class="text-right tabular-nums">{{ $money($values['expense']) }}</td><td class="text-right font-medium tabular-nums {{ $net > 0 ? 'value-positive' : ($net < 0 ? 'value-negative' : '') }}">{{ $money($net) }}</td></tr>
                    @empty <tr><td colspan="4" class="text-center text-base-content/60">Nenhuma data com movimentação nos filtros aplicados.</td></tr> @endforelse
                </tbody></table></div>
            </details>
        </section>

        <section class="panel mb-6">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><x-mary-icon name="o-building-library" />Histórico de saldo por conta</h2>
                    <p class="section-subtitle">Saldo ao fim de cada dia conforme os filtros aplicados de período, conta, categoria, forma de pagamento e contato. A abertura inclui o saldo inicial da conta e os movimentos anteriores que atendem aos mesmos filtros. Com filtros restritivos, este saldo analítico não representa o saldo bancário completo.</p>
                </div>
            </div>
            <x-account-balance-chart :accounts="$data['accounts']" :dates="array_keys($data['daily'])" />
        </section>

        <div class="mb-6 grid gap-6 xl:grid-cols-2">
            @foreach(['categories' => ['Distribuição por categoria', 'o-tag'], 'methods' => ['Distribuição por forma de pagamento', 'o-credit-card']] as $key => [$label, $icon])
                <section class="panel">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title"><x-mary-icon :name="$icon" />{{ $label }}</h2>
                            <p class="section-subtitle">Participação nas entradas e nas saídas, sobre a magnitude dos valores líquidos após estornos. Maior participação primeiro.</p>
                        </div>
                    </div>
                    @php
                        $incomeBase = array_sum(array_map(fn($v) => abs($v['income']), $data[$key]));
                        $expenseBase = array_sum(array_map(fn($v) => abs($v['expense']), $data[$key]));
                    @endphp
                    <div class="max-h-[28rem] overflow-y-auto pr-1">
                        @forelse($data[$key] as $item)
                            <div class="share-item">
                                <h3 class="share-item__title">{{ $item['label'] }}</h3>
                                @foreach([['income', 'Entradas', $incomeBase, 'bg-emerald-500'], ['expense', 'Saídas', $expenseBase, 'bg-rose-500']] as [$side, $sideLabel, $base, $color])
                                    @php [$percent, $percentLabel] = $share($item[$side], $base); @endphp
                                    <div class="share-row" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($percent) }}" aria-label="Participação nas {{ mb_strtolower($sideLabel) }}: {{ $item['label'] }}">
                                        <span class="share-row__label">{{ $sideLabel }}</span>
                                        <span class="share-row__track"><span class="share-row__bar {{ $color }}" style="width: {{ round($percent, 2) }}%"></span></span>
                                        <span class="share-row__value">{{ $money($item[$side]) }}<span>{{ $percentLabel }}</span></span>
                                    </div>
                                @endforeach
                            </div>
                        @empty <p class="muted">Sem movimentos para os filtros aplicados.</p> @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <section class="panel">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><x-mary-icon name="o-document-chart-bar" />Relatório financeiro</h2>
                    <p class="section-subtitle">Gera uma visão tabular dos filtros atuais, pronta para imprimir ou salvar em PDF pelo navegador.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="field">Agrupamento do relatório<select class="select" wire:model="filters.grouping">@foreach($groupings as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <button class="btn btn-primary" wire:click="generateReport" wire:loading.attr="disabled"><x-mary-icon name="o-document-text" class="size-4" />Gerar relatório</button>
                @if($report)
                    <button class="btn btn-outline" type="button" x-data @click="window.print()"><x-mary-icon name="o-printer" class="size-4" />Imprimir / salvar PDF</button>
                    <button class="btn btn-ghost" wire:click="closeReport"><x-mary-icon name="o-x-mark" class="size-4" />Fechar relatório</button>
                @endif
            </div>
        </section>
    </div>
    @if($report)
        <section class="financial-report mt-6" aria-label="Relatório financeiro">
            <header class="financial-report__header">
                <div>
                    <p class="financial-report__brand">MoneyControl</p>
                    <h1>Relatório financeiro</h1>
                    <p class="financial-report__subtitle">Visão consolidada de movimentações realizadas</p>
                </div>
                <div class="financial-report__period">
                    <span>Período analisado</span>
                    <strong>{{ \Carbon\CarbonImmutable::parse($data['filters']['start'])->format('d/m/Y') }} — {{ \Carbon\CarbonImmutable::parse($data['filters']['end'])->format('d/m/Y') }}</strong>
                    <small>Gerado em {{ now()->format('d/m/Y \à\s H:i') }}</small>
                </div>
            </header>

            <div class="financial-report__kpis" aria-label="Indicadores do relatório">
                <article class="financial-report__kpi financial-report__kpi--income">
                    <span class="financial-report__kpi-label">Entradas realizadas</span>
                    <strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['income'])) }}</strong>
                </article>
                <article class="financial-report__kpi financial-report__kpi--expense">
                    <span class="financial-report__kpi-label">Saídas realizadas</span>
                    <strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['expense'])) }}</strong>
                </article>
                <article class="financial-report__kpi financial-report__kpi--balance">
                    <span class="financial-report__kpi-label">Saldo líquido</span>
                    <strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['balance'])) }}</strong>
                </article>
                <article class="financial-report__kpi financial-report__kpi--count">
                    <span class="financial-report__kpi-label">Lançamentos</span>
                    <strong>{{ $data['totals']['count'] }}</strong>
                </article>
            </div>

            <section class="financial-report__context" aria-label="Parâmetros do relatório">
                <div>
                    <span>Agrupamento</span>
                    <strong>{{ $groupings[$data['filters']['grouping']] }}</strong>
                </div>
                @foreach(['excluded_categories' => ['Categorias', 'categories', 'Exibindo todas'], 'excluded_methods' => ['Formas', 'methods', 'Exibindo todas'], 'excluded_accounts' => ['Contas', 'accounts', 'Exibindo todas'], 'contacts' => ['Contatos', 'contacts', 'Todos os contatos']] as $field => [$label, $optionKey, $empty])
                    @php $selected = $options[$optionKey]->whereIn('id', $data['filters'][$field])->pluck('name')->implode(', '); @endphp
                    <div>
                        <span>{{ $label }}</span>
                        <strong>{{ $selected ?: $empty }}</strong>
                    </div>
                @endforeach
            </section>

            <p class="financial-report__note">Valores realizados por baixa e estorno. Estornos aparecem como valores negativos na data em que ocorreram. Saldo inicial e títulos pendentes não fazem parte desta análise.</p>
            @forelse($data['groups'] as $group)
                @include('components.financial-report-group', ['group' => $group, 'level' => 0])
            @empty <p class="financial-report__empty">Nenhum movimento no período e filtros selecionados.</p> @endforelse
            <footer class="financial-report__total">
                <div><span>Total de entradas</span><strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['income'])) }}</strong></div>
                <div><span>Total de saídas</span><strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['expense'])) }}</strong></div>
                <div><span>Saldo líquido do período</span><strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['balance'])) }}</strong></div>
            </footer>
            <footer class="financial-report__footer">
                <span>Relatório emitido pelo sistema <strong>MoneyControl</strong></span>
                <span>Solicitado por <strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->email }}</span>
                <span>Emissão: {{ now()->format('d/m/Y \à\s H:i') }}</span>
            </footer>
        </section>
    @endif
</div>
