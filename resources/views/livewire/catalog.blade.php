<div>
    <h1 class="page-title">{{ $title }}</h1>
    <p class="muted mt-3 mb-7">Organize seus cadastros. Desativar preserva os vinculos e o historico.</p>
    <x-feedback />
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
        <section class="panel min-w-0">
            <label class="field mb-5">Buscar por nome<input class="input w-full" wire:model.live.debounce.300ms="search" placeholder="Digite um nome" maxlength="100"></label>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nome</th><th>Tipo</th><th>Status</th><th>Acoes</th></tr></thead>
                    <tbody>
                    @forelse($records as $record)
                        <tr wire:key="record-{{ $record->id }}">
                            <td class="font-medium">{{ $record->name }}</td>
                            <td>{{ $types[$record->type] ?? $record->type }}</td>
                            <td><span class="badge badge-sm {{ $record->active ? 'badge-success badge-outline' : 'badge-ghost' }}">{{ $record->active ? 'Ativo' : 'Inativo' }}</span></td>
                            <td><div class="flex gap-1">
                                <button class="btn btn-ghost btn-xs" wire:click="edit({{ $record->id }})">Editar</button>
                                <button class="btn btn-ghost btn-xs" wire:click="toggle({{ $record->id }})" wire:confirm="Alterar a disponibilidade deste cadastro? O historico sera preservado." wire:loading.attr="disabled">{{ $record->active ? 'Desativar' : 'Ativar' }}</button>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center muted">Nenhum cadastro encontrado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $records->links() }}</div>
        </section>
        <section class="panel">
            <h2 class="mb-5 text-lg font-semibold">{{ $editing ? 'Editar cadastro' : 'Novo cadastro' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <label class="field">Nome<input class="input w-full" wire:model="form.name" required maxlength="255"></label>
                <label class="field">Tipo<select class="select w-full" wire:model="form.type">@foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                @if(isset($form['color']))
                    <label class="field">Cor<input class="input w-full" type="color" wire:model="form.color"></label>
                @endif
                @if($module === 'contas')
                    <label class="field">Saldo inicial<input class="input w-full" type="text" inputmode="decimal" wire:model="form.initial_balance" required><span class="muted">Use ponto decimal, sem milhar: 1250.00 ou -50.00</span></label>
                @endif
                @if($module === 'contatos')
                    <label class="field">CPF / CNPJ<input class="input w-full" wire:model="form.document" maxlength="18"></label>
                    <label class="field">E-mail<input class="input w-full" type="email" wire:model="form.email" maxlength="255"></label>
                    <label class="field">Telefone<input class="input w-full" wire:model="form.phone" maxlength="20"></label>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm" wire:model="form.active">Cadastro ativo</label>
                <x-mary-button type="submit" label="Salvar cadastro" class="btn-primary w-full" spinner="save" />
                @if($editing)<button type="button" class="btn btn-ghost w-full" wire:click="clearForm">Cancelar edicao</button>@endif
            </form>
        </section>
    </div>
</div>
