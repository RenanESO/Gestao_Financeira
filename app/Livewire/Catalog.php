<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOperations;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialModel;
use App\Models\PaymentType;
use App\Services\CatalogService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Catalog extends Component
{
    use ConfirmsOperations;
    use WithPagination;

    public const MODULES = [
        'categorias' => [Category::class, 'Categorias', ['income' => 'Receita', 'expense' => 'Despesa', 'both' => 'Ambos']],
        'contatos' => [Contact::class, 'Contatos', ['client' => 'Cliente', 'supplier' => 'Fornecedor', 'both' => 'Ambos']],
        'contas' => [FinancialAccount::class, 'Contas financeiras', ['checking' => 'Corrente', 'savings' => 'Poupança', 'credit_card' => 'Cartão', 'cash' => 'Dinheiro', 'investment' => 'Investimento', 'other' => 'Outra']],
        'formas' => [PaymentType::class, 'Formas de pagamento', ['payment' => 'Pagamento', 'receipt' => 'Recebimento', 'both' => 'Ambos']],
    ];

    /** Nome singular e terminacao de genero usados nas mensagens ("Categoria excluida com sucesso!"). */
    public const ENTITIES = [
        'categorias' => ['Categoria', 'a'],
        'contatos' => ['Contato', 'o'],
        'contas' => ['Conta financeira', 'a'],
        'formas' => ['Forma de pagamento', 'a'],
    ];

    #[Locked]
    public string $module;

    #[Locked]
    public ?int $editing = null;

    public string $search = '';

    public bool $showDeleted = false;

    public array $form = [];

    public function mount(string $module): void
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $this->module = $module;
        $this->clearForm();
    }

    protected function confirmableOperations(): array
    {
        return ['delete' => 'delete'];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowDeleted(): void
    {
        $this->resetPage();
    }

    public function clearForm(): void
    {
        $this->editing = null;
        $this->resetValidation();
        $this->form = ['name' => '', 'type' => array_key_first(self::MODULES[$this->module][2]), 'active' => true];
        if (in_array($this->module, ['categorias', 'contas'], true)) {
            $this->form['color'] = '#14b8a6';
            $this->form['icon'] = '';
        }
        if ($this->module === 'contas') {
            $this->form['initial_balance'] = '0.00';
        }
        if ($this->module === 'contatos') {
            $this->form += [
                'document' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'neighborhood' => '',
                'city' => '',
                'state' => '',
                'zip_code' => '',
                'notes' => '',
            ];
        }
    }

    /**
     * Botao "Editar" e atalho "arrastar para a direita". Registro excluido precisa ser restaurado antes.
     */
    public function edit(int $id): void
    {
        $record = $this->record($id, true);
        if ($record->trashed()) {
            Feedback::now('error', 'Não foi possível editar o registro.', 'Registro excluído: restaure-o antes de editar.');

            return;
        }
        Gate::authorize('update', $record);
        $this->clearForm();
        $this->editing = $record->id;
        foreach (array_keys($this->form) as $field) {
            $this->form[$field] = $record->$field ?? '';
        }
        $this->dispatch('catalog-form-focus');
    }

    public function save(CatalogService $service): void
    {
        $model = self::MODULES[$this->module][0];
        $created = $this->editing === null;
        if ($created) {
            $record = $service->create($model, $this->form);
        } else {
            $record = $service->update($model::findOrFail($this->editing), $this->form);
        }
        $this->clearForm();
        Feedback::now($created ? 'created' : 'updated', $this->message($created ? 'cadastrad' : 'alterad'), $record->name);
    }

    public function toggle(int $id, CatalogService $service): void
    {
        $record = $this->record($id);
        $record = $service->update($record, ['active' => ! $record->active]);
        Feedback::now($record->active ? 'activated' : 'deactivated',
            $this->message($record->active ? 'ativad' : 'desativad'), $record->name.' · vínculos e histórico preservados.');
    }

    /**
     * Botao "Excluir" e atalho "arrastar para a esquerda": somente abre a confirmacao.
     */
    public function confirmDelete(int $id, CatalogService $service): void
    {
        $record = $this->record($id, true);
        if ($record->trashed()) {
            Feedback::now('error', 'Não foi possível excluir o registro.', 'O registro já está excluído.');

            return;
        }
        Gate::authorize('delete', $record);
        if ($service->blocksDeletion($record)) {
            Feedback::now('error', 'Não foi possível excluir o registro.', 'Este cadastro possui vínculos financeiros. Desative-o para preservar o histórico.');

            return;
        }
        $this->askConfirmation('delete', $record->id, self::ENTITIES[$this->module][0].' · '.$record->name);
    }

    public function delete(int $id, CatalogService $service): void
    {
        $this->consumeConfirmation('delete', $id);
        $record = $service->delete($this->record($id));
        if ($this->editing === $id) {
            $this->clearForm();
        }
        Feedback::now('deleted', $this->message('excluíd'), $record->name.' · restauração disponível em "Mostrar excluídos".');
    }

    public function restore(int $id, CatalogService $service): void
    {
        $record = $service->restore($this->record($id, true));
        Feedback::now('restored', $this->message('restaurad'), $record->name);
    }

    private function record(int $id, bool $withTrashed = false): FinancialModel
    {
        $model = self::MODULES[$this->module][0];

        return ($withTrashed ? $model::withTrashed() : $model::query())->findOrFail($id);
    }

    private function message(string $verb): string
    {
        [$entity, $ending] = self::ENTITIES[$this->module];

        return $entity.' '.$verb.$ending.' com sucesso!';
    }

    /**
     * Dicas dos gestos; vinculos financeiros bloqueiam a exclusao de categorias, contas e formas.
     */
    private function shortcuts(FinancialModel $record): array
    {
        $blocked = ($record->titles_exists ?? false) || ($record->installments_exists ?? false)
            || ($record->settlement_methods_exists ?? false);

        return [
            'right' => ['label' => 'Editar', 'icon' => 'o-pencil-square', 'tone' => 'info', 'method' => 'edit', 'args' => [$record->id]],
            'left' => ($blocked
                ? ['label' => 'Exclusão indisponível', 'icon' => 'o-lock-closed', 'tone' => 'neutral']
                : ['label' => 'Excluir', 'icon' => 'o-trash', 'tone' => 'error']) + ['method' => 'confirmDelete', 'args' => [$record->id]],
            'blocked' => $blocked,
        ];
    }

    public function render()
    {
        [$model, $title, $types] = self::MODULES[$this->module];
        $query = $model::query();

        if ($this->showDeleted) {
            $query->withTrashed();
        }
        $titles = fn ($relation) => $relation->withTrashed();
        $query->withExists(match ($this->module) {
            'categorias' => ['titles' => $titles],
            'contas' => ['titles' => $titles, 'installments', 'settlementMethods'],
            'formas' => ['settlementMethods'],
            default => [],
        });
        $records = $query->where('name', 'like', '%'.mb_substr($this->search, 0, 100).'%')->orderBy('name')->paginate(12);

        return view('livewire.catalog', [
            'title' => $title,
            'entity' => self::ENTITIES[$this->module][0],
            'types' => $types,
            'records' => $records,
            'shortcuts' => $records->getCollection()->mapWithKeys(fn ($record) => [$record->id => $this->shortcuts($record)]),
        ]);
    }
}
