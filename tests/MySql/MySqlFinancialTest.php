<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\TitleSettlementMethod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MySqlFinancialTest extends TestCase
{
    private bool $transacaoAberta = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Esta suite nao migra nem apaga tabelas e nunca grava no banco da aplicacao.
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

    public function test_schema_uses_innodb_expected_indexes_and_restrictive_foreign_keys(): void
    {
        $tabelas = DB::select('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        $this->assertCount(21, $tabelas);
        foreach ($tabelas as $tabela) {
            $this->assertSame('InnoDB', $tabela->ENGINE);
            $this->assertSame('utf8mb4_unicode_ci', $tabela->TABLE_COLLATION);
        }
        $this->assertTrue(Schema::hasIndex('contacts', ['user_id', 'name']));
        $this->assertTrue(Schema::hasIndex('contacts', ['user_id', 'document']));
        $this->assertTrue(Schema::hasIndex('title_installments', ['title_id', 'status', 'due_date']));
        $this->assertTrue(Schema::hasIndex('notifications', ['user_id', 'read_at']));
        $this->assertTrue(Schema::hasIndex('settlement_reversals', ['settlement_id']));
        $this->assertTrue(Schema::hasIndex('settlement_reversals', ['user_id', 'idempotency_key']));
        $this->assertTrue(Schema::hasIndex('titles', ['generated_by_settlement_id']));
        $this->assertTrue(Schema::hasColumn('receipts', 'voided_at'));
        $this->assertTrue(Schema::hasColumn('receipts', 'snapshot'));
        $this->assertTrue(Schema::hasColumn('receipts', 'pdf_sha256'));
        $this->assertTrue(Schema::hasIndex('notifications', ['user_id', 'deduplication_key']));
        $fks = DB::select('SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()');
        $this->assertCount(22, $fks);
        foreach ($fks as $fk) {
            $this->assertSame('RESTRICT', $fk->DELETE_RULE);
        }
    }

    public static function monetaryValues(): array
    {
        return [
            'cents' => ['0.10'],
            'fraction' => ['1234.56'],
            'maximum decimal 15 2' => ['9999999999999.99'],
        ];
    }

    #[DataProvider('monetaryValues')]
    public function test_decimal_values_round_trip_without_float_conversion(string $valor): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $conta = $usuario->financialAccounts()->create([
            'name' => 'Conta', 'type' => 'checking', 'initial_balance' => $valor,
        ]);
        $this->assertSame($valor, $conta->fresh()->initial_balance);
        $this->assertSame($valor, DB::table('financial_accounts')->where('id', $conta->id)->value('initial_balance'));
    }

    public function test_invalid_enums_are_rejected_by_strict_mode(): void
    {
        $usuario = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('categories')->insert(['user_id' => $usuario->id, 'name' => 'Invalida', 'type' => 'invalid']);
    }

    public function test_decimal_overflow_is_rejected(): void
    {
        $usuario = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('financial_accounts')->insert([
            'user_id' => $usuario->id, 'name' => 'Overflow', 'type' => 'checking',
            'initial_balance' => '10000000000000.00',
        ]);
    }

    public function test_textual_uniqueness_uses_the_configured_collation(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $usuario->contacts()->create(['code' => 'CLI-0001', 'name' => 'Contato', 'type' => 'client']);

        $this->expectException(QueryException::class);
        $usuario->contacts()->create(['code' => 'cli-0001', 'name' => 'Duplicado', 'type' => 'client']);
    }

    public function test_indirect_owner_scopes_and_history_work_on_mysql(): void
    {
        $primeiro = $this->createChain();
        $segundo = $this->createChain();
        $this->actingAs($primeiro['user']);

        foreach (['title' => Title::class, 'account' => FinancialAccount::class,
            'category' => Category::class, 'payment' => PaymentType::class,
            'installment' => TitleInstallment::class, 'settlement' => TitleSettlement::class,
            'method' => TitleSettlementMethod::class] as $chave => $model) {
            $this->assertSame([$primeiro[$chave]->id], $model::query()->pluck('id')->all());
            $this->assertNull($model::find($segundo[$chave]->id));
            $this->assertFalse(Gate::allows('view', $segundo[$chave]));
        }
        $titulo = Title::with('settlements.methods.financialAccount')->findOrFail($primeiro['title']->id);
        $this->assertSame('0.10', $titulo->settlements->sole()->methods->sole()->value);
        $this->assertTrue($titulo->settlements->sole()->methods->sole()->financialAccount->is($primeiro['account']));
        $titulo->delete();
        $this->assertTrue(TitleSettlement::firstOrFail()->title->trashed());
        $this->assertSame(1, TitleSettlementMethod::count());
    }

    public function test_physical_deletion_preserves_referenced_financial_history(): void
    {
        $cadeia = $this->createChain();
        $this->expectException(QueryException::class);
        DB::table('titles')->where('id', $cadeia['title']->id)->delete();
    }

    private function createChain(): array
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $conta = $usuario->financialAccounts()->create(['name' => 'Conta', 'type' => 'checking']);
        $categoria = $usuario->categories()->create(['name' => 'Salario', 'type' => 'income']);
        $forma = $usuario->paymentTypes()->create(['name' => 'PIX', 'type' => 'both']);
        $titulo = new Title([
            'title_number' => 'REC-0001', 'title_type' => 'receivable', 'description' => 'Teste MySQL',
            'original_value' => '0.10', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        ]);
        $titulo->category()->associate($categoria);
        $titulo->financialAccount()->associate($conta);
        $usuario->titles()->save($titulo);
        $parcela = $titulo->installments()->create([
            'installment_number' => 1, 'value' => '0.10', 'due_date' => '2026-10-31',
        ]);
        $baixa = new TitleSettlement([
            'settlement_date' => '2026-10-15', 'original_value' => '0.10', 'total_value' => '0.10',
        ]);
        $baixa->installment()->associate($parcela);
        $titulo->settlements()->save($baixa);
        $metodo = new TitleSettlementMethod(['value' => '0.10']);
        $metodo->paymentType()->associate($forma);
        $metodo->financialAccount()->associate($conta);
        $baixa->methods()->save($metodo);

        return [
            'user' => $usuario, 'title' => $titulo, 'account' => $conta, 'category' => $categoria,
            'payment' => $forma, 'installment' => $parcela, 'settlement' => $baixa, 'method' => $metodo,
        ];
    }
}
