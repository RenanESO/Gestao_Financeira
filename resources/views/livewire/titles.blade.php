@php
    $columns = '@3xl:grid-cols-[minmax(0,1fr)_7.5rem_6.5rem_6.5rem_auto]';
@endphp
<div>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted mt-2 max-w-3xl">Cadastre compromissos e acompanhe as parcelas. Criar um título não movimenta o saldo.</p>
        </div>
        <span wire:loading class="loading loading-spinner loading-sm text-primary" aria-label="Atualizando"></span>
    </header>
    <x-feedback />
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
        <section class="panel min-w-0">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><x-mary-icon name="o-queue-list" />Títulos</h2>
                    <p class="section-subtitle">Arraste um título para a direita para registrar a baixa, ou para a esquerda para cancelar ou estornar. Os botões de cada linha continuam disponíveis.</p>
                </div>
            </div>
            <div class="mb-5 grid gap-4 md:grid-cols-2">
                <label class="field">Buscar descrição<input class="input w-full" wire:model.live.debounce.300ms="search" placeholder="Digite uma descrição" maxlength="100"></label>
                <label class="field">Status<select class="select w-full" wire:model.live="status"><option value="">Todos</option>@foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            </div>
            <div class="record-list">
                <div class="record-list__head {{ $columns }}" aria-hidden="true"><span>Título</span><span>Valor original</span><span>Vencimento inicial</span><span>Status</span><span class="text-right">Ações</span></div>
                <ul role="list" class="divide-y divide-base-content/10">
                    @forelse($records as $record)
                        @php
                            $hint = $shortcuts[$record->id];
                            $badge = \App\Support\Display::statusBadge($record->status);
                        @endphp
                        <x-swipe-row wire:key="title-{{ $record->id }}" :right="$hint['right']" :left="$hint['left']">
                            <div class="record-row {{ $columns }}">
                                <div class="record-row__main">
                                    <a class="link link-hover record-row__title" href="{{ route('titles.show', $record) }}">{{ $record->description }}</a>
                                    <p class="record-row__meta">{{ $record->title_number }} · {{ $record->installments_count }} parcela(s)@if($record->category) · {{ $record->category->name }}@endif</p>
                                </div>
                                <div><span class="record-row__label">Valor original</span><p class="whitespace-nowrap tabular-nums">{{ \App\Support\Display::money($record->original_value) }}</p></div>
                                <div><span class="record-row__label">Vencimento inicial</span><p>{{ $record->due_date->format('d/m/Y') }}</p></div>
                                <div><span class="status-badge swipe-tone--{{ $badge['tone'] }}"><x-mary-icon :name="$badge['icon']" aria-hidden="true" />{{ \App\Support\Display::status($record->status) }}</span></div>
                                <div class="record-row__actions">
                                    @if($hint['right']['tone'] === 'success')
                                        <button type="button" class="btn btn-ghost btn-xs text-success" wire:click="startSettlement({{ $record->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-banknotes" class="size-4" aria-hidden="true" />Baixar</button>
                                    @endif
                                    @if($hint['left']['tone'] === 'warning')
                                        <button type="button" class="btn btn-ghost btn-xs text-warning" wire:click="startReversalOrCancellation({{ $record->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-arrow-uturn-left" class="size-4" aria-hidden="true" />Estornar</button>
                                    @elseif($hint['left']['tone'] === 'error')
                                        <button type="button" class="btn btn-ghost btn-xs text-error" wire:click="startReversalOrCancellation({{ $record->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-no-symbol" class="size-4" aria-hidden="true" />Cancelar</button>
                                    @endif
                                    <a class="btn btn-ghost btn-xs" href="{{ route('titles.show', $record) }}"><x-mary-icon name="o-eye" class="size-4" aria-hidden="true" />Abrir</a>
                                </div>
                            </div>
                        </x-swipe-row>
                    @empty
                        <li class="py-10 text-center muted">Nenhum título encontrado. Cadastre seu primeiro compromisso.</li>
                    @endforelse
                </ul>
            </div>
            <div class="mt-5">{{ $records->links() }}</div>
        </section>
        <section class="panel">
            <h2 class="section-title mb-5"><x-mary-icon name="o-plus-circle" />{{ $type === 'receivable' ? 'Nova receita' : 'Nova despesa' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <label class="field">Descrição<input class="input w-full" wire:model="form.description" required maxlength="255"></label>
                <label class="field">Documento<input class="input w-full" wire:model="form.document_number" maxlength="50"></label>
                <label class="field">Referência<input class="input w-full" wire:model="form.reference" maxlength="255"></label>
                <label class="field">Observações<textarea class="textarea w-full" wire:model="form.notes" maxlength="10000"></textarea></label>
                <label class="field">Valor total<input class="input w-full" inputmode="decimal" wire:model="form.original_value" required placeholder="150.00"><span class="muted">Use ponto decimal e nenhum separador de milhar.</span></label>
                <label class="flex items-center gap-2 text-sm"><input class="checkbox checkbox-sm" type="checkbox" wire:model.live="form.is_recurring">Título recorrente</label>
                {{-- wire:key distintos: sem eles o morph do Livewire reaproveita campos pela posicao e mistura os vínculos. --}}
                @if($form['is_recurring'])
                    <div class="space-y-4" wire:key="schedule-recurring">
                        <label class="field" wire:key="recurrence-type">Recorrência<select class="select w-full" wire:model.live="form.recurrence_type"><option value="monthly">Mensal</option><option value="yearly">Anual</option></select></label>
                        <label class="field" wire:key="recurrence-day">Dia previsto<input class="input w-full" type="number" min="1" max="31" wire:model.live.debounce.400ms="form.recurrence_day" required></label>
                        @if($form['recurrence_type'] === 'yearly')
                            <label class="field" wire:key="recurrence-month">Mês previsto<input class="input w-full" type="number" min="1" max="12" wire:model.live.debounce.400ms="form.recurrence_month" required></label>
                        @endif
                        {{-- Recorrente: uma parcela por ciclo e vencimento calculado pelo dia/mês a partir da emissão. --}}
                        <label class="field" wire:key="recurring-installments">Parcelas<input class="input w-full" type="number" value="1" disabled aria-describedby="recurring-installments-hint">
                            <span id="recurring-installments-hint" class="muted">Uma parcela por ciclo.</span></label>
                        <label class="field" wire:key="recurring-issue">Emissão<x-date-input model="form.issue_date" :value="$form['issue_date']" required live /></label>
                        <label class="field" wire:key="recurring-due">Primeiro vencimento<input class="input w-full" type="text" value="{{ $recurringDue ?? 'Informe emissão e dia previsto' }}" disabled aria-describedby="recurring-due-hint">
                            <span id="recurring-due-hint" class="muted">Calculado: primeiro dia previsto a partir da emissão. Para um vencimento já passado, informe uma emissão anterior.</span></label>
                        <p class="muted">O próximo ciclo será criado somente na baixa total.</p>
                    </div>
                @else
                    <div class="space-y-4" wire:key="schedule-plain">
                        <label class="field">Parcelas<input class="input w-full" type="number" min="1" max="120" wire:model="form.installments_count" required></label>
                        <label class="field">Emissão<x-date-input model="form.issue_date" :value="$form['issue_date']" required /></label>
                        <label class="field">Primeiro vencimento<x-date-input model="form.due_date" :value="$form['due_date']" required /></label>
                    </div>
                @endif
                @foreach(['category_id' => ['Categoria', $categories], 'contact_id' => ['Contato', $contacts], 'financial_account_id' => ['Conta prevista', $accounts]] as $field => [$label, $options])
                    <label class="field">{{ $label }}<select class="select w-full" wire:model="form.{{ $field }}"><option value="">Sem vínculo</option>@foreach($options as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
                @endforeach
                <x-mary-button type="submit" label="Criar título" icon="o-plus" class="btn-primary w-full" spinner="save" />
            </form>
            <p class="muted mt-5">Parcelamento mensal. Abra o título na lista para registrar baixas, estornos, edições e recibos.</p>
        </section>
    </div>
    <x-confirm-modal :confirmation="$confirmation" />
    <x-gesture-help context="titles" />
</div>
