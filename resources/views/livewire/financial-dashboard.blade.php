<div>
    <div class="financial-dashboard__controls print:hidden">
        <h1 class="page-title">Dashboard financeira</h1>
        <p class="muted my-4">Movimentacoes realizadas por data da baixa e do estorno. Nao inclui saldo inicial nem titulos ainda sem baixa.</p>
        <x-feedback />
        <section class="panel mb-6 space-y-5">
            <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-3">
                <label class="field">Data inicial<input class="input" type="date" wire:model="filters.start" required></label>
                <label class="field">Data final<input class="input" type="date" wire:model="filters.end" required></label>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Aplicar filtros</button>
                <button class="btn btn-ghost" type="button" wire:click="clearFilters">Limpar filtros</button>
            </form>
            <p class="muted">Chips marcados estao incluidos. Todos marcados = consolidado. Contatos sem selecao = todos. Datas entram em vigor ao aplicar.</p>
            @foreach(['excluded_categories' => ['Categorias', 'categories'], 'excluded_methods' => ['Formas de pagamento', 'methods'], 'excluded_accounts' => ['Contas financeiras', 'accounts']] as $field => [$label, $optionKey])
                <fieldset>
                    <legend class="mb-2 font-semibold">{{ $label }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @forelse($options[$optionKey] as $option)
                            @php
                                $selected = !in_array($option->id, $filters[$field]);
                            @endphp
                            <button class="btn btn-sm {{ $selected ? 'btn-primary btn-outline' : 'btn-ghost' }}" type="button" aria-pressed="{{ $selected ? 'true' : 'false' }}" wire:click="toggle('{{ $field }}', {{ $option->id }})">{{ $selected ? '[x] ' : '[ ] ' }}{{ $option->name }}</button>
                        @empty <span class="muted">Nenhum cadastro.</span> @endforelse
                    </div>
                </fieldset>
            @endforeach
            <div x-data="{ open: false }" @click.outside="open = false">
                <label class="field max-w-lg">Contatos / clientes
                    <input class="input w-full" type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Buscar contato para selecionar" maxlength="100" @focus="open = true" @keydown.escape="open = false" role="combobox" aria-autocomplete="list" aria-controls="contact-options" :aria-expanded="open">
                </label>
                <div id="contact-options" x-show="open" x-cloak class="mt-2 flex max-h-40 flex-wrap gap-2 overflow-y-auto rounded-lg border p-3" role="listbox" aria-multiselectable="true" aria-label="Selecao multipla de contatos">
                    @forelse($contactOptions as $contact)
                        <button class="btn btn-sm {{ in_array($contact->id, $filters['contacts']) ? 'btn-primary' : 'btn-outline' }}" type="button" role="option" aria-selected="{{ in_array($contact->id, $filters['contacts']) ? 'true' : 'false' }}" wire:click="toggle('contacts', {{ $contact->id }})">{{ $contact->name }}</button>
                    @empty <p class="muted">Nenhum contato encontrado.</p> @endforelse
                </div>
                @if($filters['contacts'])
                    <div class="mt-2 flex flex-wrap gap-2" aria-label="Contatos selecionados">
                        @foreach($options['contacts']->whereIn('id', $filters['contacts']) as $selectedContact)
                            <button class="btn btn-sm btn-primary" wire:click="toggle('contacts', {{ $selectedContact->id }})" aria-label="Remover contato {{ $selectedContact->name }}">{{ $selectedContact->name }} [x]</button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
        <p class="muted mb-4">Periodo aplicado: {{ $data['filters']['start'] }} a {{ $data['filters']['end'] }}. Quantidade conta baixas e estornos distintos, nao suas formas.</p>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach(['income' => 'Total de entradas', 'expense' => 'Total de saidas', 'balance' => 'Saldo liquido', 'count' => 'Lancamentos'] as $key => $label)
                <article class="panel"><p class="muted">{{ $label }}</p><p class="mt-3 text-2xl font-bold">{{ $key === 'count' ? $data['totals'][$key] : \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals'][$key])) }}</p></article>
            @endforeach
        </div>
        <section class="panel mb-6">
            <h2 class="mb-4 text-lg font-semibold">Evolucao de entradas e saidas</h2>
            <p class="muted mb-3">Entradas em verde, saidas em vermelho. Estornos podem gerar valores negativos.</p>
            <x-financial-timeline :daily="$data['daily']" />
            <details class="mt-3"><summary class="cursor-pointer">Ver valores por data</summary>
                <div class="table-wrap max-h-64"><table class="table" aria-label="Valores por data com movimentacao"><thead><tr><th>Data</th><th>Entradas</th><th>Saidas</th></tr></thead><tbody>
                    @forelse($data['movement_days'] as $date => $values)<tr><td>{{ $date }}</td><td>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($values['income'])) }}</td><td>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($values['expense'])) }}</td></tr>
                    @empty <tr><td colspan="3">Nenhuma data com movimentacao nos filtros aplicados.</td></tr> @endforelse
                </tbody></table></div>
            </details>
        </section>
        <div class="mb-6 grid gap-6 xl:grid-cols-2">
            @foreach(['categories' => 'Distribuicao por categoria', 'methods' => 'Distribuicao por forma de pagamento'] as $key => $label)
                <section class="panel"><h2 class="mb-3 text-lg font-semibold">{{ $label }}</h2>
                    <p class="muted mb-4">Percentuais por entradas e saidas, sobre a magnitude dos valores liquidos apos estornos.</p>
                    @php
                        $incomeBase = array_sum(array_map(fn($v) => abs($v['income']), $data[$key]));
                        $expenseBase = array_sum(array_map(fn($v) => abs($v['expense']), $data[$key]));
                    @endphp
                    @forelse($data[$key] as $item)
                        @php
                            $incomePercent = $incomeBase ? (int) round(abs($item['income']) * 100 / $incomeBase) : 0;
                            $expensePercent = $expenseBase ? (int) round(abs($item['expense']) * 100 / $expenseBase) : 0;
                        @endphp
                        <div class="mb-4"><h3 class="font-medium">{{ $item['label'] }}</h3>
                            <p class="muted">Entradas {{ \App\Support\Display::money(\App\Support\Money::signedDecimal($item['income'])) }} ({{ $incomePercent }}%)</p>
                            <progress class="progress progress-success w-full" max="100" value="{{ $incomePercent }}" aria-label="Participacao nas entradas: {{ $item['label'] }}"></progress>
                            <p class="muted">Saidas {{ \App\Support\Display::money(\App\Support\Money::signedDecimal($item['expense'])) }} ({{ $expensePercent }}%)</p>
                            <progress class="progress progress-error w-full" max="100" value="{{ $expensePercent }}" aria-label="Participacao nas saidas: {{ $item['label'] }}"></progress>
                        </div>
                    @empty <p class="muted">Sem movimentos para os filtros aplicados.</p> @endforelse
                </section>
            @endforeach
        </div>
        <div class="panel flex flex-wrap items-end gap-3">
            <label class="field">Agrupamento do relatorio<select class="select" wire:model="filters.grouping">@foreach($groupings as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <button class="btn btn-primary" wire:click="generateReport" wire:loading.attr="disabled">Gerar relatorio</button>
            @if($report)<button class="btn btn-outline" type="button" x-data @click="window.print()">Imprimir / salvar PDF</button><button class="btn btn-ghost" wire:click="closeReport">Fechar relatorio</button>@endif
        </div>
    </div>
    @if($report)
        <section class="financial-report mt-6" aria-label="Relatorio financeiro">
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
                    @php($selected = $options[$optionKey]->whereIn('id', $data['filters'][$field])->pluck('name')->implode(', '))
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
                <div><span>Saldo final do período</span><strong>{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($data['totals']['balance'])) }}</strong></div>
            </footer>
            <footer class="financial-report__footer">
                <span>Relatório emitido pelo sistema <strong>MoneyControl</strong></span>
                <span>Solicitado por <strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->email }}</span>
                <span>Emissão: {{ now()->format('d/m/Y \à\s H:i') }}</span>
            </footer>
        </section>
    @endif
</div>
