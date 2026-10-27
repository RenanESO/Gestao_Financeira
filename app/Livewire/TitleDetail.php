<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOperations;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Services\ReceiptService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use App\Support\Display;
use App\Support\Feedback;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TitleDetail extends Component
{
    use ConfirmsOperations;

    #[Locked]
    public int $titleId;

    #[Locked]
    public ?int $installmentId = null;

    #[Locked]
    public ?string $paymentKey = null;

    #[Locked]
    public ?array $completedPayment = null;

    #[Locked]
    public ?int $reversalId = null;

    #[Locked]
    public ?string $reversalKey = null;

    #[Locked]
    public ?array $completedReversal = null;

    #[Locked]
    public array $originalEdit = [];

    public array $payment = [];

    public array $reversal = [];

    public array $edit = [];

    #[Locked]
    public bool $editing = false;

    public function mount(int $titleId, ?string $action = null): void
    {
        $this->titleId = $titleId;
        $this->record();
        if ($action !== null) {
            $this->startShortcut($action);
        }
    }

    protected function confirmableOperations(): array
    {
        return [
            'settle' => 'settle', 'reverse' => 'reverse', 'cancel' => 'cancel',
            'discard-payment' => 'closePayment', 'discard-reversal' => 'closeReversal',
        ];
    }

    public function requestClosePayment(): void
    {
        // Pedido ja confirmado pode ser fechado sem aviso: nao ha nada pendente de envio.
        if ($this->completedPayment === null) {
            $this->askConfirmation('discard-payment');
        } else {
            $this->closePayment();
        }
    }

    public function requestCloseReversal(): void
    {
        if ($this->completedReversal === null) {
            $this->askConfirmation('discard-reversal');
        } else {
            $this->closeReversal();
        }
    }

    /**
     * Continuacao dos atalhos de arraste da listagem: abre o mesmo formulario dos botoes,
     * com as mesmas validacoes. Nada e gravado sem o envio e a confirmacao do usuario.
     */
    private function startShortcut(string $action): void
    {
        $title = $this->record()->load('installments', 'settlements.reversal');
        try {
            if ($action === 'baixa') {
                $installment = $title->installments->first(fn ($item) => in_array($item->status, ['pending', 'overdue'], true));
                if ($installment === null) {
                    throw ValidationException::withMessages(['status' => 'Nenhuma parcela em aberto para baixa.']);
                }
                $this->openPayment($installment->id);
            } elseif ($action === 'estorno') {
                $settlement = $title->settlements->filter(fn ($item) => $item->reversal === null)
                    ->sortByDesc(fn ($item) => [$item->settlement_date->toDateString(), $item->id])->first();
                if ($settlement === null) {
                    throw ValidationException::withMessages(['reversal' => 'Nenhuma baixa ativa para estornar.']);
                }
                $this->openReversal($settlement->id);
                Feedback::now('info', 'Informe o motivo do estorno.', 'Estorno da baixa #'.$settlement->id.', a mais recente ainda ativa.');
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    private function record(): Title
    {
        $title = Title::findOrFail($this->titleId);
        Gate::authorize('view', $title);

        return $title;
    }

    private function installment(int $id): TitleInstallment
    {
        return $this->record()->installments()->findOrFail($id);
    }

    private function settlement(int $id): TitleSettlement
    {
        return $this->record()->settlements()->findOrFail($id);
    }

    public function openPayment(int $id): void
    {
        $title = $this->record();
        $installment = $this->installment($id);
        $paymentType = $title->title_type === 'receivable' ? 'receipt' : 'payment';
        if ($this->installmentId !== null && $this->completedPayment === null) {
            throw ValidationException::withMessages(['payment' => 'Conclua ou descarte o pedido aberto antes de iniciar outro.']);
        }
        if (! in_array($title->status, ['pending', 'partial', 'overdue'], true)
            || ! in_array($installment->status, ['pending', 'overdue'], true)) {
            throw ValidationException::withMessages(['status' => 'Parcela não permite nova baixa.']);
        }
        if (! FinancialAccount::where('active', true)->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'Cadastre e ative uma conta financeira antes de registrar a baixa.',
            ]);
        }
        if (! PaymentType::where('active', true)->whereIn('type', [$paymentType, 'both'])->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'Cadastre e ative uma forma compatível antes de registrar a baixa.',
            ]);
        }
        $remaining = $this->remaining($installment->load('settlements.reversal'));
        $this->resetValidation();
        $this->installmentId = $id;
        $this->paymentKey = (string) Str::uuid();
        $this->completedPayment = null;
        $this->payment = [
            'settlement_date' => now()->toDateString(), 'original_value' => Display::decimalInput($remaining),
            'discount' => '0,00', 'interest' => '0,00', 'fine' => '0,00', 'notes' => '',
            'payment_methods' => [['payment_type_id' => '', 'financial_account_id' => '', 'value' => Display::decimalInput($remaining)]],
        ];
    }

    public function closePayment(): void
    {
        $this->installmentId = null;
        $this->paymentKey = null;
        $this->completedPayment = null;
        $this->payment = [];
        $this->dismissConfirmation();
        $this->resetValidation();
    }

    public function addMethod(): void
    {
        if ($this->completedPayment === null && $this->installmentId !== null
            && count($this->payment['payment_methods'] ?? []) < 20) {
            $this->payment['payment_methods'][] = ['payment_type_id' => '', 'financial_account_id' => '', 'value' => $this->missingValue()];
        }
    }

    /**
     * Sugestao para a nova forma: total esperado menos as formas ja informadas (vazio se nada falta).
     * E apenas um preenchimento inicial; o TitleService continua exigindo a soma exata.
     */
    private function missingValue(): string
    {
        $total = $this->paymentSummary()['total'];
        if ($total === null) {
            return '';
        }
        $informed = 0;
        foreach ($this->payment['payment_methods'] ?? [] as $method) {
            $informed += $this->previewCents($method['value'] ?? null) ?? 0;
        }
        $missing = Money::cents($total) - $informed;

        return $missing > 0 ? Display::decimalInput(Money::decimal($missing)) : '';
    }

    public function removeMethod(int $index): void
    {
        if ($this->completedPayment === null && count($this->payment['payment_methods'] ?? []) > 1) {
            unset($this->payment['payment_methods'][$index]);
            $this->payment['payment_methods'] = array_values($this->payment['payment_methods']);
        }
    }

    public function requestSettle(): void
    {
        abort_if($this->installmentId === null || $this->paymentKey === null, 422);
        $summary = $this->paymentSummary();
        $number = $this->installment($this->installmentId)->installment_number;
        $this->askConfirmation('settle', $this->installmentId, 'Parcela '.$number
            .($summary['total'] === null ? '' : ' · total '.Display::money($summary['total'])));
    }

    public function settle(TitleService $service): void
    {
        abort_if($this->installmentId === null || $this->paymentKey === null, 422);
        $replay = $this->completedPayment !== null;
        // Reenvio do mesmo pedido confirmado e idempotente; um pedido novo exige o modal.
        if ($replay) {
            $this->dismissConfirmation();
        } else {
            $this->consumeConfirmation('settle', $this->installmentId);
        }
        $data = $this->completedPayment ?? [...$this->payment, 'idempotency_key' => $this->paymentKey];
        if (! $replay) {
            foreach (['original_value', 'discount', 'interest', 'fine'] as $field) {
                $data[$field] = Display::internalDecimal($data[$field] ?? null);
            }
            foreach ($data['payment_methods'] ?? [] as $index => $method) {
                $data['payment_methods'][$index]['value'] = Display::internalDecimal($method['value'] ?? null);
            }
        }
        $service->settle($this->record(), $this->installment($this->installmentId), $data);
        // Manter chave e pedido apos sucesso permite repetir sem criar outra movimentacao.
        $this->completedPayment = $data;
        Feedback::now(...$replay
            ? ['info', 'Pedido já confirmado anteriormente.', 'O reenvio não criou outra baixa.']
            : ['settled', 'Baixa realizada com sucesso!', 'O recibo pode ser emitido separadamente no histórico.']);
    }

    public function openReversal(int $id): void
    {
        $settlement = $this->settlement($id);
        if ($this->reversalId !== null && $this->completedReversal === null) {
            throw ValidationException::withMessages(['reversal' => 'Conclua ou descarte o estorno aberto antes de iniciar outro.']);
        }
        if ($settlement->reversal()->exists()) {
            throw ValidationException::withMessages(['reversal' => 'Esta baixa já foi estornada.']);
        }
        $this->resetValidation();
        $this->reversalId = $id;
        $this->reversalKey = (string) Str::uuid();
        $this->completedReversal = null;
        $this->reversal = ['reversal_date' => now()->toDateString(), 'reason' => ''];
    }

    public function closeReversal(): void
    {
        $this->reversalId = null;
        $this->reversalKey = null;
        $this->completedReversal = null;
        $this->reversal = [];
        $this->dismissConfirmation();
        $this->resetValidation();
    }

    public function requestReverse(): void
    {
        abort_if($this->reversalId === null || $this->reversalKey === null, 422);
        $settlement = $this->settlement($this->reversalId);
        $this->askConfirmation('reverse', $settlement->id, 'Baixa #'.$settlement->id.' · '
            .$settlement->settlement_date->format('d/m/Y').' · '.Display::money($settlement->total_value));
    }

    public function reverse(SettlementReversalService $service): void
    {
        abort_if($this->reversalId === null || $this->reversalKey === null, 422);
        $replay = $this->completedReversal !== null;
        if ($replay) {
            $this->dismissConfirmation();
        } else {
            $this->consumeConfirmation('reverse', $this->reversalId);
        }
        $data = $this->completedReversal ?? [...$this->reversal, 'idempotency_key' => $this->reversalKey];
        $service->reverse($this->settlement($this->reversalId), $data);
        $this->completedReversal = $data;
        Feedback::now(...$replay
            ? ['info', 'Estorno já confirmado anteriormente.', 'O reenvio não criou outra movimentação.']
            : ['reversed', 'Estorno realizado com sucesso!', 'Histórico preservado e recibos invalidados.']);
    }

    public function emitReceipt(int $id, ReceiptService $service): void
    {
        $settlement = $this->settlement($id);
        try {
            $receipt = $service->generate($settlement);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }
            throw ValidationException::withMessages(['receipt' => 'PDF ausente. A baixa foi preservada; o documento exige recuperação explícita.']);
        } catch (FilesystemException $exception) {
            report($exception);
            throw ValidationException::withMessages(['receipt' => 'Falha no armazenamento do PDF. A baixa foi preservada; tente emitir novamente.']);
        }
        Feedback::now('receipt', 'Recibo emitido com sucesso!', $receipt->receipt_number.' disponível para download.');
    }

    public function openEdit(): void
    {
        $title = $this->record();
        $this->resetValidation();
        $this->edit = $title->only(['description', 'document_number', 'reference', 'notes']);
        if (! $title->settlements()->exists()) {
            $this->edit += $title->only([
                'original_value', 'category_id', 'contact_id', 'financial_account_id',
                'is_recurring', 'recurrence_type', 'recurrence_day', 'recurrence_month',
            ]);
            $this->edit['issue_date'] = $title->issue_date->toDateString();
            $this->edit['due_date'] = $title->due_date->toDateString();
        }
        foreach ($this->edit as $field => $value) {
            $this->edit[$field] = $value ?? '';
        }
        if (array_key_exists('original_value', $this->edit)) {
            $this->edit['original_value'] = Display::decimalInput($this->edit['original_value']);
        }
        $this->originalEdit = $this->edit;
        $this->editing = true;
    }

    public function closeEdit(): void
    {
        $this->editing = false;
        $this->edit = $this->originalEdit = [];
        $this->resetValidation();
    }

    public function saveEdit(TitleService $service): void
    {
        abort_unless($this->editing, 422);
        // Enviar somente mudancas preserva vinculos inativos e restricoes historicas.
        $changes = [];
        foreach ($this->edit as $field => $value) {
            if (! array_key_exists($field, $this->originalEdit)) {
                throw ValidationException::withMessages([$field => 'Campo não disponível nesta edição.']);
            }
            if ((string) $value !== (string) $this->originalEdit[$field]) {
                $changes[$field] = $value === '' && in_array($field, [
                    'category_id', 'contact_id', 'financial_account_id', 'document_number', 'reference', 'notes',
                ], true) ? null : $value;
            }
        }
        $changes = $this->normalizeRecurrenceChanges($changes);
        if (array_key_exists('original_value', $changes)) {
            $changes['original_value'] = Display::internalDecimal($changes['original_value']);
        }
        $service->update($this->record(), $changes);
        $this->closeEdit();
        Feedback::now('updated', 'Título alterado com sucesso!', 'A identidade das parcelas foi preservada.');
    }

    public function confirmCancel(): void
    {
        $title = $this->record();
        // Pre-validacao para informar o motivo antes do modal; o Service revalida sob lock.
        if ($title->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'O título já está cancelado.']);
        }
        if ($title->status === 'paid' || $title->settlements()->active()->exists()) {
            throw ValidationException::withMessages(['status' => 'Título com baixas ativas não pode ser cancelado. Estorne as baixas primeiro.']);
        }
        $this->askConfirmation('cancel', $title->id, $title->title_number.' · '.$title->description);
    }

    public function cancel(TitleService $service): void
    {
        $this->consumeConfirmation('cancel', $this->titleId);
        $title = $service->cancel($this->record());
        $this->closeEdit();
        Feedback::now('cancelled', 'Título cancelado com sucesso!', $title->title_number.' · histórico preservado.');
    }

    private function remaining(TitleInstallment $installment): string
    {
        $paid = 0;
        foreach ($installment->settlements as $settlement) {
            if ($settlement->reversal === null) {
                $paid += Money::cents($settlement->original_value);
            }
        }

        return Money::decimal(Money::cents($installment->value) - $paid, 'installments');
    }

    private function normalizeRecurrenceChanges(array $changes): array
    {
        $fields = ['is_recurring', 'recurrence_type', 'recurrence_day', 'recurrence_month'];
        if (array_intersect(array_keys($changes), $fields) === []) {
            return $changes;
        }

        $recurring = filter_var(
            $changes['is_recurring'] ?? $this->originalEdit['is_recurring'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        if (! $recurring) {
            $changes['is_recurring'] = false;
            $changes['recurrence_type'] = null;
            $changes['recurrence_day'] = null;
            $changes['recurrence_month'] = null;

            return $changes;
        }

        $changes['is_recurring'] = true;
        foreach (['recurrence_type', 'recurrence_day', 'recurrence_month'] as $field) {
            if (array_key_exists($field, $changes) && $changes[$field] === '') {
                $changes[$field] = null;
            }
        }
        $type = $changes['recurrence_type'] ?? $this->originalEdit['recurrence_type'] ?? null;
        if ($type === 'monthly') {
            $changes['recurrence_month'] = null;
        }

        return $changes;
    }

    private function paymentSummary(): array
    {
        $principal = $this->previewCents($this->payment['original_value'] ?? null);
        $discount = $this->previewCents($this->payment['discount'] ?? null);
        $interest = $this->previewCents($this->payment['interest'] ?? null);
        $fine = $this->previewCents($this->payment['fine'] ?? null);
        $valid = ! in_array(null, [$principal, $discount, $interest, $fine], true);
        $total = $valid ? $principal - $discount + $interest + $fine : null;
        if ($total !== null && $total <= 0) {
            $total = null;
            $valid = false;
        }

        $methods = 0;
        foreach ($this->payment['payment_methods'] ?? [] as $method) {
            $value = $this->previewCents($method['value'] ?? null);
            if ($value === null) {
                $valid = false;

                continue;
            }
            $methods += $value;
        }

        return [
            'valid' => $valid,
            'total' => $total === null ? null : Money::decimal($total),
            'methods' => $valid ? Money::decimal($methods) : null,
            'difference' => $total === null || ! $valid ? null : $methods - $total,
        ];
    }

    private function previewCents(mixed $value): ?int
    {
        try {
            return Money::cents(Display::internalDecimal($value));
        } catch (ValidationException) {
            return null;
        }
    }

    public function render()
    {
        $title = $this->record()->load('category', 'contact', 'financialAccount',
            'installments.settlements.reversal', 'settlements.reversal', 'settlements.receipt',
            'settlements.methods.paymentType', 'settlements.methods.financialAccount', 'children');
        $income = $title->title_type === 'receivable';
        $remaining = [];
        foreach ($title->installments as $installment) {
            $remaining[$installment->id] = $this->remaining($installment);
        }
        // Historico em ordem crescente de parcela; na mesma parcela, pela data e ordem da baixa.
        $numbers = $title->installments->pluck('installment_number', 'id');
        $title->setRelation('settlements', $title->settlements->sortBy(fn ($settlement) => [
            $numbers[$settlement->title_installment_id] ?? PHP_INT_MAX,
            $settlement->settlement_date->toDateString(),
            $settlement->id,
        ])->values());

        return view('livewire.title-detail', [
            'title' => $title, 'remaining' => $remaining,
            'paymentSummary' => $this->paymentSummary(),
            'hasHistory' => $title->settlements->isNotEmpty(),
            'hasActive' => $title->settlements->contains(fn ($settlement) => $settlement->reversal === null),
            'accounts' => FinancialAccount::where('active', true)->orderBy('name')->get(),
            'methods' => PaymentType::where('active', true)->whereIn('type', [$income ? 'receipt' : 'payment', 'both'])->orderBy('name')->get(),
            'categories' => Category::where('active', true)->whereIn('type', [$income ? 'income' : 'expense', 'both'])->orderBy('name')->get(),
            'contacts' => Contact::where('active', true)->whereIn('type', [$income ? 'client' : 'supplier', 'both'])->orderBy('name')->get(),
        ]);
    }
}
