<?php

namespace Tests\MySql;

use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FinancialAnalyticsService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MySqlFinancialAnalyticsTest extends TestCase
{
    private bool $transactionOpen = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertSame('gestao_financeira_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        DB::beginTransaction();
        $this->transactionOpen = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionOpen) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_analytics_preserves_decimal_boundaries_and_reversal_dates(): void
    {
        $this->actingAs(User::factory()->create());
        $catalog = app(CatalogService::class);
        $account = $catalog->create(FinancialAccount::class, ['name' => 'Analise', 'type' => 'cash']);
        $method = $catalog->create(PaymentType::class, ['name' => 'Pix', 'type' => 'both']);
        $title = app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Precisao', 'original_value' => '123.45',
            'issue_date' => '2026-01-01', 'due_date' => '2026-01-31',
        ]);
        $settlement = app(TitleService::class)->settle($title, $title->installments->sole(), [
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-01-31',
            'original_value' => '123.45', 'payment_methods' => [[
                'payment_type_id' => $method->id, 'financial_account_id' => $account->id, 'value' => '123.45',
            ]],
        ]);
        app(SettlementReversalService::class)->reverse($settlement, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-02-01', 'reason' => 'Correcao',
        ]);
        $filters = [
            'start' => '2026-01-01', 'end' => '2026-01-31', 'grouping' => 'category',
            'excluded_categories' => [], 'excluded_methods' => [], 'excluded_accounts' => [], 'contacts' => [],
        ];
        $service = app(FinancialAnalyticsService::class);
        $this->assertSame(12345, $service->analyze($filters)['totals']['income']);
        $filters['start'] = $filters['end'] = '2026-02-01';
        $data = $service->analyze($filters);
        $this->assertSame(-12345, $data['totals']['income']);
        $this->assertSame('Estorno', $data['rows'][0]['event']);
        $filters['excluded_accounts'] = [$account->id];
        $this->assertSame(0, $service->analyze($filters)['totals']['count']);
    }
}
