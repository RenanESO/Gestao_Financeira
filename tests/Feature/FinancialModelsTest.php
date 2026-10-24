<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\Notification;
use App\Models\PaymentType;
use App\Models\Receipt;
use App\Models\Scopes\OwnerScope;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\TitleSettlementMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinancialModelsTest extends TestCase
{
    use RefreshDatabase;

    public static function financialModels(): array
    {
        return [
            'category' => [Category::class],
            'account' => [FinancialAccount::class],
            'contact' => [Contact::class],
            'payment type' => [PaymentType::class],
            'title' => [Title::class],
            'installment' => [TitleInstallment::class],
            'settlement' => [TitleSettlement::class],
            'settlement method' => [TitleSettlementMethod::class],
            'receipt' => [Receipt::class],
            'notification' => [Notification::class],
        ];
    }

    #[DataProvider('financialModels')]
    public function test_queries_and_route_binding_only_expose_the_authenticated_owner(string $model): void
    {
        $primeiro = $this->createChain();
        $segundo = $this->createChain();
        $this->actingAs($primeiro['user']);

        $this->assertSame([$primeiro[$model]->id], $model::query()->pluck('id')->all());
        $this->assertNull($model::find($segundo[$model]->id));
        $this->assertNull((new $model)->resolveRouteBinding($segundo[$model]->id));
        $this->assertSame($primeiro[$model]->id, (new $model)->resolveRouteBinding($primeiro[$model]->id)->id);
        $this->assertSame(1, $model::count());
    }

    #[DataProvider('financialModels')]
    public function test_guest_queries_return_no_financial_records(string $model): void
    {
        $cadeia = $this->createChain();

        $this->assertSame(0, $model::count());
        $this->assertNull($model::find($cadeia[$model]->id));
        $this->assertFalse(Gate::allows('view', $cadeia[$model]));
        $this->assertFalse(Gate::allows('viewAny', $model));
        $this->assertFalse(Gate::allows('create', $model));
    }

    #[DataProvider('financialModels')]
    public function test_policies_check_the_persisted_owner_even_for_unscoped_objects(string $model): void
    {
        $primeiro = $this->createChain();
        $segundo = $this->createChain();
        $this->actingAs($primeiro['user']);
        $alheio = $segundo[$model];

        $this->assertTrue(Gate::allows('view', $primeiro[$model]));
        $this->assertTrue(Gate::forUser($segundo['user'])->allows('view', $alheio));

        foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $acao) {
            $this->assertFalse(Gate::allows($acao, $alheio), $acao);
        }

        // Manipular atributos locais nao deve transformar um registro alheio em proprio.
        $alheio->setAttribute('user_id', $primeiro['user']->id);
        $alheio->setAttribute('title_id', $primeiro[Title::class]->id);
        $alheio->setAttribute('settlement_id', $primeiro[TitleSettlement::class]->id);
        $this->assertFalse(Gate::allows('view', $alheio));
    }

    #[DataProvider('financialModels')]
    public function test_scoped_bulk_updates_and_deletes_do_not_touch_another_owner(string $model): void
    {
        $primeiro = $this->createChain();
        $segundo = $this->createChain();
        $this->actingAs($primeiro['user']);
        $alheio = $segundo[$model];

        $this->assertSame(0, $model::whereKey($alheio->id)->update(['updated_at' => '2026-10-15 12:00:00']));
        $this->assertSame(0, $model::whereKey($alheio->id)->delete());
        $this->assertTrue(DB::table($alheio->getTable())->where('id', $alheio->id)->exists());
    }

    #[DataProvider('financialModels')]
    public function test_identity_and_relationship_keys_are_not_mass_assignable(string $model): void
    {
        $registro = new $model;

        foreach (['id', 'user_id', 'title_id', 'settlement_id', 'financial_account_id',
            'contact_id', 'category_id', 'payment_type_id', 'parent_title_id',
            'title_installment_id', 'notifiable_id', 'notifiable_type', 'deleted_at'] as $campo) {
            $this->assertFalse($registro->isFillable($campo), $campo);
        }
    }

    public function test_complete_chain_and_inverse_relationships_can_be_loaded(): void
    {
        $cadeia = $this->createChain();
        $usuario = $cadeia['user'];
        $this->actingAs($usuario);
        $titulo = Title::with([
            'user', 'contact', 'category', 'financialAccount',
            'installments.financialAccount', 'installments.settlements',
            'settlements.installment', 'settlements.methods.paymentType',
            'settlements.methods.financialAccount', 'settlements.receipt.user',
        ])->findOrFail($cadeia[Title::class]->id);

        $this->assertTrue($titulo->user->is($usuario));
        $this->assertTrue($titulo->contact->is($cadeia[Contact::class]));
        $this->assertTrue($titulo->category->is($cadeia[Category::class]));
        $this->assertTrue($titulo->financialAccount->is($cadeia[FinancialAccount::class]));
        $this->assertTrue($titulo->installments->sole()->financialAccount->is($cadeia[FinancialAccount::class]));
        $baixa = $titulo->settlements->sole();
        $this->assertTrue($baixa->installment->is($titulo->installments->sole()));
        $this->assertTrue($titulo->installments->sole()->settlements->sole()->is($baixa));
        $this->assertTrue($baixa->methods->sole()->settlement->is($baixa));
        $this->assertTrue($baixa->methods->sole()->paymentType->is($cadeia[PaymentType::class]));
        $this->assertTrue($baixa->methods->sole()->financialAccount->is($cadeia[FinancialAccount::class]));
        $this->assertTrue($baixa->receipt->settlement->is($baixa));
        $this->assertTrue($baixa->receipt->user->is($usuario));
        $this->assertTrue($titulo->category->titles->sole()->is($titulo));
        $this->assertTrue($titulo->contact->titles->sole()->is($titulo));
        $this->assertTrue($titulo->financialAccount->titles->sole()->is($titulo));
        $this->assertTrue($titulo->financialAccount->installments->sole()->is($titulo->installments->sole()));
        $this->assertTrue($titulo->financialAccount->settlementMethods->sole()->is($baixa->methods->sole()));
        $this->assertTrue($cadeia[PaymentType::class]->settlementMethods->sole()->is($baixa->methods->sole()));

        foreach (['categories', 'financialAccounts', 'contacts', 'paymentTypes',
            'titles', 'receipts', 'financialNotifications'] as $relacao) {
            $this->assertCount(1, $usuario->$relacao);
        }

        foreach ([Category::class, FinancialAccount::class, Contact::class,
            PaymentType::class, Notification::class] as $model) {
            $this->assertTrue($cadeia[$model]->user->is($usuario));
        }
    }

    public function test_money_dates_booleans_and_integer_fields_have_domain_casts(): void
    {
        $cadeia = $this->createChain();
        $this->actingAs($cadeia['user']);

        $this->assertSame('10.25', $cadeia[FinancialAccount::class]->initial_balance);
        $this->assertTrue($cadeia[Category::class]->active);
        $this->assertTrue($cadeia[Contact::class]->active);
        $this->assertTrue($cadeia[PaymentType::class]->active);
        $this->assertTrue($cadeia[FinancialAccount::class]->active);
        $titulo = $cadeia[Title::class];
        $this->assertSame('100.10', $titulo->original_value);
        $this->assertSame(1, $titulo->installments_count);
        $this->assertSame(1, $titulo->current_installment);
        $this->assertFalse($titulo->is_recurring);
        $this->assertNull($titulo->recurrence_day);
        $this->assertSame('2026-10-01', $titulo->issue_date->format('Y-m-d'));
        $this->assertSame('2026-10-31', $titulo->due_date->format('Y-m-d'));
        $this->assertSame('100.10', $cadeia[TitleInstallment::class]->value);
        $this->assertSame(1, $cadeia[TitleInstallment::class]->installment_number);
        $this->assertSame('2026-10-31', $cadeia[TitleInstallment::class]->due_date->format('Y-m-d'));
        $this->assertSame('2026-10-15', $cadeia[TitleSettlement::class]->settlement_date->format('Y-m-d'));
        foreach (['original_value' => '100.10', 'total_value' => '100.10',
            'discount' => '0.00', 'interest' => '0.00', 'fine' => '0.00'] as $campo => $valor) {
            $this->assertSame($valor, $cadeia[TitleSettlement::class]->$campo);
        }
        $this->assertSame('100.10', $cadeia[TitleSettlementMethod::class]->value);
        $this->assertSame('2026-10-15', $cadeia[Receipt::class]->issue_date->format('Y-m-d'));
    }

    public function test_soft_deletes_preserve_history_and_owner_isolation(): void
    {
        $cadeia = $this->createChain();
        $outro = $this->createChain();
        $this->actingAs($cadeia['user']);
        $cadeia[Contact::class]->delete();
        $cadeia[Title::class]->delete();

        $this->assertSame(0, Contact::count());
        $this->assertSame(0, Title::count());
        $this->assertSame(1, Contact::withTrashed()->count());
        $this->assertSame(1, Title::withTrashed()->count());
        $this->assertNull(Title::withTrashed()->find($outro[Title::class]->id));
        $this->assertTrue(TitleSettlement::firstOrFail()->title->trashed());
        $this->assertTrue(TitleInstallment::firstOrFail()->title->trashed());
        $this->assertTrue(TitleSettlement::firstOrFail()->title->contact->trashed());
        $this->assertSame(1, TitleSettlementMethod::count());
        $this->assertSame(1, Receipt::count());
        $this->assertTrue(Gate::allows('restore', $cadeia[Title::class]));
        $this->assertFalse(Gate::allows('forceDelete', $cadeia[Title::class]));

        $this->actingAs($outro['user']);
        $this->assertNull(TitleSettlement::find($cadeia[TitleSettlement::class]->id));
        $this->assertFalse(Gate::allows('view', $cadeia[Title::class]));
    }

    public function test_optional_links_and_parent_child_relationships_are_supported(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $raiz = $usuario->titles()->create($this->titleData('REC-0001'));
        $filho = new Title(array_replace($this->titleData('REC-0002'), [
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));
        $filho->parent()->associate($raiz);
        $usuario->titles()->save($filho);

        $this->assertNull($raiz->contact);
        $this->assertNull($raiz->category);
        $this->assertNull($raiz->financialAccount);
        $this->assertNull($raiz->parent);
        $this->assertTrue($raiz->children->sole()->is($filho));
        $this->assertTrue($filho->parent->is($raiz));
        $this->assertSame(31, $filho->fresh()->recurrence_day);
        $this->assertTrue($filho->fresh()->is_recurring);

        $raiz->delete();
        $this->assertTrue($filho->fresh()->parent->trashed());
    }

    public function test_custom_notifications_have_polymorphic_links_and_unread_scope(): void
    {
        $cadeia = $this->createChain();
        $this->actingAs($cadeia['user']);
        $notificacao = $cadeia[Notification::class];

        $this->assertTrue($notificacao->notifiable->is($cadeia[Title::class]));
        $this->assertSame(1, $cadeia['user']->financialNotifications()->unread()->count());
        $notificacao->update(['read_at' => now()]);
        $this->assertNotNull($notificacao->fresh()->read_at);
        $this->assertSame(0, Notification::unread()->count());
    }

    public function test_guest_scope_is_restored_after_logout(): void
    {
        $cadeia = $this->createChain();
        $this->actingAs($cadeia['user']);
        $this->assertSame(1, Title::count());
        Auth::logout();
        $this->assertSame(0, Title::count());
    }

    public function test_history_records_cannot_be_deleted_or_updated_through_crud_policies(): void
    {
        $cadeia = $this->createChain();
        $this->actingAs($cadeia['user']);

        foreach ([TitleInstallment::class, TitleSettlement::class, TitleSettlementMethod::class,
            Receipt::class] as $model) {
            $this->assertFalse(Gate::allows('update', $cadeia[$model]));
            $this->assertFalse(Gate::allows('delete', $cadeia[$model]));
            $this->assertFalse(Gate::allows('forceDelete', $cadeia[$model]));
        }
        $this->assertTrue(Gate::allows('update', $cadeia[Title::class]));
        $this->assertTrue(Gate::allows('delete', $cadeia[Title::class]));
    }

    public function test_relations_and_eager_loading_do_not_expose_another_user(): void
    {
        $primeiro = $this->createChain();
        $segundo = $this->createChain();
        $this->actingAs($primeiro['user']);

        $this->assertCount(0, $segundo['user']->titles);
        $this->assertNull($segundo[TitleInstallment::class]->title);
        $this->assertNull($segundo[Receipt::class]->settlement);
        $this->assertNull($segundo[TitleSettlementMethod::class]->settlement);
        $this->assertCount(0, $segundo[Title::class]->load('installments', 'settlements')->installments);
        $this->assertCount(0, $segundo[Title::class]->settlements);
    }

    private function titleData(string $numero): array
    {
        return [
            'title_number' => $numero, 'title_type' => 'receivable', 'description' => 'Título de teste',
            'original_value' => '100.10', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        ];
    }

    private function createChain(): array
    {
        $usuario = User::factory()->create();
        $ids = [];
        $ids[Category::class] = DB::table('categories')->insertGetId([
            'user_id' => $usuario->id, 'name' => 'Salario', 'type' => 'income',
        ]);
        $ids[FinancialAccount::class] = DB::table('financial_accounts')->insertGetId([
            'user_id' => $usuario->id, 'name' => 'Conta', 'type' => 'checking', 'initial_balance' => '10.25',
        ]);
        $ids[Contact::class] = DB::table('contacts')->insertGetId([
            'user_id' => $usuario->id, 'code' => 'CLI-0001', 'name' => 'Contato', 'type' => 'client',
        ]);
        $ids[PaymentType::class] = DB::table('payment_types')->insertGetId([
            'user_id' => $usuario->id, 'name' => 'PIX', 'type' => 'both',
        ]);
        $ids[Title::class] = DB::table('titles')->insertGetId(array_merge($this->titleData('REC-0001'), [
            'user_id' => $usuario->id, 'category_id' => $ids[Category::class],
            'contact_id' => $ids[Contact::class], 'financial_account_id' => $ids[FinancialAccount::class],
        ]));
        $ids[TitleInstallment::class] = DB::table('title_installments')->insertGetId([
            'title_id' => $ids[Title::class], 'financial_account_id' => $ids[FinancialAccount::class],
            'installment_number' => 1, 'value' => '100.10', 'due_date' => '2026-10-31',
        ]);
        $ids[TitleSettlement::class] = DB::table('title_settlements')->insertGetId([
            'title_id' => $ids[Title::class], 'title_installment_id' => $ids[TitleInstallment::class],
            'settlement_date' => '2026-10-15', 'original_value' => '100.10', 'total_value' => '100.10',
        ]);
        $ids[TitleSettlementMethod::class] = DB::table('title_settlement_methods')->insertGetId([
            'settlement_id' => $ids[TitleSettlement::class], 'payment_type_id' => $ids[PaymentType::class],
            'financial_account_id' => $ids[FinancialAccount::class], 'value' => '100.10',
        ]);
        $ids[Receipt::class] = DB::table('receipts')->insertGetId([
            'user_id' => $usuario->id, 'settlement_id' => $ids[TitleSettlement::class],
            'receipt_number' => sprintf('RCB-%s', $usuario->id), 'issue_date' => '2026-10-15',
            'pdf_path' => "receipts/{$usuario->id}/teste.pdf",
        ]);
        $ids[Notification::class] = DB::table('notifications')->insertGetId([
            'user_id' => $usuario->id, 'title' => 'Vencimento', 'message' => 'Título próximo ao vencimento',
            'type' => 'warning', 'notifiable_type' => Title::class, 'notifiable_id' => $ids[Title::class],
        ]);

        $cadeia = ['user' => $usuario];
        foreach ($ids as $model => $id) {
            /** @var Model $registro */
            $registro = $model::withoutGlobalScope(OwnerScope::class)->findOrFail($id);
            $cadeia[$model] = $registro;
        }

        return $cadeia;
    }
}
