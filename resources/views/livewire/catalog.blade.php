@php
    $columns = '@3xl:grid-cols-[minmax(0,1fr)_7rem_6.5rem_auto]';
@endphp
<div>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="muted mt-2 max-w-3xl">Organize seus cadastros. Desativar preserva os vínculos e o histórico; a exclusão é lógica e pode ser desfeita.</p>
        </div>
        <span wire:loading class="loading loading-spinner loading-sm text-primary" aria-label="Atualizando"></span>
    </header>
    <x-feedback />
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
        <section class="panel min-w-0">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><x-mary-icon name="o-queue-list" />Registros</h2>
                    <p class="section-subtitle">Arraste um registro para a direita para editar, ou para a esquerda para excluir. Os botões de cada linha continuam disponíveis.</p>
                </div>
            </div>
            <div class="mb-5 flex flex-wrap items-end gap-4">
                <label class="field flex-1">Buscar por nome<input class="input w-full" wire:model.live.debounce.300ms="search" placeholder="Digite um nome" maxlength="100"></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm" wire:model.live="showDeleted">Mostrar excluídos</label>
            </div>
            <div class="record-list">
                <div class="record-list__head {{ $columns }}" aria-hidden="true"><span>Nome</span><span>Tipo</span><span>Status</span><span class="text-right">Ações</span></div>
                <ul role="list" class="divide-y divide-base-content/10">
                    @forelse($records as $record)
                        @php
                            $hint = $shortcuts[$record->id];
                            $deleted = $record->trashed();
                        @endphp
                        <x-swipe-row wire:key="record-{{ $record->id }}" :right="$hint['right']" :left="$hint['left']" :disabled="$deleted">
                            <div class="record-row {{ $columns }} {{ $deleted ? 'opacity-70' : '' }}">
                                <div class="record-row__main">
                                    <p class="record-row__title flex items-center gap-2">
                                        @if(!empty($record->color))<span class="size-2.5 shrink-0 rounded-full" style="background: {{ $record->color }}" aria-hidden="true"></span>@endif
                                        <span class="truncate">{{ $record->name }}</span>
                                    </p>
                                    @if($module === 'contatos')
                                        <p class="record-row__meta">{{ $record->code }}@if($record->email) · {{ $record->email }}@endif</p>
                                    @endif
                                </div>
                                <div><span class="record-row__label">Tipo</span><p>{{ $types[$record->type] ?? $record->type }}</p></div>
                                <div>
                                    @if($deleted)
                                        <span class="status-badge swipe-tone--error"><x-mary-icon name="o-trash" aria-hidden="true" />Excluído</span>
                                    @elseif($record->active)
                                        <span class="status-badge swipe-tone--success"><x-mary-icon name="o-check-circle" aria-hidden="true" />Ativo</span>
                                    @else
                                        <span class="status-badge swipe-tone--neutral"><x-mary-icon name="o-pause-circle" aria-hidden="true" />Inativo</span>
                                    @endif
                                </div>
                                <div class="record-row__actions col-span-2 @3xl:col-span-1">
                                    @if($deleted)
                                        <button type="button" class="btn btn-ghost btn-xs" wire:click="restore({{ $record->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-arrow-path" class="size-4" aria-hidden="true" />Restaurar</button>
                                    @else
                                        <button type="button" class="btn btn-ghost btn-xs" wire:click="edit({{ $record->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-pencil-square" class="size-4" aria-hidden="true" />Editar</button>
                                        <button type="button" class="btn btn-ghost btn-xs" wire:click="toggle({{ $record->id }})" wire:loading.attr="disabled"><x-mary-icon :name="$record->active ? 'o-pause-circle' : 'o-play-circle'" class="size-4" aria-hidden="true" />{{ $record->active ? 'Desativar' : 'Ativar' }}</button>
                                        <button type="button" class="btn btn-ghost btn-xs text-error" wire:click="confirmDelete({{ $record->id }})" wire:loading.attr="disabled"
                                            @if($hint['blocked']) title="Possui vínculos financeiros: desative para preservar o histórico" @endif><x-mary-icon name="o-trash" class="size-4" aria-hidden="true" />Excluir</button>
                                    @endif
                                </div>
                            </div>
                        </x-swipe-row>
                    @empty
                        <li class="py-10 text-center muted">Nenhum cadastro encontrado.</li>
                    @endforelse
                </ul>
            </div>
            <div class="mt-5">{{ $records->links() }}</div>
        </section>
        <section class="panel scroll-mt-6" x-data x-on:catalog-form-focus.window="$el.scrollIntoView({ behavior: 'smooth', block: 'start' }); $nextTick(() => $el.querySelector('input')?.focus({ preventScroll: true }))">
            <h2 class="section-title mb-5"><x-mary-icon :name="$editing ? 'o-pencil-square' : 'o-plus-circle'" />{{ $editing ? 'Editar cadastro' : 'Novo cadastro' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <label class="field">Nome<input class="input w-full" wire:model="form.name" required maxlength="255"></label>
                <label class="field">Tipo<select class="select w-full" wire:model="form.type">@foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                @if(isset($form['color']))
                    <label class="field">Cor<input class="input w-full" type="color" wire:model="form.color"></label>
                    <label class="field">Icone<input class="input w-full" wire:model="form.icon" maxlength="50" placeholder="o-building-library"></label>
                @endif
                @if($module === 'contas')
                    <label class="field">Saldo inicial<input class="input w-full" type="text" inputmode="decimal" wire:model="form.initial_balance" required><span class="muted">Use ponto decimal, sem milhar: 1250.00 ou -50.00</span></label>
                @endif
                @if($module === 'contatos')
                    <label class="field">CPF / CNPJ<input class="input w-full" wire:model="form.document" maxlength="18"></label>
                    <label class="field">E-mail<input class="input w-full" type="email" wire:model="form.email" maxlength="255"></label>
                    <label class="field">Telefone<input class="input w-full" wire:model="form.phone" maxlength="20"></label>
                    <label class="field">Endereço<input class="input w-full" wire:model="form.address" maxlength="255"></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="field">Bairro<input class="input w-full" wire:model="form.neighborhood" maxlength="100"></label>
                        <label class="field">CEP<input class="input w-full" wire:model="form.zip_code" maxlength="9" placeholder="00000-000"></label>
                    </div>
                    <div class="grid grid-cols-[1fr_5rem] gap-3">
                        <label class="field">Cidade<input class="input w-full" wire:model="form.city" maxlength="100"></label>
                        <label class="field">UF<input class="input w-full uppercase" wire:model="form.state" maxlength="2" placeholder="SP"></label>
                    </div>
                    <label class="field">Observações<textarea class="textarea w-full" wire:model="form.notes" maxlength="10000" rows="4"></textarea></label>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm" wire:model="form.active">Cadastro ativo</label>
                <x-mary-button type="submit" :label="$editing ? 'Salvar alterações' : 'Salvar cadastro'" :icon="$editing ? 'o-check' : 'o-plus'" class="btn-primary w-full" spinner="save" />
                @if($editing)<button type="button" class="btn btn-ghost w-full" wire:click="clearForm"><x-mary-icon name="o-x-mark" class="size-4" aria-hidden="true" />Cancelar edição</button>@endif
            </form>
        </section>
    </div>
    <x-confirm-modal :confirmation="$confirmation" />
    <x-gesture-help context="catalog" />
</div>
