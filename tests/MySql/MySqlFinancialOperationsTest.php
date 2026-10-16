<?php

namespace Tests\MySql;

use App\Services\CatalogService;
use App\Services\FinancialAccountService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FinancialOperationsScenarios;
use Tests\TestCase;

class MySqlFinancialOperationsTest extends TestCase
{
    use FinancialOperationsScenarios;

    private bool $transacaoAberta = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('gestao_financeira_testing', DB::selectOne('SELECT DATABASE() AS nome')->nome);
        DB::beginTransaction();
        $this->transacaoAberta = true;
    }

    protected function tearDown(): void
    {
        if ($this->transacaoAberta) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_balance_preserves_maximum_mysql_decimal_without_float_conversion(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        app(CatalogService::class)->update($conta, ['initial_balance' => '0.00']);
        $titulo = $this->operationTitle(['original_value' => '9999999999999.99']);
        $this->operationPayment($titulo, $conta, $forma, '9999999999999.99');
        $resultado = app(FinancialAccountService::class)->balance($conta);
        $this->assertSame('9999999999999.99', $resultado['receipts']);
        $this->assertSame('9999999999999.99', $resultado['balance']);
    }

    public function test_balance_rejects_aggregate_overflow_instead_of_rounding(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $titulo = $this->operationTitle(['original_value' => '9999999999999.99']);
        $this->operationPayment($titulo, $conta, $forma, '9999999999999.99');
        $outro = $this->operationTitle();
        $this->operationPayment($outro, $conta, $forma);
        $this->expectOperationValidation(fn () => app(FinancialAccountService::class)->balance($conta), 'receipts');
    }
}
