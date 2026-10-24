<?php

namespace Tests\Feature;

use App\Livewire\Catalog;
use App\Livewire\FinancialDashboard;
use App\Livewire\Titles;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FinancialAnalyticsService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    private FinancialAccount $account;

    private FinancialAccount $otherAccount;

    private Category $category;

    private Contact $contact;

    private PaymentType $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
        $catalog = app(CatalogService::class);
        $this->account = $catalog->create(FinancialAccount::class, ['name' => 'Principal', 'type' => 'cash']);
        $this->otherAccount = $catalog->create(FinancialAccount::class, ['name' => 'Reserva', 'type' => 'cash']);
        $this->category = $catalog->create(Category::class, ['name' => 'Servicos', 'type' => 'both']);
        $this->contact = $catalog->create(Contact::class, ['name' => 'Cliente teste', 'type' => 'both']);
        $this->method = $catalog->create(PaymentType::class, ['name' => 'Pix', 'type' => 'both']);
    }

    private function filters(array $changes = []): array
    {
        return array_replace([
            'start' => '2026-10-01', 'end' => '2026-10-31',
            'excluded_categories' => [], 'excluded_methods' => [], 'excluded_accounts' => [],
            'contacts' => [], 'grouping' => 'contact',
        ], $changes);
    }

    private function movement(string $type = 'receivable', string $date = '2026-10-10', bool $split = false)
    {
        $title = app(TitleService::class)->create([
            'title_type' => $type, 'description' => 'Movimento '.$type, 'original_value' => '100.00',
            'issue_date' => '2026-09-01', 'due_date' => '2026-10-10',
            'category_id' => $this->category->id, 'contact_id' => $this->contact->id,
        ]);
        $methods = [[
            'payment_type_id' => $this->method->id, 'financial_account_id' => $this->account->id,
            'value' => $split ? '30.00' : '100.00',
        ]];
        if ($split) {
            $methods[] = [
                'payment_type_id' => $this->method->id, 'financial_account_id' => $this->otherAccount->id,
                'value' => '70.00',
            ];
        }

        return app(TitleService::class)->settle($title, $title->installments->sole(), [
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => $date,
            'original_value' => '100.00', 'payment_methods' => $methods,
        ]);
    }

    public function test_totals_count_events_not_payment_rows_and_filters_use_actual_accounts(): void
    {
        $this->movement(split: true);
        $this->movement('payable');
        $service = app(FinancialAnalyticsService::class);
        $data = $service->analyze($this->filters());
        $this->assertSame(['income' => 10000, 'expense' => 10000, 'balance' => 0, 'count' => 2], $data['totals']);
        $this->assertCount(3, $data['rows']);
        $filtered = $service->analyze($this->filters(['excluded_accounts' => [$this->otherAccount->id]]));
        $this->assertSame(3000, $filtered['totals']['income']);
        $this->assertSame(10000, $filtered['totals']['expense']);
        $this->assertSame(-7000, $filtered['totals']['balance']);
    }

    public function test_reversal_uses_its_own_date_and_preserves_original_period(): void
    {
        $settlement = $this->movement(date: '2026-09-10');
        app(SettlementReversalService::class)->reverse($settlement, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-15', 'reason' => 'Correção',
        ]);
        $service = app(FinancialAnalyticsService::class);
        $this->assertSame(-10000, $service->analyze($this->filters())['totals']['income']);
        $this->assertSame(10000, $service->analyze($this->filters([
            'start' => '2026-09-01', 'end' => '2026-09-30',
        ]))['totals']['income']);
    }

    public function test_combined_filters_and_group_subtotals_match_totals(): void
    {
        $this->movement(split: true);
        $service = app(FinancialAnalyticsService::class);
        foreach (array_keys(FinancialAnalyticsService::GROUPINGS) as $grouping) {
            $data = $service->analyze($this->filters(['grouping' => $grouping, 'contacts' => [$this->contact->id]]));
            $this->assertSame($data['totals']['income'], array_sum(array_column(array_column($data['groups'], 'totals'), 'income')));
            $this->assertSame(10000, array_sum(array_column($data['daily'], 'income')));
        }
        $this->assertSame(0, $service->analyze($this->filters(['excluded_categories' => [$this->category->id]]))['totals']['count']);
        $this->assertSame(0, $service->analyze($this->filters(['excluded_methods' => [$this->method->id]]))['totals']['count']);
    }

    public function test_foreign_owner_is_not_visible_and_foreign_filters_are_rejected(): void
    {
        $this->movement();
        $this->actingAs(User::factory()->create());
        $this->assertSame(0, app(FinancialAnalyticsService::class)->analyze($this->filters())['totals']['count']);
        $this->get(route('financial.dashboard'))->assertOk()->assertDontSee('Cliente teste');
        $this->expectException(ValidationException::class);
        app(FinancialAnalyticsService::class)->analyze($this->filters(['contacts' => [$this->contact->id]]));
    }

    public function test_dashboard_reset_validation_and_report_all_groupings(): void
    {
        $this->movement();
        $component = Livewire::test(FinancialDashboard::class)
            ->assertSet('filters.start', '2026-10-01')->assertSet('filters.end', '2026-10-31')
            ->call('toggle', 'excluded_accounts', $this->account->id)
            ->assertSee('Sem movimentos');
        $component->call('clearFilters')->assertSet('filters.excluded_accounts', []);
        foreach (array_keys(FinancialAnalyticsService::GROUPINGS) as $grouping) {
            $component->set('filters.grouping', $grouping)->call('generateReport')->assertHasNoErrors()
                ->assertSee('Relatório financeiro')->assertSee('Entradas realizadas')
                ->assertSee('Saldo líquido do período')->assertSee('Solicitado por')
                ->assertSee('MoneyControl')->assertSee(auth()->user()->email)
                ->assertSee('Subtotal')->assertDontSee('Nível');
        }
        $component->set('filters.end', '2026-09-01')->call('applyFilters')->assertHasErrors('end');
        $this->assertSame('2026-10-31', $component->get('applied.end'));
    }

    public function test_title_creation_has_all_editable_metadata_and_catalog_fields_match(): void
    {
        Livewire::test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Completo')->set('form.original_value', '20.00')
            ->set('form.document_number', 'DOC-1')->set('form.reference', 'Referencia')
            ->set('form.notes', 'Observações')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('titles', ['description' => 'Completo', 'document_number' => 'DOC-1', 'reference' => 'Referencia', 'notes' => 'Observações']);
        foreach (['contas' => $this->account, 'categorias' => $this->category, 'contatos' => $this->contact, 'formas' => $this->method] as $module => $record) {
            $component = Livewire::test(Catalog::class, ['module' => $module]);
            $fields = array_keys($component->get('form'));
            $component->call('edit', $record->id);
            $this->assertSame($fields, array_keys($component->get('form')));
        }
    }

    public function test_dashboard_route_requires_authentication(): void
    {
        auth()->logout();
        $this->get(route('financial.dashboard'))->assertRedirect(route('login'));
    }

    public function test_period_boundaries_are_inclusive_and_pending_titles_are_not_cash_movements(): void
    {
        $this->movement(date: '2026-10-01');
        $this->movement(date: '2026-10-31');
        app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Ainda pendente', 'original_value' => '500.00',
            'issue_date' => '2026-10-01', 'due_date' => '2026-10-15',
        ]);
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters());
        $this->assertSame(20000, $data['totals']['income']);
        $this->assertSame(10000, $data['daily']['2026-10-01']['income']);
        $this->assertSame(10000, $data['daily']['2026-10-31']['income']);
        $this->assertSame(2, $data['totals']['count']);
    }

    public function test_archived_contacts_and_inactive_catalogs_remain_in_historical_analysis(): void
    {
        $this->movement();
        $catalog = app(CatalogService::class);
        $catalog->archive($this->contact);
        $catalog->update($this->account, ['active' => false]);
        $catalog->update($this->method, ['active' => false]);
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters(['contacts' => [$this->contact->id]]));
        $this->assertSame(10000, $data['totals']['income']);
        $this->assertSame('Cliente teste', $data['rows'][0]['contact']);
    }

    public function test_multiple_contacts_search_and_reset_are_shared_by_report(): void
    {
        $this->movement();
        $second = app(CatalogService::class)->create(Contact::class, ['name' => 'Outro cliente', 'type' => 'client']);
        Livewire::test(FinancialDashboard::class)
            ->set('contactSearch', 'Outro')
            ->call('toggle', 'contacts', $this->contact->id)
            ->call('toggle', 'contacts', $second->id)
            ->call('generateReport')
            ->assertSee('Cliente teste')->assertSee('Outro cliente')
            ->assertSet('applied.contacts', [$this->contact->id, $second->id])
            ->call('clearFilters')->assertSet('filters.contacts', [])->assertSet('contactSearch', '');
    }

    public function test_daily_table_only_has_movement_dates_but_chart_keeps_full_period(): void
    {
        $this->movement(date: '2026-10-10');
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters());
        $this->assertCount(31, $data['daily']);
        $this->assertSame(['2026-10-10'], array_keys($data['movement_days']));
        $component = Livewire::test(FinancialDashboard::class);
        $html = $component->html();
        preg_match('/<table[^>]*aria-label="Valores por data com movimentação".*?<\/table>/s', $html, $matches);
        $this->assertStringContainsString('10/10/2026', $matches[0]);
        $this->assertStringNotContainsString('09/10/2026', $matches[0]);
        $component->assertSee('01/10/2026 — 31/10/2026');
    }

    public function test_chips_apply_immediately_without_applying_pending_dates(): void
    {
        $this->movement(date: '2026-10-10');
        Livewire::test(FinancialDashboard::class)
            ->set('filters.end', '2026-09-01')
            ->call('toggle', 'excluded_methods', $this->method->id)
            ->assertHasNoErrors()
            ->assertSet('applied.excluded_methods', [$this->method->id])
            ->assertSet('applied.end', '2026-10-31')
            ->assertSet('filters.end', '2026-09-01')
            ->call('applyFilters')->assertHasErrors('end');
    }

    public function test_distribution_is_sorted_by_share_and_inactive_account_with_balance_stays_in_chart(): void
    {
        $other = app(CatalogService::class)->create(Category::class, ['name' => 'Aaa menor', 'type' => 'both']);
        $this->movement(split: true);
        $small = app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Pequeno', 'original_value' => '1.00',
            'issue_date' => '2026-09-01', 'due_date' => '2026-10-10', 'category_id' => $other->id,
        ]);
        app(TitleService::class)->settle($small, $small->installments->sole(), [
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-09-05', 'original_value' => '1.00',
            'payment_methods' => [['payment_type_id' => $this->method->id, 'financial_account_id' => $this->otherAccount->id, 'value' => '1.00']],
        ]);
        app(TitleService::class)->settle($second = app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Pequeno 2', 'original_value' => '1.00',
            'issue_date' => '2026-09-01', 'due_date' => '2026-10-10', 'category_id' => $other->id,
        ]), $second->installments->sole(), [
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-05', 'original_value' => '1.00',
            'payment_methods' => [['payment_type_id' => $this->method->id, 'financial_account_id' => $this->account->id, 'value' => '1.00']],
        ]);
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters());
        $this->assertSame(['Servicos', 'Aaa menor'], array_column($data['categories'], 'label'));

        // Reserva fica inativa e sem movimento em novembro, mas ainda guarda R$ 71,00.
        app(CatalogService::class)->update($this->otherAccount, ['active' => false]);
        $november = app(FinancialAnalyticsService::class)->analyze($this->filters(['start' => '2026-11-01', 'end' => '2026-11-30']));
        $reserve = collect($november['accounts'])->firstWhere('id', $this->otherAccount->id);
        $this->assertSame(7100, $reserve['opening']);
    }

    public function test_reversal_net_zero_date_is_still_a_movement_date(): void
    {
        $settlement = $this->movement(date: '2026-10-10');
        app(SettlementReversalService::class)->reverse($settlement, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-10', 'reason' => 'Correção',
        ]);
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters());
        $this->assertSame(['2026-10-10' => ['income' => 0, 'expense' => 0]], $data['movement_days']);
    }

    public function test_account_balance_history_uses_opening_balance_prior_movements_and_reversals(): void
    {
        $this->account->update(['initial_balance' => '50.00']);
        $this->movement(date: '2026-09-10');
        $reversed = $this->movement(date: '2026-10-10');
        $this->movement('payable', '2026-10-12');
        app(SettlementReversalService::class)->reverse($reversed, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-15', 'reason' => 'Correção',
        ]);
        $service = app(FinancialAnalyticsService::class);
        $accounts = collect($service->analyze($this->filters())['accounts'])->keyBy('id');

        $principal = $accounts[$this->account->id];
        $this->assertSame(15000, $principal['opening']);
        $this->assertSame([15000, 25000, 25000, 15000, 15000, 15000, 5000], array_slice($principal['values'], 8, 7));
        $this->assertSame(5000, end($principal['values']));
        $this->assertSame(array_fill(0, 31, 0), $accounts[$this->otherAccount->id]['values']);
        $this->assertNotSame($principal['color'], $accounts[$this->otherAccount->id]['color']);

        $filtered = $service->analyze($this->filters(['excluded_accounts' => [$this->otherAccount->id]]))['accounts'];
        $this->assertSame([$this->account->id], array_column($filtered, 'id'));
        Livewire::test(FinancialDashboard::class)->assertSee('Histórico de saldo por conta')->assertSee('Contas no gráfico');
    }

    public function test_account_history_filters_opening_period_and_reversal_without_removing_initial_balance(): void
    {
        $this->account->update(['initial_balance' => '50.00']);
        $prior = $this->movement(date: '2026-09-10');
        $this->movement(date: '2026-10-10', split: true);
        app(SettlementReversalService::class)->reverse($prior, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-15', 'reason' => 'Correção',
        ]);
        $otherContact = app(CatalogService::class)->create(Contact::class, ['name' => 'Outro', 'type' => 'both']);
        $service = app(FinancialAnalyticsService::class);
        foreach ([
            ['excluded_categories' => [$this->category->id]],
            ['excluded_methods' => [$this->method->id]],
            ['contacts' => [$otherContact->id]],
        ] as $filter) {
            $data = $service->analyze($this->filters($filter));
            $accounts = collect($data['accounts'])->keyBy('id');
            $this->assertSame(5000, $accounts[$this->account->id]['opening']);
            $this->assertSame(array_fill(0, 31, 5000), $accounts[$this->account->id]['values']);
            $this->assertSame(array_fill(0, 31, 0), $accounts[$this->otherAccount->id]['values']);
            $this->assertSame(0, $data['totals']['count']);
        }
        $data = $service->analyze($this->filters(['contacts' => [$this->contact->id, $otherContact->id]]));
        $principal = collect($data['accounts'])->firstWhere('id', $this->account->id);
        $this->assertSame(15000, $principal['opening']);
        $this->assertSame(18000, $principal['values'][9]);
        $this->assertSame(8000, $principal['values'][14]);
        $this->assertSame($data['totals']['balance'], array_sum(array_map(
            fn ($account) => end($account['values']) - $account['opening'], $data['accounts']
        )));
    }

    public function test_account_history_combined_filters_keep_only_matching_movements(): void
    {
        $this->account->update(['initial_balance' => '50.00']);
        $excludedCategory = $this->category;
        $excludedMethod = $this->method;
        $excludedContact = $this->contact;
        $this->movement(date: '2026-09-10');
        $this->movement(date: '2026-10-10');
        $catalog = app(CatalogService::class);
        $this->category = $catalog->create(Category::class, ['name' => 'Outra categoria', 'type' => 'both']);
        $this->method = $catalog->create(PaymentType::class, ['name' => 'Outra forma', 'type' => 'both']);
        $this->contact = $catalog->create(Contact::class, ['name' => 'Outro contato', 'type' => 'both']);
        $this->movement(date: '2026-09-20', split: true);
        $this->movement(date: '2026-10-01', split: true);
        $this->movement('payable', '2026-10-31', split: true);
        $this->movement(date: '2026-11-01');
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters([
            'excluded_categories' => [$excludedCategory->id],
            'excluded_methods' => [$excludedMethod->id],
            'excluded_accounts' => [$this->otherAccount->id],
            'contacts' => [$this->contact->id],
        ]));
        $this->assertNotSame($excludedContact->id, $this->contact->id);
        $this->assertCount(1, $data['accounts']);
        $principal = $data['accounts'][0];
        $this->assertSame(8000, $principal['opening']);
        $this->assertSame(11000, $principal['values'][0]);
        $this->assertSame(8000, $principal['values'][30]);
        $this->assertSame(2, $data['totals']['count']);
        $this->assertSame(0, $data['totals']['balance']);
        Livewire::test(FinancialDashboard::class)
            ->call('toggle', 'excluded_methods', $this->method->id)
            ->assertSet('applied.excluded_methods', [$this->method->id])
            ->assertSee('saldo analítico');
    }

    public function test_empty_period_has_no_movement_dates_and_single_day_chart_renders(): void
    {
        $data = app(FinancialAnalyticsService::class)->analyze($this->filters());
        $this->assertSame([], $data['movement_days']);
        Livewire::test(FinancialDashboard::class)
            ->set('filters.start', '2026-10-22')->set('filters.end', '2026-10-22')
            ->call('applyFilters')->assertHasNoErrors()
            ->assertSee('Nenhuma data com movimentação')->assertSee('setas do teclado');
    }
}
