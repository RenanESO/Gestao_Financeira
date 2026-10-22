<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\Title;
use App\Services\TitleService;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Titles extends Component
{
    use WithPagination;

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

    public function save(TitleService $service): void
    {
        $data = $this->form;
        foreach (['category_id', 'contact_id', 'financial_account_id'] as $field) {
            $data[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
        }
        if (! ($data['is_recurring'] ?? false)) {
            $data['recurrence_type'] = $data['recurrence_day'] = $data['recurrence_month'] = null;
        } elseif (($data['recurrence_type'] ?? null) === 'monthly') {
            $data['recurrence_month'] = null;
        }
        // O tipo vem da rota bloqueada, nunca de um campo enviado pelo cliente.
        $data['title_type'] = $this->type;
        $title = $service->create($data);
        $this->clearForm();
        session()->flash('status', 'Titulo '.$title->title_number.' criado.');
        $this->resetPage();
    }

    public function render()
    {
        $income = $this->type === 'receivable';

        return view('livewire.titles', [
            'title' => $income ? 'Receitas' : 'Despesas',
            'records' => Title::where('title_type', $this->type)
                ->where('description', 'like', '%'.mb_substr($this->search, 0, 100).'%')
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->with('category')->orderByDesc('id')->paginate(12),
            'categories' => Category::where('active', true)->whereIn('type', [$income ? 'income' : 'expense', 'both'])->orderBy('name')->get(),
            'contacts' => Contact::where('active', true)->whereIn('type', [$income ? 'client' : 'supplier', 'both'])->orderBy('name')->get(),
            'accounts' => FinancialAccount::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
