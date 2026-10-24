<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOperations;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\Title;
use App\Services\RecurrenceService;
use App\Services\TitleService;
use App\Support\Display;
use App\Support\Feedback;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Titles extends Component
{
    use ConfirmsOperations;
    use WithPagination;

    private const OPEN = ['pending', 'partial', 'overdue'];

    #[Locked]
    public string $type;

    public string $search = '';

    public string $status = '';

    public array $form = [];

    public function mount(string $type): void
    {
        abort_unless(in_array($type, ['receivable', 'payable'], true), 404);
        $this->type = $type;
        $this->clearForm();
    }

    protected function confirmableOperations(): array
    {
        return ['cancel' => 'cancelTitle'];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function clearForm(): void
    {
        $this->resetValidation();
        $this->form = [
            'description' => '', 'original_value' => '', 'installments_count' => 1,
            'document_number' => '', 'reference' => '', 'notes' => '',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
            'category_id' => '', 'contact_id' => '', 'financial_account_id' => '',
            'is_recurring' => false, 'recurrence_type' => 'monthly',
            'recurrence_day' => now()->day, 'recurrence_month' => now()->month,
        ];
    }

    public function save(TitleService $service, RecurrenceService $recurrence): void
    {
        $data = $this->form;
        foreach (['category_id', 'contact_id', 'financial_account_id'] as $field) {
            $data[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
        }
        if (! ($data['is_recurring'] ?? false)) {
            $data['recurrence_type'] = $data['recurrence_day'] = $data['recurrence_month'] = null;
        } else {
            if (($data['recurrence_type'] ?? null) === 'monthly') {
                $data['recurrence_month'] = null;
            }
            // Recorrente tem sempre uma parcela por ciclo; o vencimento vem do dia/mes configurado.
            $data['installments_count'] = 1;
            $data['due_date'] = $recurrence->firstDueDate($data['issue_date'] ?? null,
                $data['recurrence_type'] ?? null, $data['recurrence_day'] ?? null, $data['recurrence_month'] ?? null)->toDateString();
        }
        // O tipo vem da rota bloqueada, nunca de um campo enviado pelo cliente.
        $data['title_type'] = $this->type;
        $title = $service->create($data);
        $this->clearForm();
        Feedback::now('created', 'Título cadastrado com sucesso!', $title->title_number.' · '.$title->description);
        $this->resetPage();
    }

    /**
     * Atalho "arrastar para a direita": valida o estado atual e abre o fluxo de baixa do detalhe.
     */
    public function startSettlement(int $id): void
    {
        $title = $this->owned($id);
        if (! in_array($title->status, self::OPEN, true)
            || ! $title->installments()->whereIn('status', ['pending', 'overdue'])->exists()) {
            Feedback::now('error', 'Não foi possível iniciar a baixa.', $title->status === 'cancelled'
                ? 'Títulos cancelados não permitem novas baixas.'
                : 'Todas as parcelas deste título já foram pagas.');

            return;
        }
        $this->redirectRoute('titles.show', ['title' => $title->id, 'acao' => 'baixa']);
    }

    /**
     * Atalho "arrastar para a esquerda": o estado real define a operacao. Com baixa ativa, abre o
     * estorno da ultima baixa no detalhe (motivo e data sao obrigatorios); sem baixa, pede
     * confirmacao do cancelamento.
     */
    public function startReversalOrCancellation(int $id): void
    {
        $title = $this->owned($id);
        if ($title->status === 'cancelled') {
            Feedback::now('error', 'Não foi possível cancelar ou estornar.', 'O título já está cancelado.');

            return;
        }
        if ($title->settlements()->active()->exists()) {
            $this->redirectRoute('titles.show', ['title' => $title->id, 'acao' => 'estorno']);

            return;
        }
        $this->askConfirmation('cancel', $title->id, $title->title_number.' · '.$title->description);
    }

    public function cancelTitle(int $id, TitleService $service): void
    {
        $this->consumeConfirmation('cancel', $id);
        $title = $service->cancel($this->owned($id));
        Feedback::now('cancelled', 'Título cancelado com sucesso!', $title->title_number.' · histórico preservado.');
    }

    /**
     * Previa somente visual do primeiro vencimento recorrente; o save recalcula no servidor.
     */
    private function recurringDuePreview(): ?string
    {
        if (! ($this->form['is_recurring'] ?? false)) {
            return null;
        }
        try {
            return app(RecurrenceService::class)->firstDueDate($this->form['issue_date'] ?? null,
                $this->form['recurrence_type'] ?? null, $this->form['recurrence_day'] ?? null,
                $this->form['recurrence_month'] ?? null)->format('d/m/Y');
        } catch (ValidationException) {
            return null;
        }
    }

    private function owned(int $id): Title
    {
        $title = Title::where('title_type', $this->type)->findOrFail($id);
        Gate::authorize('update', $title);

        return $title;
    }

    /**
     * Dicas visuais dos gestos; a decisao final e sempre refeita no servidor ao soltar.
     */
    private function shortcuts(Title $title): array
    {
        $unavailable = fn (string $label) => ['label' => $label, 'icon' => 'o-lock-closed', 'tone' => 'neutral'];
        $right = in_array($title->status, self::OPEN, true)
            ? ['label' => 'Registrar baixa', 'icon' => 'o-banknotes', 'tone' => 'success']
            : $unavailable('Baixa indisponível');
        $left = match (true) {
            $title->status === 'cancelled' => $unavailable('Título já cancelado'),
            $title->active_settlements_count > 0 => ['label' => 'Estornar baixa', 'icon' => 'o-arrow-uturn-left', 'tone' => 'warning'],
            default => ['label' => 'Cancelar título', 'icon' => 'o-no-symbol', 'tone' => 'error'],
        };

        return [
            'right' => $right + ['method' => 'startSettlement', 'args' => [$title->id]],
            'left' => $left + ['method' => 'startReversalOrCancellation', 'args' => [$title->id]],
        ];
    }

    public function render()
    {
        $income = $this->type === 'receivable';
        $records = Title::where('title_type', $this->type)
            ->where('description', 'like', '%'.mb_substr($this->search, 0, 100).'%')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->withCount(['settlements as active_settlements_count' => fn ($query) => $query->active()])
            ->with('category')->orderByDesc('id')->paginate(12);

        return view('livewire.titles', [
            'title' => $income ? 'Receitas' : 'Despesas',
            'recurringDue' => $this->recurringDuePreview(),
            'records' => $records,
            'shortcuts' => $records->getCollection()->mapWithKeys(fn (Title $title) => [$title->id => $this->shortcuts($title)]),
            'statuses' => collect(['pending', 'partial', 'paid', 'overdue', 'cancelled'])->mapWithKeys(fn ($value) => [$value => Display::status($value)]),
            'categories' => Category::where('active', true)->whereIn('type', [$income ? 'income' : 'expense', 'both'])->orderBy('name')->get(),
            'contacts' => Contact::where('active', true)->whereIn('type', [$income ? 'client' : 'supplier', 'both'])->orderBy('name')->get(),
            'accounts' => FinancialAccount::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
