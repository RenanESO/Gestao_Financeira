<?php

namespace App\Livewire;

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

    public function mount(int $titleId): void
    {
        $this->titleId = $titleId;
        $this->record();
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
            throw ValidationException::withMessages(['status' => 'Parcela nao permite nova baixa.']);
        }
        if (! FinancialAccount::where('active', true)->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'Cadastre e ative uma conta financeira antes de registrar a baixa.',
            ]);
        }
        if (! PaymentType::where('active', true)->whereIn('type', [$paymentType, 'both'])->exists()) {
            throw ValidationException::withMessages([
                'payment' => 'Cadastre e ative uma forma compativel antes de registrar a baixa.',
            ]);
        }
        $remaining = $this->remaining($installment->load('settlements.reversal'));
        $this->resetValidation();
        $this->installmentId = $id;
        $this->paymentKey = (string) Str::uuid();
        $this->completedPayment = null;
        $this->payment = [
            'settlement_date' => now()->toDateString(), 'original_value' => $remaining,
            'discount' => '0.00', 'interest' => '0.00', 'fine' => '0.00', 'notes' => '',
            'payment_methods' => [['payment_type_id' => '', 'financial_account_id' => '', 'value' => $remaining]],
        ];
    }

    public function closePayment(): void
    {
        $this->installmentId = null;
        $this->paymentKey = null;
        $this->completedPayment = null;
        $this->payment = [];
        $this->resetValidation();
    }

    public function addMethod(): void
    {
        if ($this->completedPayment === null && $this->installmentId !== null
            && count($this->payment['payment_methods'] ?? []) < 20) {
            $this->payment['payment_methods'][] = ['payment_type_id' => '', 'financial_account_id' => '', 'value' => ''];
        }
    }

    public function removeMethod(int $index): void
    {
        if ($this->completedPayment === null && count($this->payment['payment_methods'] ?? []) > 1) {
            unset($this->payment['payment_methods'][$index]);
            $this->payment['payment_methods'] = array_values($this->payment['payment_methods']);
        }
    }

    public function settle(TitleService $service): void
    {
        abort_if($this->installmentId === null || $this->paymentKey === null, 422);
        $data = $this->completedPayment ?? [...$this->payment, 'idempotency_key' => $this->paymentKey];
        $service->settle($this->record(), $this->installment($this->installmentId), $data);
        // Manter chave e pedido apos sucesso permite repetir sem criar outra movimentacao.
        $this->completedPayment = $data;
        session()->flash('status', 'Baixa confirmada. O recibo pode ser emitido separadamente no historico.');
    }

    public function openReversal(int $id): void
    {
        $settlement = $this->settlement($id);
        if ($this->reversalId !== null && $this->completedReversal === null) {
            throw ValidationException::withMessages(['reversal' => 'Conclua ou descarte o estorno aberto antes de iniciar outro.']);
        }
        if ($settlement->reversal()->exists()) {
            throw ValidationException::withMessages(['reversal' => 'Esta baixa ja foi estornada.']);
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
        $this->resetValidation();
    }

    public function reverse(SettlementReversalService $service): void
    {
        abort_if($this->reversalId === null || $this->reversalKey === null, 422);
        $data = $this->completedReversal ?? [...$this->reversal, 'idempotency_key' => $this->reversalKey];
        $service->reverse($this->settlement($this->reversalId), $data);
        $this->completedReversal = $data;
        session()->flash('status', 'Estorno confirmado. Historico preservado e recibos invalidados.');
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
            throw ValidationException::withMessages(['receipt' => 'PDF ausente. A baixa foi preservada; o documento exige recuperacao explicita.']);
        } catch (FilesystemException $exception) {
            report($exception);
            throw ValidationException::withMessages(['receipt' => 'Falha no armazenamento do PDF. A baixa foi preservada; tente emitir novamente.']);
        }
        session()->flash('status', 'Recibo '.$receipt->receipt_number.' disponivel para download.');
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
                throw ValidationException::withMessages([$field => 'Campo nao disponivel nesta edicao.']);
            }
            if ((string) $value !== (string) $this->originalEdit[$field]) {
                $changes[$field] = $value === '' && in_array($field, [
                    'category_id', 'contact_id', 'financial_account_id', 'document_number', 'reference', 'notes',
                ], true) ? null : $value;
            }
        }
        $changes = $this->normalizeRecurrenceChanges($changes);
        $service->update($this->record(), $changes);
        $this->closeEdit();
        session()->flash('status', 'Titulo atualizado sem alterar a identidade das parcelas.');
    }

    public function cancel(TitleService $service): void
    {
        $service->cancel($this->record());
        $this->closeEdit();
        session()->flash('status', 'Titulo cancelado. Historico preservado.');
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
            return Money::cents($value);
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
