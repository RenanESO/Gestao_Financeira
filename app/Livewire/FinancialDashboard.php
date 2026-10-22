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
        $this->resetValidation();
    }

    public function applyFilters(FinancialAnalyticsService $service): void
    {
        $result = $service->analyze($this->filters);
        $this->applied = $result['filters'];
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
        $this->applyFilters($service);
    }

    public function render()
    {
        $service = app(FinancialAnalyticsService::class);
        $options = $service->options();

        return view('livewire.financial-dashboard', [
            'data' => $service->analyze($this->applied),
            'options' => $options,
            'contactOptions' => $options['contacts']->filter(fn ($contact) => str_contains(
                mb_strtolower($contact->name), mb_strtolower(mb_substr($this->contactSearch, 0, 100))
            )),
            'groupings' => FinancialAnalyticsService::GROUPINGS,
        ]);
    }
}
