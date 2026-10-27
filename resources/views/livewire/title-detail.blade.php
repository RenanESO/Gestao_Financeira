<div>
    <a class="link muted" href="{{ route($title->title_type === 'receivable' ? 'income' : 'expenses') }}">&larr; Voltar para {{ $title->title_type === 'receivable' ? 'receitas' : 'despesas' }}</a>
    <div class="my-5 flex flex-wrap items-start justify-between gap-4">
        @php $badge = \App\Support\Display::statusBadge($title->status); @endphp
        <div><h1 class="page-title">{{ $title->description }}</h1><p class="muted mt-3 flex flex-wrap items-center gap-2">{{ $title->title_number }} <span class="status-badge swipe-tone--{{ $badge['tone'] }}"><x-mary-icon :name="$badge['icon']" aria-hidden="true" />{{ \App\Support\Display::status($title->status) }}</span></p></div>
        @if($title->status !== 'cancelled')
            <div class="flex gap-2">
                <button class="btn btn-outline btn-sm" wire:click="openEdit" wire:loading.attr="disabled"><x-mary-icon name="o-pencil-square" class="size-4" aria-hidden="true" />Editar título</button>
                @if(!$hasActive && $title->status !== 'paid')
                    <button class="btn btn-outline btn-error btn-sm" wire:click="confirmCancel" wire:loading.attr="disabled"><x-mary-icon name="o-no-symbol" class="size-4" aria-hidden="true" />Cancelar título</button>
                @endif
            </div>
        @endif
    </div>
    <x-feedback />
    <div class="panel mb-6 grid gap-5 md:grid-cols-3">
        <div><p class="muted">Valor original</p><p class="mt-2 text-2xl font-semibold">{{ \App\Support\Display::money($title->original_value) }}</p></div>
        <div><p class="muted">Categoria / Contato</p><p class="mt-2">{{ $title->category?->name ?? 'Sem categoria' }} / {{ $title->contact?->name ?? 'Sem contato' }}</p></div>
        <div><p class="muted">Conta prevista</p><p class="mt-2">{{ $title->financialAccount?->name ?? 'Sem vínculo' }}</p></div>
        @if($title->is_recurring)
            <p class="muted md:col-span-3">Recorrência {{ $title->recurrence_type === 'monthly' ? 'mensal' : 'anual' }}, dia {{ $title->recurrence_day }}. O próximo ciclo nasce na baixa total.</p>
        @endif
    </div>
    @if($editing)
        <section class="panel mb-6">
            <h2 class="mb-4 text-lg font-semibold">Editar título</h2>
            <p class="muted mb-4">{{ $hasHistory ? 'Com histórico de baixa, apenas metadados podem mudar, mesmo após estorno.' : 'Número, tipo, quantidade e identidade das parcelas são preservados.' }}</p>
            <form wire:submit="saveEdit" class="grid gap-4 md:grid-cols-2">
                <label class="field">Descrição<input class="input w-full" wire:model="edit.description" required maxlength="255"></label>
                <label class="field">Documento<input class="input w-full" wire:model="edit.document_number" maxlength="50"></label>
                <label class="field">Referência<input class="input w-full" wire:model="edit.reference" maxlength="255"></label>
                <label class="field">Observações<textarea class="textarea w-full" wire:model="edit.notes" maxlength="10000"></textarea></label>
                @if(array_key_exists('original_value', $originalEdit))
                    <label class="field">Valor total<input class="input w-full" inputmode="decimal" wire:model="edit.original_value" required></label>
                    <label class="field">Emissão<x-date-input model="edit.issue_date" :value="$edit['issue_date']" required /></label>
                    <label class="field">Primeiro vencimento<x-date-input model="edit.due_date" :value="$edit['due_date']" required /></label>
                    <label class="flex items-center gap-2 text-sm md:col-span-2"><input class="checkbox checkbox-sm" type="checkbox" wire:model.live="edit.is_recurring">Título recorrente</label>
                    @if($edit['is_recurring'])
                        <label class="field" wire:key="edit-recurrence-type">Recorrência<select class="select w-full" wire:model.live="edit.recurrence_type"><option value="monthly">Mensal</option><option value="yearly">Anual</option></select></label>
                        <label class="field" wire:key="edit-recurrence-day">Dia previsto<input class="input w-full" type="number" min="1" max="31" wire:model="edit.recurrence_day" required></label>
                        @if($edit['recurrence_type'] === 'yearly')
                            <label class="field" wire:key="edit-recurrence-month">Mês previsto<input class="input w-full" type="number" min="1" max="12" wire:model="edit.recurrence_month" required></label>
                        @endif
                        <p class="muted md:col-span-2" wire:key="edit-recurrence-hint">A recorrência exige uma parcela e vencimento compatível com o dia e, na anual, o mês informado.</p>
                    @endif
                    @foreach(['category_id' => ['Categoria', $categories], 'contact_id' => ['Contato', $contacts], 'financial_account_id' => ['Conta prevista', $accounts]] as $field => [$label, $options])
                        <label class="field" wire:key="edit-{{ $field }}">{{ $label }}<select class="select w-full" wire:model="edit.{{ $field }}">
                            <option value="">Sem vínculo</option>
                            @if($edit[$field] !== '' && !$options->contains('id', $edit[$field]))
                                <option value="{{ $edit[$field] }}">Vínculo atual (inativo)</option>
                            @endif
                            @foreach($options as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                        </select></label>
                    @endforeach
                @endif
                <div class="flex gap-3 md:col-span-2"><x-mary-button label="Salvar alterações" type="submit" class="btn-primary" spinner="saveEdit" /><button type="button" class="btn btn-ghost" wire:click="closeEdit">Fechar edição</button></div>
            </form>
        </section>
    @endif
    <section class="panel mb-6">
        <h2 class="mb-5 text-lg font-semibold">Parcelas</h2>
        <div class="table-wrap"><table class="table"><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor da parcela</th><th>Data da baixa</th><th>Status</th><th>Ação</th></tr></thead><tbody>
            @foreach($title->installments as $installment)
                @php
                    // Baixas estornadas não contam; parcela pode ter varias baixas parciais.
                    $paidDates = $installment->settlements->filter(fn ($item) => $item->reversal === null)
                        ->sortBy(fn ($item) => [$item->settlement_date->toDateString(), $item->id]);
                    $left = \App\Support\Money::cents($remaining[$installment->id]);
                    $partial = $left > 0 && $left < \App\Support\Money::cents($installment->value);
                @endphp
                <tr wire:key="installment-{{ $installment->id }}">
                    <td>{{ $installment->installment_number }}</td><td>{{ $installment->due_date->format('d/m/Y') }}</td>
                    <td class="whitespace-nowrap">{{ \App\Support\Display::money($installment->value) }}</td>
                    <td>@forelse($paidDates as $paid)<span class="block whitespace-nowrap">{{ $paid->settlement_date->format('d/m/Y') }}</span>@empty<span class="muted">—</span>@endforelse</td>
                    <td>{{ \App\Support\Display::status($installment->status) }}
                        @if($partial)<small class="muted block whitespace-nowrap">Restante {{ \App\Support\Display::money($remaining[$installment->id]) }}</small>@endif
                    </td>
                    <td>@if(in_array($title->status, ['pending', 'partial', 'overdue']) && in_array($installment->status, ['pending', 'overdue']))
                        <button class="btn btn-primary btn-xs" wire:click="openPayment({{ $installment->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-banknotes" class="size-4" aria-hidden="true" />Registrar baixa</button>
                    @endif</td>
                </tr>
            @endforeach
        </tbody></table></div>
    </section>
    @if($installmentId !== null)
        <section class="panel mb-6 scroll-mt-6" wire:key="payment-{{ $paymentKey }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <h2 class="text-lg font-semibold mb-2">Baixa da parcela {{ $title->installments->firstWhere('id', $installmentId)?->installment_number }}</h2>
            <p class="muted mb-5">Valores com vírgula decimal. Total = principal - desconto + juros + multa. As formas devem somar exatamente esse total.</p>
            @if($completedPayment)
                <div role="status" class="mb-4 text-success">Pedido confirmado. Reenviar este mesmo pedido não cria outra baixa.</div>
                <button class="btn btn-outline" wire:click="settle" wire:loading.attr="disabled">Reenviar mesmo pedido</button>
            @else
                <form wire:submit="requestSettle" class="space-y-5">
                    @php
                        $paymentInstallment = $title->installments->firstWhere('id', $installmentId);
                        $dueIn = (int) now()->startOfDay()->diffInDays($paymentInstallment->due_date, false);
                    @endphp
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="field">Vencimento da parcela
                            <input class="input w-full bg-base-200/60" type="text" value="{{ $paymentInstallment->due_date->format('d/m/Y') }}" readonly aria-describedby="payment-due-hint">
                            <span id="payment-due-hint" class="flex items-center gap-1 text-xs font-normal {{ $dueIn < 0 ? 'text-error' : ($dueIn === 0 ? 'text-warning' : 'text-base-content/60') }}">
                                <x-mary-icon :name="$dueIn < 0 ? 'o-exclamation-triangle' : 'o-calendar-days'" class="size-4" aria-hidden="true" />
                                {{ $dueIn < 0 ? 'Vencida há '.abs($dueIn).' dia(s)' : ($dueIn === 0 ? 'Vence hoje' : 'Vence em '.$dueIn.' dia(s)') }}
                            </span>
                        </label>
                        <label class="field">Data da baixa<x-date-input model="payment.settlement_date" :value="$payment['settlement_date']" required /></label>
                        <label class="field">Principal a baixar<input class="input w-full" inputmode="decimal" wire:model.live.debounce.300ms="payment.original_value" required></label>
                        @foreach(['discount' => 'Desconto', 'interest' => 'Juros', 'fine' => 'Multa'] as $field => $label)
                            <label class="field">{{ $label }}<input class="input w-full" inputmode="decimal" wire:model.live.debounce.300ms="payment.{{ $field }}" required></label>
                        @endforeach
                        <label class="field">Observações<textarea class="textarea w-full" wire:model="payment.notes" maxlength="10000"></textarea></label>
                    </div>
                    <div class="grid gap-3 rounded-xl border border-base-content/10 bg-base-200/40 p-4 sm:grid-cols-3">
                        <div><p class="muted">Total esperado</p><p class="mt-1 font-semibold">{{ $paymentSummary['total'] === null ? 'Preencha os valores' : \App\Support\Display::money($paymentSummary['total']) }}</p></div>
                        <div><p class="muted">Formas informadas</p><p class="mt-1 font-semibold">{{ $paymentSummary['methods'] === null ? 'Preencha as formas' : \App\Support\Display::money($paymentSummary['methods']) }}</p></div>
                        <div>
                            <p class="muted">Conferência</p>
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
                        <x-mary-button label="Revisar e confirmar baixa" type="submit" icon="o-banknotes" class="btn-primary" spinner="requestSettle" />
                    </div>
                    @if($accounts->isEmpty() || $methods->isEmpty())<p class="text-warning text-sm">Cadastre uma conta ativa e uma forma compatível antes de confirmar.</p>@endif
                </form>
            @endif
            <button class="btn btn-ghost mt-4" wire:click="requestClosePayment" wire:loading.attr="disabled"><x-mary-icon name="o-x-mark" class="size-4" aria-hidden="true" />Fechar pedido</button>
        </section>
    @endif
    <section class="panel mb-6">
        <h2 class="text-lg font-semibold mb-5">Histórico de baixas</h2>
        @forelse($title->settlements as $settlement)
            @php
                $parcel = $title->installments->firstWhere('id', $settlement->title_installment_id);
            @endphp
            <article class="border-t border-base-content/10 py-5" wire:key="settlement-{{ $settlement->id }}">
                <div class="flex flex-wrap justify-between gap-4">
                    <div><h3 class="font-semibold">{{ $title->title_number }}/{{ $parcel?->installment_number ?? '—' }} · {{ $settlement->settlement_date->format('d/m/Y') }} · {{ $parcel ? \App\Support\Display::money($parcel->value) : '—' }}</h3>
                        <p class="muted mt-2">Baixa #{{ $settlement->id }} · Vencimento {{ $parcel?->due_date->format('d/m/Y') ?? '—' }} · Desconto {{ \App\Support\Display::money($settlement->discount) }} · Juros {{ \App\Support\Display::money($settlement->interest) }} / Multa {{ \App\Support\Display::money($settlement->fine) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if(!$settlement->reversal && $reversalId !== $settlement->id)
                            <button class="btn btn-outline btn-sm" wire:click="emitReceipt({{ $settlement->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-document-check" class="size-4" aria-hidden="true" />{{ $settlement->receipt ? 'Verificar recibo' : 'Emitir recibo PDF' }}</button>
                            <button class="btn btn-outline btn-warning btn-sm" wire:click="openReversal({{ $settlement->id }})" wire:loading.attr="disabled"><x-mary-icon name="o-arrow-uturn-left" class="size-4" aria-hidden="true" />Estornar baixa</button>
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
                @if($settlement->receipt?->voided_at)<p class="muted mt-2">Recibo {{ $settlement->receipt->receipt_number }} invalidado, preservado no histórico.</p>@endif
            </article>
        @empty<p class="muted">Nenhuma baixa registrada.</p>@endforelse
        <p class="muted mt-5">PDF emitido em operação separada da baixa. Falhas de arquivo não desfazem a movimentação; arquivos inválidos não são regenerados silenciosamente.</p>
    </section>
    @if($reversalId !== null)
        <section class="panel mb-6 scroll-mt-6" wire:key="reversal-{{ $reversalKey }}" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <h2 class="text-lg font-semibold mb-3">Estornar baixa #{{ $reversalId }}</h2>
            <p class="muted mb-5">Estorno integral desta baixa, com auditoria. Invalida recibos e pode cancelar o próximo ciclo sem histórico. Cadeias com movimentos exigem tratamento separado.</p>
            @if($completedReversal)
                <p role="status" class="text-success mb-4">Estorno confirmado.</p>
                <button class="btn btn-outline" wire:click="reverse" wire:loading.attr="disabled">Reenviar mesmo estorno</button>
            @else
                <form wire:submit="requestReverse" class="space-y-4">
                    <label class="field">Data do estorno<x-date-input model="reversal.reversal_date" :value="$reversal['reversal_date']" class="input w-full md:max-w-xs" :max="now()->toDateString()" required /></label>
                    <label class="field">Motivo<textarea class="textarea w-full" wire:model="reversal.reason" required minlength="3" maxlength="2000"></textarea></label>
                    <x-mary-button label="Revisar e confirmar estorno" type="submit" icon="o-arrow-uturn-left" class="btn-warning" spinner="requestReverse" />
                </form>
            @endif
            <button class="btn btn-ghost mt-4" wire:click="requestCloseReversal" wire:loading.attr="disabled"><x-mary-icon name="o-x-mark" class="size-4" aria-hidden="true" />Fechar estorno</button>
        </section>
    @endif
    @if($title->children->isNotEmpty())
        <section class="panel"><h2 class="text-lg font-semibold mb-4">Ciclos gerados</h2>
            @foreach($title->children as $child)
                <a class="block link mb-2" href="{{ route('titles.show', $child) }}">{{ $child->title_number }} · {{ $child->due_date->format('d/m/Y') }} · {{ \App\Support\Display::status($child->status) }}</a>
            @endforeach
        </section>
    @endif
    <x-confirm-modal :confirmation="$confirmation" />
</div>
