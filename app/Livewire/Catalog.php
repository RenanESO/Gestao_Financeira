<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Services\CatalogService;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Catalog extends Component
{
    use WithPagination;

    public const MODULES = [
        'categorias' => [Category::class, 'Categorias', ['income' => 'Receita', 'expense' => 'Despesa', 'both' => 'Ambos']],
        'contatos' => [Contact::class, 'Contatos', ['client' => 'Cliente', 'supplier' => 'Fornecedor', 'both' => 'Ambos']],
        'contas' => [FinancialAccount::class, 'Contas financeiras', ['checking' => 'Corrente', 'savings' => 'Poupanca', 'credit_card' => 'Cartao', 'cash' => 'Dinheiro', 'investment' => 'Investimento', 'other' => 'Outra']],
        'formas' => [PaymentType::class, 'Formas de pagamento', ['payment' => 'Pagamento', 'receipt' => 'Recebimento', 'both' => 'Ambos']],
    ];

    #[Locked]
    public string $module;

    #[Locked]
    public ?int $editing = null;

    public string $search = '';

    public array $form = [];

    public function mount(string $module): void
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $this->module = $module;
        $this->clearForm();
    }

    public function updatedSearch(): void
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
        }
        if ($this->module === 'contas') {
            $this->form['initial_balance'] = '0.00';
        }
        if ($this->module === 'contatos') {
            $this->form += ['document' => '', 'email' => '', 'phone' => ''];
        }
    }

    public function edit(int $id): void
    {
        $record = self::MODULES[$this->module][0]::findOrFail($id);
        $this->clearForm();
        $this->editing = $record->id;
        foreach (array_keys($this->form) as $field) {
            $this->form[$field] = $record->$field ?? '';
        }
    }

    public function save(CatalogService $service): void
    {
        $model = self::MODULES[$this->module][0];
        if ($this->editing === null) {
            $service->create($model, $this->form);
        } else {
            $service->update($model::findOrFail($this->editing), $this->form);
        }
        $this->clearForm();
        session()->flash('status', 'Cadastro salvo.');
    }

    public function toggle(int $id, CatalogService $service): void
    {
        $record = self::MODULES[$this->module][0]::findOrFail($id);
        $service->update($record, ['active' => ! $record->active]);
        session()->flash('status', $record->active ? 'Cadastro desativado. Historico preservado.' : 'Cadastro ativado.');
    }

    public function render()
    {
        [$model, $title, $types] = self::MODULES[$this->module];

        return view('livewire.catalog', [
            'title' => $title,
            'types' => $types,
            'records' => $model::where('name', 'like', '%'.mb_substr($this->search, 0, 100).'%')->orderBy('name')->paginate(12),
        ]);
    }
}
