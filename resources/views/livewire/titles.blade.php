<div>
    <h1 class="page-title">{{ $title }}</h1>
    <p class="muted mt-3 mb-7">Cadastre compromissos e acompanhe as parcelas. Criar um titulo nao movimenta o saldo.</p>
    <x-feedback />
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
        <section class="panel min-w-0">
            <div class="mb-5 grid gap-4 md:grid-cols-2">
                <label class="field">Buscar descricao<input class="input w-full" wire:model.live.debounce.300ms="search" placeholder="Digite uma descricao" maxlength="100"></label>
                <label class="field">Status<select class="select w-full" wire:model.live="status"><option value="">Todos</option>@foreach(['pending', 'partial', 'paid', 'overdue', 'cancelled'] as $value)<option value="{{ $value }}">{{ \App\Support\Display::status($value) }}</option>@endforeach</select></label>
            </div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Titulo</th><th>Valor original</th><th>Vencimento inicial</th><th>Status</th></tr></thead>
                <tbody>@forelse($records as $record)
                    <tr wire:key="title-{{ $record->id }}">
                        <td><a class="link font-medium" href="{{ route('titles.show', $record) }}">{{ $record->description }}</a><p class="muted">{{ $record->title_number }} · {{ $record->installments_count }} parcela(s)</p></td>
                        <td class="whitespace-nowrap">{{ \App\Support\Display::money($record->original_value) }}</td>
                        <td>{{ $record->due_date->format('d/m/Y') }}</td>
                        <td><span class="badge badge-sm badge-outline">{{ \App\Support\Display::status($record->status) }}</span></td>
                    </tr>
                @empty<tr><td colspan="4" class="py-10 text-center muted">Nenhum titulo encontrado. Cadastre seu primeiro compromisso.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="mt-5">{{ $records->links() }}</div>
        </section>
        <section class="panel">
            <h2 class="mb-5 text-lg font-semibold">{{ $type === 'receivable' ? 'Nova receita' : 'Nova despesa' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <label class="field">Descricao<input class="input w-full" wire:model="form.description" required maxlength="255"></label>
                <label class="field">Documento<input class="input w-full" wire:model="form.document_number" maxlength="50"></label>
                <label class="field">Referencia<input class="input w-full" wire:model="form.reference" maxlength="255"></label>
                <label class="field">Observacoes<textarea class="textarea w-full" wire:model="form.notes" maxlength="10000"></textarea></label>
                <label class="field">Valor total<input class="input w-full" inputmode="decimal" wire:model="form.original_value" required placeholder="150.00"><span class="muted">Use ponto decimal e nenhum separador de milhar.</span></label>
                <label class="field">Parcelas<input class="input w-full" type="number" min="1" max="120" wire:model="form.installments_count" required></label>
                <label class="field">Emissao<input class="input w-full" type="date" wire:model="form.issue_date" required></label>
                <label class="field">Primeiro vencimento<input class="input w-full" type="date" wire:model="form.due_date" required></label>
                <label class="flex items-center gap-2 text-sm"><input class="checkbox checkbox-sm" type="checkbox" wire:model.live="form.is_recurring">Titulo recorrente</label>
                @if($form['is_recurring'])
                    <label class="field">Recorrencia<select class="select w-full" wire:model.live="form.recurrence_type"><option value="monthly">Mensal</option><option value="yearly">Anual</option></select></label>
                    <label class="field">Dia previsto<input class="input w-full" type="number" min="1" max="31" wire:model="form.recurrence_day" required></label>
                    @if($form['recurrence_type'] === 'yearly')
                        <label class="field">Mes previsto<input class="input w-full" type="number" min="1" max="12" wire:model="form.recurrence_month" required></label>
                    @endif
                    <p class="muted">Recorrencia exige uma parcela e vencimento compativel com dia/mes. O proximo ciclo sera criado somente na baixa total.</p>
                @endif
                @foreach(['category_id' => ['Categoria', $categories], 'contact_id' => ['Contato', $contacts], 'financial_account_id' => ['Conta prevista', $accounts]] as $field => [$label, $options])
                    <label class="field">{{ $label }}<select class="select w-full" wire:model="form.{{ $field }}"><option value="">Sem vinculo</option>@foreach($options as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
                @endforeach
                <x-mary-button type="submit" label="Criar titulo" class="btn-primary w-full" spinner="save" />
            </form>
            <p class="muted mt-5">Parcelamento mensal. Abra o titulo na lista para registrar baixas, estornos, edicoes e recibos.</p>
        </section>
    </div>
</div>
