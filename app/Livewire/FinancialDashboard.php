<?php

namespace App\Livewire;

use App\Services\FinancialAnalyticsService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FinancialDashboard extends Component
{
    public array $filters = [];

    public string $contactSearch = '';

    #[Locked]
    public array $applied = [];

    #[Locked]
    public bool $report = false;

    /** Resultado da analise ja calculado nesta requisicao; evita repetir a consulta no render. */
    protected ?array $result = null;

    public function mount(): void
    {
        $this->clearFilters();
    }

    public function clearFilters(): void
    {
        $this->filters = [
            'start' => now()->startOfMonth()->toDateString(),
            'end' => now()->endOfMonth()->toDateString(),
            'excluded_categories' => [], 'excluded_methods' => [], 'excluded_accounts' => [],
            'contacts' => [], 'grouping' => 'contact',
        ];
        $this->applied = $this->filters;
        $this->contactSearch = '';
        $this->report = false;
        $this->result = null;
        $this->resetValidation();
    }

    public function applyFilters(FinancialAnalyticsService $service): void
    {
        $this->apply($service, $this->filters);
    }

    private function apply(FinancialAnalyticsService $service, array $filters): void
    {
        $this->result = $service->analyze($filters);
        $this->applied = $this->result['filters'];
        $this->report = false;
        $this->resetValidation();
    }

    public function generateReport(FinancialAnalyticsService $service): void
    {
        $this->applyFilters($service);
        $this->report = true;
    }

    public function closeReport(): void
    {
        $this->report = false;
    }

    public function toggle(string $field, int $id, FinancialAnalyticsService $service): void
    {
        $options = $service->options();
        $optionField = match ($field) {
            'excluded_categories' => 'categories',
            'excluded_methods' => 'methods',
            'excluded_accounts' => 'accounts',
            'contacts' => 'contacts',
            default => abort(422),
        };
        abort_unless($options[$optionField]->contains('id', $id), 404);
        $values = $this->filters[$field] ?? [];
        $this->filters[$field] = in_array($id, $values)
            ? array_values(array_diff($values, [$id]))
            : [...$values, $id];
        // Chips entram em vigor na hora; datas e agrupamento pendentes so valem ao aplicar.
        $this->apply($service, array_replace($this->filters, [
            'start' => $this->applied['start'], 'end' => $this->applied['end'], 'grouping' => $this->applied['grouping'],
        ]));
    }

    public function render()
    {
        $service = app(FinancialAnalyticsService::class);
        $options = $service->options();

        return view('livewire.financial-dashboard', [
            'data' => $this->result ?? $service->analyze($this->applied),
            'options' => $options,
            'contactOptions' => $options['contacts']->filter(fn ($contact) => str_contains(
                mb_strtolower($contact->name), mb_strtolower(mb_substr($this->contactSearch, 0, 100))
            )),
            'groupings' => FinancialAnalyticsService::GROUPINGS,
        ]);
    }
}
