<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinancialSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_domain_tables_and_profile_columns_exist(): void
    {
        foreach (['users', 'categories', 'financial_accounts', 'contacts', 'payment_types',
            'titles', 'title_installments', 'title_settlements', 'title_settlement_methods',
            'receipts', 'notifications'] as $tabela) {
            $this->assertTrue(Schema::hasTable($tabela), $tabela);
        }

        $this->assertTrue(Schema::hasColumns('users', ['avatar', 'theme', 'notification_days_before']));
        $this->assertTrue(Schema::hasColumn('contacts', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('titles', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('title_settlements', 'deleted_at'));
    }

    public function test_search_and_dashboard_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('contacts', ['user_id', 'name']));
        $this->assertTrue(Schema::hasIndex('contacts', ['user_id', 'document']));
        $this->assertTrue(Schema::hasIndex('title_installments', ['title_id', 'status', 'due_date']));
        $this->assertTrue(Schema::hasIndex('notifications', ['user_id', 'read_at']));
    }

    public function test_profile_defaults_are_persisted_by_the_database(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Usuário', 'email' => 'defaults@example.test', 'password' => 'hash',
        ]);
        $usuario = User::findOrFail($id);

        $this->assertSame('dark', $usuario->theme);
        $this->assertSame(7, $usuario->notification_days_before);
        $this->assertNull($usuario->avatar);
    }

    public function test_complete_financial_chain_can_be_persisted(): void
    {
        $cadeia = $this->createFinancialChain();
        $baixa = DB::table('title_settlements')->find($cadeia['settlement']);

        $this->assertEquals('100.00', $baixa->total_value);
        $this->assertEquals(0, $baixa->discount);
        $this->assertEquals(0, $baixa->interest);
        $this->assertEquals(0, $baixa->fine);
        $this->assertEquals(100, DB::table('title_settlement_methods')->where('settlement_id', $baixa->id)->sum('value'));
        $this->assertDatabaseHas('receipts', ['settlement_id' => $baixa->id]);
        $this->assertDatabaseHas('titles', ['id' => $cadeia['title'], 'status' => 'pending', 'installments_count' => 1]);
        $this->assertDatabaseHas('title_installments', ['title_id' => $cadeia['title'], 'status' => 'pending']);
    }

    public function test_contact_and_title_numbers_are_scoped_to_the_owner(): void
    {
        $primeiro = User::factory()->create();
        $segundo = User::factory()->create();

        foreach ([$primeiro, $segundo] as $usuario) {
            DB::table('contacts')->insert([
                'user_id' => $usuario->id, 'code' => 'CLI-0001', 'type' => 'client', 'name' => 'Contato',
            ]);
            DB::table('titles')->insert($this->titleData($usuario->id));
        }

        $this->assertDatabaseCount('contacts', 2);
        $this->assertDatabaseCount('titles', 2);
    }

    #[DataProvider('duplicateKeys')]
    public function test_duplicate_business_keys_are_rejected(string $tabela, string $chave): void
    {
        $cadeia = $this->createFinancialChain();
        $dados = (array) DB::table($tabela)->find($cadeia[$chave]);
        unset($dados['id']);

        $this->expectException(QueryException::class);
        DB::table($tabela)->insert($dados);
    }

    public static function duplicateKeys(): array
    {
        return [
            'contact code per owner' => ['contacts', 'contact'],
            'title number per owner' => ['titles', 'title'],
            'installment number per title' => ['title_installments', 'installment'],
            'receipt number' => ['receipts', 'receipt'],
        ];
    }

    public function test_only_one_receipt_is_allowed_per_settlement(): void
    {
        $cadeia = $this->createFinancialChain();
        $dados = (array) DB::table('receipts')->find($cadeia['receipt']);
        unset($dados['id']);
        $dados['receipt_number'] = 'RCB-2026-0002';

        $this->expectException(QueryException::class);
        DB::table('receipts')->insert($dados);
    }

    #[DataProvider('invalidEnumValues')]
    public function test_invalid_domain_types_are_rejected(string $tabela, string $chave, string $coluna): void
    {
        $cadeia = $this->createFinancialChain();

        $this->expectException(QueryException::class);
        DB::table($tabela)->where('id', $cadeia[$chave])->update([$coluna => 'invalid']);
    }

    public static function invalidEnumValues(): array
    {
        return [
            'theme' => ['users', 'user', 'theme'],
            'category type' => ['categories', 'category', 'type'],
            'account type' => ['financial_accounts', 'account', 'type'],
            'contact type' => ['contacts', 'contact', 'type'],
            'payment type' => ['payment_types', 'payment', 'type'],
            'title type' => ['titles', 'title', 'title_type'],
            'title status' => ['titles', 'title', 'status'],
            'recurrence type' => ['titles', 'title', 'recurrence_type'],
            'installment status' => ['title_installments', 'installment', 'status'],
            'notification type' => ['notifications', 'notification', 'type'],
        ];
    }

    #[DataProvider('referencedRecords')]
    public function test_physical_deletion_of_referenced_records_is_rejected(string $tabela, string $chave): void
    {
        $cadeia = $this->createFinancialChain();

        $this->expectException(QueryException::class);
        DB::table($tabela)->where('id', $cadeia[$chave])->delete();
    }

    public static function referencedRecords(): array
    {
        return [
            'owner' => ['users', 'user'],
            'category' => ['categories', 'category'],
            'account' => ['financial_accounts', 'account'],
            'contact' => ['contacts', 'contact'],
            'payment type' => ['payment_types', 'payment'],
            'title' => ['titles', 'title'],
            'installment' => ['title_installments', 'installment'],
            'settlement' => ['title_settlements', 'settlement'],
        ];
    }

    public function test_nonexistent_foreign_keys_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('titles')->insert($this->titleData(999999));
    }

    public function test_nullable_links_and_recurrence_parent_are_supported(): void
    {
        $usuario = User::factory()->create();
        $raiz = DB::table('titles')->insertGetId($this->titleData($usuario->id));
        $filho = DB::table('titles')->insertGetId(array_replace($this->titleData($usuario->id), [
            'title_number' => 'REC-0002', 'parent_title_id' => $raiz,
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));

        $this->assertDatabaseHas('titles', ['id' => $filho, 'parent_title_id' => $raiz, 'contact_id' => null]);
        $this->expectException(QueryException::class);
        DB::table('titles')->where('id', $raiz)->delete();
    }

    public function test_soft_deletion_keeps_financial_history(): void
    {
        $cadeia = $this->createFinancialChain();
        DB::table('titles')->where('id', $cadeia['title'])->update(['deleted_at' => now()]);
        DB::table('contacts')->where('id', $cadeia['contact'])->update(['deleted_at' => now()]);

        $this->assertDatabaseHas('title_settlements', ['id' => $cadeia['settlement']]);
        $this->assertDatabaseHas('receipts', ['id' => $cadeia['receipt']]);
        $this->assertNotNull(DB::table('titles')->find($cadeia['title'])->deleted_at);
    }

    public function test_financial_transaction_rolls_back_on_failure(): void
    {
        $usuario = User::factory()->create();

        try {
            DB::transaction(function () use ($usuario) {
                $titulo = DB::table('titles')->insertGetId($this->titleData($usuario->id));
                DB::table('title_installments')->insert([
                    'title_id' => $titulo, 'financial_account_id' => 999999,
                    'installment_number' => 1, 'value' => '100.00', 'due_date' => '2026-10-31',
                ]);
            });
            $this->fail('A chave estrangeira invalida deveria interromper a transacao.');
        } catch (QueryException) {
            $this->assertDatabaseCount('titles', 0);
            $this->assertDatabaseCount('title_installments', 0);
        }
    }

    private function titleData(int $usuarioId): array
    {
        return [
            'user_id' => $usuarioId, 'title_number' => 'REC-0001', 'title_type' => 'receivable',
            'description' => 'Título de teste', 'original_value' => '100.00',
            'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        ];
    }

    private function createFinancialChain(): array
    {
        $usuario = User::factory()->create();
        $categoria = DB::table('categories')->insertGetId(['user_id' => $usuario->id, 'name' => 'Salario', 'type' => 'income']);
        $conta = DB::table('financial_accounts')->insertGetId(['user_id' => $usuario->id, 'name' => 'Conta', 'type' => 'checking']);
        $contato = DB::table('contacts')->insertGetId(['user_id' => $usuario->id, 'code' => 'CLI-0001', 'name' => 'Contato', 'type' => 'client']);
        $forma = DB::table('payment_types')->insertGetId(['user_id' => $usuario->id, 'name' => 'PIX', 'type' => 'both']);
        $titulo = DB::table('titles')->insertGetId(array_replace($this->titleData($usuario->id), [
            'category_id' => $categoria, 'financial_account_id' => $conta, 'contact_id' => $contato,
        ]));
        $parcela = DB::table('title_installments')->insertGetId([
            'title_id' => $titulo, 'financial_account_id' => $conta, 'installment_number' => 1,
            'value' => '100.00', 'due_date' => '2026-10-31',
        ]);
        $baixa = DB::table('title_settlements')->insertGetId([
            'title_id' => $titulo, 'title_installment_id' => $parcela,
            'settlement_date' => '2026-10-15', 'original_value' => '100.00', 'total_value' => '100.00',
        ]);
        foreach (['40.00', '60.00'] as $valor) {
            DB::table('title_settlement_methods')->insert([
                'settlement_id' => $baixa, 'payment_type_id' => $forma,
                'financial_account_id' => $conta, 'value' => $valor,
            ]);
        }
        $recibo = DB::table('receipts')->insertGetId([
            'user_id' => $usuario->id, 'settlement_id' => $baixa, 'receipt_number' => 'RCB-2026-0001',
            'issue_date' => '2026-10-15', 'pdf_path' => "receipts/{$usuario->id}/RCB-2026-0001.pdf",
        ]);
        $notificacao = DB::table('notifications')->insertGetId([
            'user_id' => $usuario->id, 'title' => 'Vencimento', 'message' => 'Título próximo ao vencimento',
            'type' => 'warning', 'notifiable_type' => 'App\\Models\\Title', 'notifiable_id' => $titulo,
        ]);

        return [
            'user' => $usuario->id, 'category' => $categoria, 'account' => $conta,
            'contact' => $contato, 'payment' => $forma, 'title' => $titulo,
            'installment' => $parcela, 'settlement' => $baixa, 'receipt' => $recibo, 'notification' => $notificacao,
        ];
    }
}
