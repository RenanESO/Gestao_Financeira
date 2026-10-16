<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FinancialAccountService;
use App\Services\FinancialDefaultsService;
use App\Services\OverdueTitleService;
use App\Services\TitleService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

trait FinancialOperationsScenarios
{
    private function operationFixtures(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $catalog = app(CatalogService::class);
        $conta = $catalog->create(FinancialAccount::class, [
            'name' => 'Conta corrente', 'type' => 'checking', 'initial_balance' => '-10.00',
        ]);
        $forma = $catalog->create(PaymentType::class, ['name' => 'PIX', 'type' => 'both']);

        return [$usuario, $conta, $forma];
    }

    private function operationTitle(array $dados = []): Title
    {
        return app(TitleService::class)->create(array_replace([
            'title_type' => 'receivable', 'description' => 'Operacao',
            'original_value' => '100.00', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        ], $dados));
    }

    private function operationPayment(Title $titulo, FinancialAccount $conta, PaymentType $forma, string $principal = '100.00', array $dados = []): TitleSettlement
    {
        return app(TitleService::class)->settle($titulo, $titulo->installments->first(), array_replace([
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-15',
            'original_value' => $principal,
            'payment_methods' => [[
                'financial_account_id' => $conta->id, 'payment_type_id' => $forma->id, 'value' => $principal,
            ]],
        ], $dados));
    }

    private function expectOperationValidation(callable $operacao, string $campo): void
    {
        try {
            $operacao();
            $this->fail('Operacao deveria rejeitar os dados.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    public function test_balance_uses_actual_methods_cutoff_and_signed_opening_balance(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $receita = $this->operationTitle();
        $this->operationPayment($receita, $conta, $forma, '40.00', [
            'discount' => '5.00',
            'payment_methods' => [[
                'financial_account_id' => $conta->id, 'payment_type_id' => $forma->id, 'value' => '35.00',
            ]],
        ]);
        $despesa = $this->operationTitle(['title_type' => 'payable', 'original_value' => '50.00']);
        $this->operationPayment($despesa, $conta, $forma, '50.00', [
            'fine' => '1.00',
            'payment_methods' => [[
                'financial_account_id' => $conta->id, 'payment_type_id' => $forma->id, 'value' => '51.00',
            ]],
        ]);
        $futuro = $this->operationTitle();
        $this->operationPayment($futuro, $conta, $forma, '100.00', ['settlement_date' => '2026-11-01']);
        $this->assertSame([
            'as_of' => '2026-10-15', 'initial_balance' => '-10.00',
            'receipts' => '35.00', 'payments' => '51.00', 'balance' => '-26.00',
        ], app(FinancialAccountService::class)->balance($conta));
        $this->assertSame('74.00', app(FinancialAccountService::class)->balance($conta, '2026-11-01')['balance']);
        $this->assertSame('-10.00', app(FinancialAccountService::class)->balance($conta, '2026-10-14')['balance']);
    }

    public function test_balance_includes_soft_deleted_history_and_inactive_accounts(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $titulo = $this->operationTitle();
        $this->operationPayment($titulo, $conta, $forma);
        $titulo->delete();
        app(CatalogService::class)->update($conta, ['active' => false]);
        $this->assertSame('90.00', app(FinancialAccountService::class)->balance($conta)['balance']);
        $this->assertSame(0, Title::count());
    }

    public function test_balance_only_counts_the_method_posted_to_the_selected_account(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $segunda = app(CatalogService::class)->create(FinancialAccount::class, ['name' => 'Caixa', 'type' => 'cash']);
        $titulo = $this->operationTitle();
        $this->operationPayment($titulo, $conta, $forma, '100.00', [
            'payment_methods' => [
                ['financial_account_id' => $conta->id, 'payment_type_id' => $forma->id, 'value' => '60.00'],
                ['financial_account_id' => $segunda->id, 'payment_type_id' => $forma->id, 'value' => '40.00'],
            ],
        ]);
        $this->assertSame('50.00', app(FinancialAccountService::class)->balance($conta)['balance']);
        $this->assertSame('40.00', app(FinancialAccountService::class)->balance($segunda)['balance']);
    }

    public function test_balance_rejects_invalid_dates_and_foreign_accounts(): void
    {
        [$primeiro, $conta] = $this->operationFixtures();
        $this->expectOperationValidation(fn () => app(FinancialAccountService::class)->balance($conta, '2026-02-31'), 'date');
        [, $alheia] = $this->operationFixtures();
        $this->actingAs($primeiro);
        $alheia->user_id = $primeiro->id;
        $this->expectException(ModelNotFoundException::class);
        app(FinancialAccountService::class)->balance($alheia);
    }

    public static function operationCatalogModels(): array
    {
        return [[Category::class, 'income'], [Contact::class, 'client'],
            [FinancialAccount::class, 'cash'], [PaymentType::class, 'both']];
    }

    #[DataProvider('operationCatalogModels')]
    public function test_catalog_creation_update_and_owner_fields(string $model, string $tipo): void
    {
        [$usuario] = $this->operationFixtures();
        $catalog = app(CatalogService::class);
        $registro = $catalog->create($model, ['name' => '  Cadastro  ', 'type' => $tipo]);
        $this->assertSame('Cadastro', $registro->name);
        $this->assertSame($usuario->id, $registro->user_id);
        $atualizado = $catalog->update($registro, ['name' => 'Editado', 'active' => false]);
        $this->assertSame($registro->id, $atualizado->id);
        $this->assertSame('Editado', $atualizado->name);
        $this->assertFalse($atualizado->active);
        $this->expectOperationValidation(fn () => $catalog->update($registro, ['user_id' => 999]), 'user_id');
    }

    #[DataProvider('operationCatalogModels')]
    public function test_catalog_update_reloads_the_persisted_owner(string $model, string $tipo): void
    {
        [$primeiro] = $this->operationFixtures();
        $this->operationFixtures();
        $registro = app(CatalogService::class)->create($model, ['name' => 'Alheio', 'type' => $tipo]);
        $this->actingAs($primeiro);
        $registro->user_id = $primeiro->id;
        $this->expectException(ModelNotFoundException::class);
        app(CatalogService::class)->update($registro, ['name' => 'Invasao']);
    }

    public static function invalidCatalogOperations(): array
    {
        return [
            [Category::class, ['name' => ' ', 'type' => 'income'], 'name'],
            [Category::class, ['name' => 'Teste', 'type' => 'invalid'], 'type'],
            [Category::class, ['name' => 'Teste', 'type' => 'income', 'color' => 'red'], 'color'],
            [FinancialAccount::class, ['name' => 'Teste', 'type' => 'cash', 'initial_balance' => 10.1], 'initial_balance'],
            [Contact::class, ['name' => 'Teste', 'type' => 'client', 'email' => 'invalid'], 'email'],
            [Contact::class, ['name' => 'Teste', 'type' => 'client', 'state' => 'ABC'], 'state'],
            [Contact::class, ['name' => 'Teste', 'type' => 'client', 'code' => 'CLI-9999'], 'code'],
            [PaymentType::class, ['name' => 'Teste', 'type' => 'both', 'id' => 5], 'id'],
        ];
    }

    #[DataProvider('invalidCatalogOperations')]
    public function test_catalog_rejects_bad_input(string $model, array $dados, string $campo): void
    {
        $this->operationFixtures();
        $this->expectOperationValidation(fn () => app(CatalogService::class)->create($model, $dados), $campo);
    }

    public function test_contact_codes_are_not_reused_after_archive_and_restore_keeps_history(): void
    {
        [$usuario] = $this->operationFixtures();
        $catalog = app(CatalogService::class);
        $contato = $catalog->create(Contact::class, ['name' => 'Cliente', 'type' => 'client']);
        $titulo = $this->operationTitle(['contact_id' => $contato->id]);
        $catalog->archive($contato);
        $this->assertTrue($titulo->fresh()->contact->trashed());
        $this->assertSame('CLI-0002', $catalog->create(Contact::class, ['name' => 'Outro', 'type' => 'client'])->code);
        $this->assertSame('FOR-0001', $catalog->create(Contact::class, ['name' => 'Fornecedor', 'type' => 'supplier'])->code);
        $this->assertSame('CON-0001', $catalog->create(Contact::class, ['name' => 'Ambos', 'type' => 'both'])->code);
        $restaurado = $catalog->restore($contato);
        $this->assertFalse($restaurado->trashed());
        $this->assertSame('CLI-0001', $restaurado->code);
        $this->operationFixtures();
        $this->assertSame('CLI-0001', $catalog->create(Contact::class, ['name' => 'Outro dono', 'type' => 'client'])->code);
        $this->actingAs($usuario);
        $this->assertCount(4, Contact::all());
    }

    public function test_catalog_type_and_opening_changes_preserve_linked_history(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $catalog = app(CatalogService::class);
        $categoria = $catalog->create(Category::class, ['name' => 'Receita', 'type' => 'income']);
        $contato = $catalog->create(Contact::class, ['name' => 'Cliente', 'type' => 'client']);
        $titulo = $this->operationTitle([
            'category_id' => $categoria->id, 'contact_id' => $contato->id, 'financial_account_id' => $conta->id,
        ]);
        $this->operationPayment($titulo, $conta, $forma);
        $titulo->delete();
        foreach ([[$categoria, 'expense'], [$contato, 'supplier'], [$conta, 'cash'], [$forma, 'payment']] as [$registro, $tipo]) {
            $this->expectOperationValidation(fn () => $catalog->update($registro, ['type' => $tipo]), 'type');
        }
        $this->expectOperationValidation(fn () => $catalog->update($conta, ['initial_balance' => '0.00']), 'initial_balance');
        $this->assertSame('-10.00', $catalog->update($conta, ['initial_balance' => '-10.00'])->initial_balance);
        $this->assertSame('Renomeado', $catalog->update($categoria, ['name' => 'Renomeado'])->name);
    }

    public function test_title_edit_preserves_parcel_ids_and_recalculates_money_dates_and_links(): void
    {
        [, $conta] = $this->operationFixtures();
        $titulo = $this->operationTitle(['installments_count' => 3]);
        $ids = $titulo->installments->pluck('id')->all();
        $editado = app(TitleService::class)->update($titulo, [
            'original_value' => '100.01', 'due_date' => '2027-01-31', 'financial_account_id' => $conta->id,
        ]);
        $this->assertSame($titulo->id, $editado->id);
        $this->assertSame($titulo->title_number, $editado->title_number);
        $this->assertSame($ids, $editado->installments->pluck('id')->all());
        $this->assertSame(['33.34', '33.34', '33.33'], $editado->installments->pluck('value')->all());
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'],
            $editado->installments->map(fn ($item) => $item->due_date->toDateString())->all());
        $this->assertSame([$conta->id, $conta->id, $conta->id], $editado->installments->pluck('financial_account_id')->all());
        $this->assertSame('pending', $editado->status);
        $semConta = app(TitleService::class)->update($editado, ['financial_account_id' => null]);
        $this->assertNull($semConta->financial_account_id);
        $this->assertSame([null, null, null], $semConta->installments->pluck('financial_account_id')->all());
    }

    public function test_paid_titles_only_allow_metadata_edits_and_never_rewrite_payments(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $titulo = $this->operationTitle();
        $baixa = $this->operationPayment($titulo, $conta, $forma);
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($titulo, ['original_value' => '50.00']), 'status');
        $editado = app(TitleService::class)->update($titulo, ['description' => 'Descricao nova', 'notes' => 'Observacao']);
        $this->assertSame('Descricao nova', $editado->description);
        $this->assertSame('paid', $editado->status);
        $this->assertSame('100.00', $baixa->fresh()->original_value);
        $this->assertSame('100.00', $editado->installments->sole()->value);
    }

    public function test_partial_titles_also_block_financial_edits(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $titulo = $this->operationTitle();
        $this->operationPayment($titulo, $conta, $forma, '40.00');
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($titulo, ['due_date' => '2026-11-30']), 'status');
        $this->assertSame('partial', app(TitleService::class)->update($titulo, ['reference' => 'Ajustado'])->status);
    }

    public function test_title_edit_rejects_foreign_links_and_identity_changes(): void
    {
        [$usuario] = $this->operationFixtures();
        $titulo = $this->operationTitle();
        foreach (['title_type', 'installments_count', 'user_id', 'title_number', 'parent_title_id', 'status'] as $campo) {
            $this->expectOperationValidation(fn () => app(TitleService::class)->update($titulo, [$campo => 1]), $campo);
        }
        [, $alheia] = $this->operationFixtures();
        $this->actingAs($usuario);
        try {
            app(TitleService::class)->update($titulo, ['financial_account_id' => $alheia->id]);
            $this->fail('Conta alheia deveria ser rejeitada.');
        } catch (ModelNotFoundException) {
            $this->assertNull($titulo->fresh()->financial_account_id);
        }
    }

    public function test_title_edit_rollback_restores_all_parcels_on_failure(): void
    {
        $this->operationFixtures();
        $titulo = $this->operationTitle(['installments_count' => 2]);
        $evento = 'eloquent.updating: '.TitleInstallment::class;
        $gravacoes = 0;
        Event::listen($evento, function () use (&$gravacoes) {
            if (++$gravacoes === 2) {
                throw new RuntimeException('Falha na segunda parcela.');
            }
        });
        try {
            app(TitleService::class)->update($titulo, ['original_value' => '200.00']);
            $this->fail('Edicao deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame('100.00', $titulo->fresh()->original_value);
            $this->assertSame(['50.00', '50.00'], $titulo->fresh()->installments->pluck('value')->all());
        } finally {
            Event::forget($evento);
        }
    }

    public function test_title_edit_rejects_cancelled_titles_and_foreign_objects(): void
    {
        [$usuario] = $this->operationFixtures();
        $titulo = $this->operationTitle();
        app(TitleService::class)->cancel($titulo);
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($titulo, ['notes' => 'Teste']), 'status');
        $this->operationFixtures();
        $alheio = $this->operationTitle();
        $this->actingAs($usuario);
        $alheio->user_id = $usuario->id;
        $this->expectException(ModelNotFoundException::class);
        app(TitleService::class)->update($alheio, ['description' => 'Invasao']);
    }

    public function test_recurrence_settings_cannot_change_on_a_generated_cycle(): void
    {
        [, $conta, $forma] = $this->operationFixtures();
        $titulo = $this->operationTitle(['is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31]);
        $this->operationPayment($titulo, $conta, $forma);
        $filho = Title::where('parent_title_id', $titulo->id)->firstOrFail();
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($filho, ['recurrence_day' => 30]), 'recurrence_type');
        $this->assertSame('120.00', app(TitleService::class)->update($filho, ['original_value' => '120.00'])->original_value);
    }

    public function test_overdue_job_is_owner_explicit_and_preserves_other_financial_states(): void
    {
        [$usuario, $conta, $forma] = $this->operationFixtures();
        $vencido = $this->operationTitle(['due_date' => '2026-10-10']);
        $hoje = $this->operationTitle(['due_date' => '2026-10-15']);
        $pago = $this->operationTitle(['due_date' => '2026-10-10']);
        $this->operationPayment($pago, $conta, $forma);
        $parcial = $this->operationTitle(['due_date' => '2026-10-10']);
        $this->operationPayment($parcial, $conta, $forma, '40.00');
        $parcial->installments->sole()->fresh()->update(['status' => 'pending']);
        $cancelado = $this->operationTitle(['due_date' => '2026-10-10']);
        app(TitleService::class)->cancel($cancelado);
        $apagado = $this->operationTitle(['due_date' => '2026-10-10']);
        $apagado->delete();
        $this->operationFixtures();
        $alheio = $this->operationTitle(['due_date' => '2026-10-10']);
        Auth::logout();
        $resultado = app(OverdueTitleService::class)->updateFor($usuario, '2026-10-15');
        $this->assertSame(['titles' => 1, 'installments' => 2], $resultado);
        $this->assertSame(['titles' => 0, 'installments' => 0], app(OverdueTitleService::class)->updateFor($usuario, '2026-10-15'));
        foreach ([$vencido->id => 'overdue', $hoje->id => 'pending', $pago->id => 'paid',
            $parcial->id => 'partial', $cancelado->id => 'cancelled', $apagado->id => 'pending', $alheio->id => 'pending'] as $id => $status) {
            $this->assertSame($status, DB::table('titles')->where('id', $id)->value('status'));
        }
        $this->assertNull(Auth::id());
    }

    public function test_overdue_command_respects_target_user_and_rejects_bad_options(): void
    {
        [$usuario] = $this->operationFixtures();
        $titulo = $this->operationTitle(['due_date' => '2026-10-10']);
        Auth::logout();
        $this->artisan('titles:update-overdue', ['--user' => $usuario->id, '--date' => '2026-10-15'])->assertSuccessful();
        $this->assertSame('overdue', DB::table('titles')->where('id', $titulo->id)->value('status'));
        $this->artisan('titles:update-overdue', ['--date' => 'invalid'])->assertFailed();
        $this->artisan('titles:update-overdue', ['--user' => 999999999])->assertFailed();
    }

    public function test_default_initialization_is_repeatable_owner_scoped_and_preserves_customization(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $custom = app(CatalogService::class)->create(PaymentType::class, [
            'name' => 'pix', 'type' => 'payment', 'active' => false,
        ]);
        $this->actingAs(User::factory()->create());
        $resultado = app(FinancialDefaultsService::class)->initialize($usuario);
        $this->assertSame(['categories' => 12, 'payment_types' => 6], $resultado);
        $this->assertSame(['categories' => 0, 'payment_types' => 0], app(FinancialDefaultsService::class)->initialize($usuario));
        $this->assertSame(0, Category::count());
        $this->actingAs($usuario);
        $this->assertSame('payment', $custom->fresh()->type);
        $this->assertFalse($custom->fresh()->active);
        $this->assertCount(12, Category::all());
        $this->assertCount(7, PaymentType::all());
        $this->assertSame(0, FinancialAccount::count());
    }

    public function test_seeders_and_initialize_command_never_create_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, User::count());
        $usuario = User::factory()->create();
        Auth::logout();
        $this->artisan('finance:initialize', ['user' => $usuario->id])->assertSuccessful();
        $this->artisan('finance:initialize', ['user' => $usuario->id])->assertSuccessful();
        $this->artisan('finance:initialize', ['user' => 999999999])->assertFailed();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, User::count());
        $this->assertSame(12, DB::table('categories')->where('user_id', $usuario->id)->count());
        $this->assertSame(7, DB::table('payment_types')->where('user_id', $usuario->id)->count());
        $this->assertNull(Auth::id());
    }

    public function test_default_initialization_rolls_back_every_group_on_failure(): void
    {
        $usuario = User::factory()->create();
        $evento = 'eloquent.creating: '.PaymentType::class;
        Event::listen($evento, fn () => throw new RuntimeException('Falha nas formas.'));
        try {
            app(FinancialDefaultsService::class)->initialize($usuario);
            $this->fail('Inicializacao deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame(0, DB::table('categories')->where('user_id', $usuario->id)->count());
            $this->assertSame(0, DB::table('payment_types')->where('user_id', $usuario->id)->count());
        } finally {
            Event::forget($evento);
        }
    }

    public function test_financial_operations_require_identity_when_called_from_ui(): void
    {
        [, $conta] = $this->operationFixtures();
        $titulo = $this->operationTitle();
        Auth::logout();
        foreach ([
            fn () => app(FinancialAccountService::class)->balance($conta),
            fn () => app(CatalogService::class)->create(Category::class, ['name' => 'Anonimo', 'type' => 'income']),
            fn () => app(CatalogService::class)->update($conta, ['name' => 'Anonimo']),
            fn () => app(TitleService::class)->update($titulo, ['notes' => 'Anonimo']),
        ] as $operacao) {
            try {
                $operacao();
                $this->fail('Operacao anonima deveria falhar.');
            } catch (AuthenticationException) {
                $this->assertNull(Auth::id());
            }
        }
    }

    public function test_title_edit_preserves_unchanged_inactive_links_but_rejects_new_ones(): void
    {
        [, $conta] = $this->operationFixtures();
        $titulo = $this->operationTitle(['financial_account_id' => $conta->id]);
        app(CatalogService::class)->update($conta, ['active' => false]);
        $editado = app(TitleService::class)->update($titulo, ['original_value' => '120.00']);
        $this->assertSame($conta->id, $editado->financial_account_id);
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($editado, [
            'financial_account_id' => $conta->id,
        ]), 'financial_account_id');
    }

    public function test_invalid_title_edits_preserve_original_money_and_dates(): void
    {
        $this->operationFixtures();
        $titulo = $this->operationTitle();
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($titulo, [
            'due_date' => '2026-02-31',
        ]), 'due_date');
        $this->expectOperationValidation(fn () => app(TitleService::class)->update($titulo, [
            'original_value' => '0',
        ]), 'original_value');
        $this->assertSame('100.00', $titulo->fresh()->original_value);
        $this->assertSame('2026-10-31', $titulo->fresh()->due_date->toDateString());
    }

    public function test_contact_restore_rejects_another_owner_even_for_archived_objects(): void
    {
        [$usuario] = $this->operationFixtures();
        $this->operationFixtures();
        $contato = app(CatalogService::class)->create(Contact::class, ['name' => 'Alheio', 'type' => 'client']);
        app(CatalogService::class)->archive($contato);
        $this->actingAs($usuario);
        $contato->user_id = $usuario->id;
        $this->expectException(ModelNotFoundException::class);
        app(CatalogService::class)->restore($contato);
    }

    public function test_overdue_command_without_target_processes_existing_users_without_changing_session(): void
    {
        $this->operationFixtures();
        $primeiro = $this->operationTitle(['due_date' => '2026-10-10']);
        [$usuario] = $this->operationFixtures();
        $segundo = $this->operationTitle(['due_date' => '2026-10-10']);
        $this->artisan('titles:update-overdue', ['--date' => '2026-10-15'])->assertSuccessful();
        $this->assertSame('overdue', DB::table('titles')->where('id', $primeiro->id)->value('status'));
        $this->assertSame('overdue', $segundo->fresh()->status);
        $this->assertSame($usuario->id, Auth::id());
    }
}
