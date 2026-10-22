<div>
    <a class="link muted" href="{{ route($title->title_type === 'receivable' ? 'income' : 'expenses') }}">&larr; Voltar para {{ $title->title_type === 'receivable' ? 'receitas' : 'despesas' }}</a>
    <div class="my-5 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="page-title">{{ $title->description }}</h1><p class="muted mt-3">{{ $title->title_number }} · {{ \App\Support\Display::status($title->status) }}</p></div>
        @if($title->status !== 'cancelled')
            <div class="flex gap-2">
                <button class="btn btn-outline btn-sm" wire:click="openEdit" wire:loading.attr="disabled">Editar titulo</button>
                @if(!$hasActive && $title->status !== 'paid')
                    <button class="btn btn-outline btn-error btn-sm" wire:click="cancel" wire:confirm="Cancelar o titulo e suas parcelas? O historico sera preservado." wire:loading.attr="disabled">Cancelar titulo</button>
                @endif
            </div>
        @endif
    </div>
    <x-feedback />
    <div class="panel mb-6 grid gap-5 md:grid-cols-3">
        <div><p class="muted">Valor original</p><p class="mt-2 text-2xl font-semibold">{{ \App\Support\Display::money($title->original_value) }}</p></div>
        <div><p class="muted">Categoria / Contato</p><p class="mt-2">{{ $title->category?->name ?? 'Sem categoria' }} / {{ $title->contact?->name ?? 'Sem contato' }}</p></div>
        <div><p class="muted">Conta prevista</p><p class="mt-2">{{ $title->financialAccount?->name ?? 'Sem vinculo' }}</p></div>
        @if($title->is_recurring)
            <p class="muted md:col-span-3">Recorrencia {{ $title->recurrence_type === 'monthly' ? 'mensal' : 'anual' }}, dia {{ $title->recurrence_day }}. O proximo ciclo nasce na baixa total.</p>
        @endif
    </div>
    @if($editing)
        <section class="panel mb-6">
            <h2 class="mb-4 text-lg font-semibold">Editar titulo</h2>
            <p class="muted mb-4">{{ $hasHistory ? 'Com historico de baixa, apenas metadados podem mudar, mesmo apos estorno.' : 'Numero, tipo, quantidade e identidade das parcelas sao preservados.' }}</p>
            <form wire:submit="saveEdit" class="grid gap-4 md:grid-cols-2">
                <label class="field">Descricao<input class="input w-full" wire:model="edit.description" required maxlength="255"></label>
                <label class="field">Documento<input class="input w-full" wire:model="edit.document_number" maxlength="50"></label>
                <label class="field">Referencia<input class="input w-full" wire:model="edit.reference" maxlength="255"></label>
                <label class="field">Observacoes<textarea class="textarea w-full" wire:model="edit.notes" maxlength="10000"></textarea></label>
                @if(array_key_exists('original_value', $originalEdit))
                    <label class="field">Valor total<input class="input w-full" inputmode="decimal" wire:model="edit.original_value" required></label>
                    <label class="field">Emissao<input class="input w-full" type="date" wire:model="edit.issue_date" required></label>
                    <label class="field">Primeiro vencimento<input class="input w-full" type="date" wire:model="edit.due_date" required></label>
                    <label class="flex items-center gap-2 text-sm md:col-span-2"><input class="checkbox checkbox-sm" type="checkbox" wire:model.live="edit.is_recurring">Titulo recorrente</label>
                    @if($edit['is_recurring'])
                        <label class="field">Recorrencia<select class="select w-full" wire:model.live="edit.recurrence_type"><option value="monthly">Mensal</option><option value="yearly">Anual</option></select></label>
                        <label class="field">Dia previsto<input class="input w-full" type="number" min="1" max="31" wire:model="edit.recurrence_day" required></label>
                        @if($edit['recurrence_type'] === 'yearly')
                            <label class="field">Mes previsto<input class="input w-full" type="number" min="1" max="12" wire:model="edit.recurrence_month" required></label>
                        @endif
                        <p class="muted md:col-span-2">A recorrencia exige uma parcela e vencimento compativel com o dia e, na anual, o mes informado.</p>
                    @endif
                    @foreach(['category_id' => ['Categoria', $categories], 'contact_id' => ['Contato', $contacts], 'financial_account_id' => ['Conta prevista', $accounts]] as $field => [$label, $options])
                        <label class="field">{{ $label }}<select class="select w-full" wire:model="edit.{{ $field }}">
                            <option value="">Sem vinculo</option>
                            @if($edit[$field] !== '' && !$options->contains('id', $edit[$field]))
                                <option value="{{ $edit[$field] }}">Vinculo atual (inativo)</option>
                            @endif
                            @foreach($options as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                        </select></label>
                    @endforeach
                @endif
                <div class="flex gap-3 md:col-span-2"><x-mary-button label="Salvar alteracoes" type="submit" class="btn-primary" spinner="saveEdit" /><button type="button" class="btn btn-ghost" wire:click="closeEdit">Fechar edicao</button></div>
            </form>
        </section>
    @endif
    <section class="panel mb-6">
        <h2 class="mb-5 text-lg font-semibold">Parcelas</h2>
        <div class="table-wrap"><table class="table"><thead><tr><th>Parcela</th><th>Vencimento</th><th>Principal original</th><th>Principal restante</th><th>Status</th><th>Acao</th></tr></thead><tbody>
            @foreach($title->installments as $installment)
                <tr wire:key="installment-{{ $installment->id }}">
                    <td>{{ $installment->installment_number }}</td><td>{{ $installment->due_date->format('d/m/Y') }}</td>
                    <td>{{ \App\Support\Display::money($installment->value) }}</td><td>{{ \App\Support\Display::money($remaining[$installment->id]) }}</td><td>{{ \App\Support\Display::status($installment->status) }}</td>
                    <td>@if(in_array($title->status, ['pending', 'partial', 'overdue']) && in_array($installment->status, ['pending', 'overdue']))
                        <button class="btn btn-primary btn-xs" wire:click="openPayment({{ $installment->id }})" wire:loading.attr="disabled">Registrar baixa</button>
                    @endif</td>
                </tr>
            @endforeach
        </tbody></table></div>
    </section>
    @if($installmentId !== null)
        <section class="panel mb-6" wire:key="payment-{{ $paymentKey }}">
            <h2 class="text-lg font-semibold mb-2">Baixa da parcela {{ $title->installments->firstWhere('id', $installmentId)?->installment_number }}</h2>
            <p class="muted mb-5">Valores com ponto decimal, sem milhar. Total = principal - desconto + juros + multa. As formas devem somar exatamente esse total.</p>
            @if($completedPayment)
                <div role="status" class="mb-4 text-success">Pedido confirmado. Reenviar este mesmo pedido nao cria outra baixa.</div>
                <button class="btn btn-outline" wire:click="settle" wire:loading.attr="disabled">Reenviar mesmo pedido</button>
            @else
                <form wire:submit="settle" class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="field">Data da baixa<input class="input w-full" type="date" wire:model="payment.settlement_date" required></label>
                        <label class="field">Principal a baixar<input class="input w-full" inputmode="decimal" wire:model.live.debounce.300ms="payment.original_value" required></label>
                        @foreach(['discount' => 'Desconto', 'interest' => 'Juros', 'fine' => 'Multa'] as $field => $label)
                            <label class="field">{{ $label }}<input class="input w-full" inputmode="decimal" wire:model.live.debounce.300ms="payment.{{ $field }}" required></label>
                        @endforeach
                        <label class="field">Observacoes<textarea class="textarea w-full" wire:model="payment.notes" maxlength="10000"></textarea></label>
                    </div>
                    <div class="grid gap-3 rounded-xl border border-base-content/10 bg-base-200/40 p-4 sm:grid-cols-3">
                        <div><p class="muted">Total esperado</p><p class="mt-1 font-semibold">{{ $paymentSummary['total'] === null ? 'Preencha os valores' : \App\Support\Display::money($paymentSummary['total']) }}</p></div>
                        <div><p class="muted">Formas informadas</p><p class="mt-1 font-semibold">{{ $paymentSummary['methods'] === null ? 'Preencha as formas' : \App\Support\Display::money($paymentSummary['methods']) }}</p></div>
                        <div>
                            <p class="muted">Conferencia</p>
                            @if($paymentSummary['difference'] === 0)
                                <p class="mt-1 font-semibold text-success">Valores conferem</p>
                            @elseif($paymentSummary['difference'] !== null)
                                <p class="mt-1 font-semibold text-warning">{{ $paymentSummary['difference'] < 0 ? 'Faltam ' : 'Excedem ' }}{{ \App\Support\Display::money(\App\Support\Money::signedDecimal(abs($paymentSummary['difference']))) }}</p>
                            @else
                                <p class="mt-1 font-semibold text-warning">Complete os valores</p>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-semibold">Formas de {{ $title->title_type === 'receivable' ? 'recebimento' : 'pagamento' }}</h3>
                    @foreach($payment['payment_methods'] as $index => $row)
                        <div class="grid items-end gap-3 md:grid-cols-4" wire:key="method-{{ $paymentKey }}-{{ $index }}">
                            <label class="field">Forma {{ $index + 1 }}<select class="select w-full" wire:model="payment.payment_methods.{{ $index }}.payment_type_id" required><option value="">Selecione</option>@foreach($methods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select></label>
                            <label class="field">Conta<select class="select w-full" wire:model="payment.payment_methods.{{ $index }}.financial_account_id" required><option value="">Selecione</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></label>
                            <label class="field">Valor<input class="input w-full" inputmode="decimal" wire:model.live.debounce.300ms="payment.payment_methods.{{ $index }}.value" required></label>
                            @if(count($payment['payment_methods']) > 1)<button class="btn btn-ghost" type="button" wire:click="removeMethod({{ $index }})">Remover forma</button>@endif
                        </div>
                    @endforeach
                    <div class="flex flex-wrap gap-3">
                        @if(count($payment['payment_methods']) < 20)<button class="btn btn-outline" type="button" wire:click="addMethod">Adicionar forma</button>@endif
                        <x-mary-button label="Confirmar baixa" type="submit" class="btn-primary" spinner="settle" wire:confirm="Confirmar esta movimentacao financeira? Confira principal, ajustes, contas e formas." />
                    </div>
                    @if($accounts->isEmpty() || $methods->isEmpty())<p class="text-warning text-sm">Cadastre uma conta ativa e uma forma compativel antes de confirmar.</p>@endif
                </form>
            @endif
            <button class="btn btn-ghost mt-4" wire:click="closePayment" wire:confirm="Fechar este pedido? Se houve falha de conexao, confira o historico antes de criar outro pedido.">Fechar pedido</button>
        </section>
    @endif
    <section class="panel mb-6">
        <h2 class="text-lg font-semibold mb-5">Historico de baixas</h2>
        @forelse($title->settlements as $settlement)
            <article class="border-t border-base-content/10 py-5" wire:key="settlement-{{ $settlement->id }}">
                <div class="flex flex-wrap justify-between gap-4">
                    <div><h3 class="font-semibold">Baixa #{{ $settlement->id }} · {{ $settlement->settlement_date->format('d/m/Y') }} · {{ \App\Support\Display::money($settlement->total_value) }}</h3>
                        <p class="muted mt-2">Principal {{ \App\Support\Display::money($settlement->original_value) }} / desconto {{ \App\Support\Display::money($settlement->discount) }} / juros {{ \App\Support\Display::money($settlement->interest) }} / multa {{ \App\Support\Display::money($settlement->fine) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if(!$settlement->reversal)
                            <button class="btn btn-outline btn-sm" wire:click="emitReceipt({{ $settlement->id }})" wire:loading.attr="disabled">{{ $settlement->receipt ? 'Verificar recibo' : 'Emitir recibo PDF' }}</button>
                            <button class="btn btn-outline btn-error btn-sm" wire:click="openReversal({{ $settlement->id }})" wire:loading.attr="disabled">Estornar baixa</button>
                        @endif
                        @if($settlement->receipt && !$settlement->receipt->voided_at)
                            <a class="btn btn-primary btn-sm" href="{{ route('receipts.download', $settlement->receipt) }}">Baixar recibo</a>
                        @endif
                    </div>
                </div>
                @foreach($settlement->methods as $method)<p class="muted mt-2">{{ $method->paymentType?->name }} · {{ $method->financialAccount?->name }} · {{ \App\Support\Display::money($method->value) }}</p>@endforeach
                @if($settlement->notes)<p class="mt-3 text-sm whitespace-pre-wrap">{{ $settlement->notes }}</p>@endif
                @if($settlement->reversal)
                    <p class="mt-3 text-warning text-sm">Estornada em {{ $settlement->reversal->reversal_date->format('d/m/Y') }}: {{ $settlement->reversal->reason }}</p>
                @endif
                @if($settlement->receipt?->voided_at)<p class="muted mt-2">Recibo {{ $settlement->receipt->receipt_number }} invalidado, preservado no historico.</p>@endif
            </article>
        @empty<p class="muted">Nenhuma baixa registrada.</p>@endforelse
        <p class="muted mt-5">PDF emitido em operacao separada da baixa. Falhas de arquivo nao desfazem a movimentacao; arquivos invalidos nao sao regenerados silenciosamente.</p>
    </section>
    @if($reversalId !== null)
        <section class="panel mb-6" wire:key="reversal-{{ $reversalKey }}">
            <h2 class="text-lg font-semibold mb-3">Estornar baixa #{{ $reversalId }}</h2>
            <p class="muted mb-5">Estorno integral desta baixa, com auditoria. Invalida recibos e pode cancelar o proximo ciclo sem historico. Cadeias com movimentos exigem tratamento separado.</p>
            @if($completedReversal)
                <p role="status" class="text-success mb-4">Estorno confirmado.</p>
                <button class="btn btn-outline" wire:click="reverse" wire:loading.attr="disabled">Reenviar mesmo estorno</button>
            @else
                <form wire:submit="reverse" class="space-y-4">
                    <label class="field">Data do estorno<input class="input w-full md:max-w-xs" type="date" wire:model="reversal.reversal_date" required max="{{ now()->toDateString() }}"></label>
                    <label class="field">Motivo<textarea class="textarea w-full" wire:model="reversal.reason" required minlength="3" maxlength="2000"></textarea></label>
                    <x-mary-button label="Confirmar estorno" type="submit" class="btn-error" spinner="reverse" wire:confirm="Confirmar o estorno integral da baixa? O recibo sera invalidado e o principal reaberto." />
                </form>
            @endif
            <button class="btn btn-ghost mt-4" wire:click="closeReversal" wire:confirm="Fechar o pedido de estorno? Em caso de falha de conexao, confira o historico antes de iniciar outro.">Fechar estorno</button>
        </section>
    @endif
    @if($title->children->isNotEmpty())
        <section class="panel"><h2 class="text-lg font-semibold mb-4">Ciclos gerados</h2>
            @foreach($title->children as $child)
                <a class="block link mb-2" href="{{ route('titles.show', $child) }}">{{ $child->title_number }} · {{ $child->due_date->format('d/m/Y') }} · {{ \App\Support\Display::status($child->status) }}</a>
            @endforeach
        </section>
    @endif
</div>
