<?php

namespace Tests\Feature;

use App\Livewire\Catalog;
use App\Livewire\TitleDetail;
use App\Livewire\Titles;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Title;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FinancialDefaultsService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use App\Support\Feedback;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Atalhos de arraste, confirmacoes obrigatorias, mensagens com icones e exclusao logica dos cadastros.
 * Os gestos chamam os mesmos metodos dos botoes; aqui se valida o contrato do servidor.
 */
class InteractionShortcutsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FinancialAccount $account;

    private PaymentType $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(10, 0));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->account = app(CatalogService::class)->create(FinancialAccount::class, ['name' => 'Conta real', 'type' => 'cash']);
        $this->method = app(CatalogService::class)->create(PaymentType::class, ['name' => 'Pix', 'type' => 'both']);
    }

    private function createTitle(array $data = []): Title
    {
        return app(TitleService::class)->create(array_replace([
            'title_type' => 'receivable', 'description' => 'Contrato',
            'original_value' => '100.00', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-16',
        ], $data));
    }

    private function settle(Title $title, string $value = '100.00')
    {
        return app(TitleService::class)->settle($title, $title->installments->first(), [
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-16', 'original_value' => $value,
            'payment_methods' => [['payment_type_id' => $this->method->id, 'financial_account_id' => $this->account->id, 'value' => $value]],
        ]);
    }

    private function titles()
    {
        return Livewire::test(Titles::class, ['type' => 'receivable']);
    }

    public function test_title_list_renders_swipe_rows_with_state_based_hints_and_help(): void
    {
        $open = $this->createTitle(['description' => 'Aberto']);
        $paid = $this->createTitle(['description' => 'Pago']);
        $this->settle($paid);

        $this->get(route('income'))->assertOk()
            ->assertSee('swipeRow(', false)
            ->assertSee('Registrar baixa')
            ->assertSee('Cancelar título')
            ->assertSee('Estornar baixa')
            ->assertSee('Baixa indisponível')
            ->assertSee('Ajuda: atalhos de arraste da tela de títulos')
            ->assertSee('Iniciar a baixa do título, quando permitida.')
            ->assertDontSee('Editar o registro no formulário ao lado.');
        $this->assertSame('pending', $open->fresh()->status);
    }

    public function test_swipe_right_opens_existing_settlement_flow_only_when_allowed(): void
    {
        $title = $this->createTitle();
        $this->titles()->call('startSettlement', $title->id)
            ->assertRedirect(route('titles.show', ['title' => $title->id, 'acao' => 'baixa']));

        $this->settle($title);
        $this->titles()->call('startSettlement', $title->id)->assertNoRedirect()
            ->assertSee('Não foi possível iniciar a baixa.')
            ->assertSee('Todas as parcelas deste título já foram pagas.');
        $this->assertDatabaseCount('title_settlements', 1);
    }

    public function test_swipe_left_without_payments_asks_confirmation_before_cancelling(): void
    {
        $title = $this->createTitle();
        $component = $this->titles()->call('startReversalOrCancellation', $title->id)
            ->assertSet('confirming', true)
            ->assertSet('confirmation.operation', 'cancel')
            ->assertSee('Confirmar cancelamento?')
            ->assertSee('Deseja realmente cancelar este título? Verifique as informações antes de prosseguir.')
            ->assertSee('Confirmar cancelamento');
        $this->assertSame('pending', $title->fresh()->status);

        // Cancelar o modal encerra o fluxo sem alterar dados.
        $component->call('dismissConfirmation')->assertSet('confirmation', null);
        $this->assertSame('pending', $title->fresh()->status);

        $component->call('startReversalOrCancellation', $title->id)->call('confirmOperation')
            ->assertHasNoErrors()->assertSee('Título cancelado com sucesso!')->assertSet('confirmation', null);
        $this->assertSame('cancelled', $title->fresh()->status);

        $component->call('startReversalOrCancellation', $title->id)
            ->assertSee('O título já está cancelado.')->assertSet('confirmation', null);
    }

    public function test_closing_modal_from_browser_discards_pending_confirmation(): void
    {
        $title = $this->createTitle();
        $this->titles()->call('startReversalOrCancellation', $title->id)
            ->set('confirming', false)->assertSet('confirmation', null)
            ->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('pending', $title->fresh()->status);
    }

    public function test_swipe_left_with_active_payment_opens_reversal_instead_of_cancelling(): void
    {
        $title = $this->createTitle();
        $this->settle($title, '40.00');
        $this->titles()->call('startReversalOrCancellation', $title->id)
            ->assertRedirect(route('titles.show', ['title' => $title->id, 'acao' => 'estorno']))
            ->assertSet('confirmation', null);
        $this->assertSame('partial', $title->fresh()->status);
        $this->assertDatabaseCount('settlement_reversals', 0);
    }

    public function test_cancellation_cannot_skip_confirmation_and_backend_revalidates_state(): void
    {
        $title = $this->createTitle();
        $this->titles()->call('cancelTitle', $title->id)->assertHasErrors('confirmation');
        $this->assertSame('pending', $title->fresh()->status);

        $component = $this->titles()->call('startReversalOrCancellation', $title->id);
        // Uma baixa registrada depois da abertura do modal impede o cancelamento.
        $this->settle($title, '10.00');
        $component->call('confirmOperation')->assertHasErrors('status')
            ->assertSee('Não foi possível concluir a operação.')
            ->assertDontSee('Título cancelado com sucesso!');
        $this->assertSame('partial', $title->fresh()->status);
    }

    public function test_confirmation_state_is_locked_and_foreign_titles_are_unavailable(): void
    {
        $title = $this->createTitle();
        $this->actingAs(User::factory()->create());
        try {
            $this->titles()->call('startReversalOrCancellation', $title->id);
            $this->fail('Título de outro usuário não deveria ser encontrado.');
        } catch (ModelNotFoundException) {
            $this->assertSame('pending', $title->fresh()->status);
        }

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->titles()->set('confirmation', ['operation' => 'cancel', 'target' => $title->id, 'subject' => null]);
    }

    public function test_detail_shortcuts_open_payment_and_latest_active_reversal(): void
    {
        $title = $this->createTitle();
        Livewire::test(TitleDetail::class, ['titleId' => $title->id, 'action' => 'baixa'])
            ->assertSet('installmentId', $title->installments->first()->id)
            ->assertNotSet('paymentKey', null)
            ->assertSee('Baixa da parcela 1');
        $this->assertDatabaseCount('title_settlements', 0);

        $first = $this->settle($title, '30.00');
        $latest = $this->settle($title->fresh('installments'), '20.00');
        Livewire::test(TitleDetail::class, ['titleId' => $title->id, 'action' => 'estorno'])
            ->assertSet('reversalId', $latest->id)
            ->assertSee('Informe o motivo do estorno.');
        $this->assertNotSame($first->id, $latest->id);
        $this->assertDatabaseCount('settlement_reversals', 0);

        $this->get(route('titles.show', ['title' => $title->id, 'acao' => 'estorno']))->assertOk()->assertSee('Estornar baixa #'.$latest->id);
        $this->get(route('titles.show', ['title' => $title->id, 'acao' => 'invalida']))->assertOk()->assertDontSee('Estornar baixa #');
    }

    public function test_detail_shortcut_reports_reason_when_settlement_is_not_allowed(): void
    {
        $title = $this->createTitle();
        $this->settle($title);
        Livewire::test(TitleDetail::class, ['titleId' => $title->id, 'action' => 'baixa'])
            ->assertHasErrors('status')->assertSet('installmentId', null);
    }

    public function test_sensitive_detail_operations_require_confirmation(): void
    {
        $title = $this->createTitle();
        $component = Livewire::test(TitleDetail::class, ['titleId' => $title->id])
            ->call('openPayment', $title->installments->first()->id)
            ->set('payment.payment_methods.0.payment_type_id', $this->method->id)
            ->set('payment.payment_methods.0.financial_account_id', $this->account->id)
            ->call('settle')->assertHasErrors('confirmation');
        $this->assertDatabaseCount('title_settlements', 0);

        $component->call('requestSettle')->assertSee('Confirmar baixa?')->call('confirmOperation')
            ->assertHasNoErrors()->assertSee('Baixa realizada com sucesso!');
        $settlement = $title->settlements()->sole();

        $component->call('openReversal', $settlement->id)->set('reversal.reason', 'Lançamento duplicado')
            ->call('reverse')->assertHasErrors('confirmation');
        $this->assertDatabaseCount('settlement_reversals', 0);
        $component->call('requestReverse')->assertSee('Estornar baixa?')
            ->assertSee('Deseja realmente estornar a baixa deste título? Essa ação poderá alterar a situação financeira do registro.')
            ->assertSee('Confirmar estorno')
            ->call('dismissConfirmation');
        $this->assertDatabaseCount('settlement_reversals', 0);
        $component->call('requestReverse')->call('confirmOperation')->assertHasNoErrors()->assertSee('Estorno realizado com sucesso!');
        $this->assertDatabaseCount('settlement_reversals', 1);

        $component->call('cancel')->assertHasErrors('confirmation');
        $this->assertNotSame('cancelled', $title->fresh()->status);
        $component->call('confirmCancel')->call('confirmOperation')->assertHasNoErrors()->assertSee('Título cancelado com sucesso!');
        $this->assertSame('cancelled', $title->fresh()->status);
    }

    public function test_repeated_confirmation_does_not_duplicate_operation(): void
    {
        $title = $this->createTitle();
        $component = Livewire::test(TitleDetail::class, ['titleId' => $title->id])
            ->call('openPayment', $title->installments->first()->id)
            ->set('payment.payment_methods.0.payment_type_id', $this->method->id)
            ->set('payment.payment_methods.0.financial_account_id', $this->account->id)
            ->call('requestSettle')->call('confirmOperation')->call('confirmOperation')->assertHasNoErrors();
        $this->assertDatabaseCount('title_settlements', 1);
        $component->call('settle')->assertSee('Pedido já confirmado anteriormente.');
        $this->assertDatabaseCount('title_settlements', 1);
    }

    public function test_discarding_open_request_uses_confirmation_modal(): void
    {
        $title = $this->createTitle();
        $component = Livewire::test(TitleDetail::class, ['titleId' => $title->id])
            ->call('openPayment', $title->installments->first()->id)
            ->call('requestClosePayment')->assertSee('Descartar pedido de baixa?');
        $component->call('dismissConfirmation')->assertNotSet('installmentId', null);
        $component->call('requestClosePayment')->call('confirmOperation')->assertSet('installmentId', null)->assertSet('confirmation', null);
    }

    public function test_catalog_swipe_right_edits_and_left_deletes_logically_after_confirmation(): void
    {
        $category = app(CatalogService::class)->create(Category::class, ['name' => 'Lazer', 'type' => 'expense']);
        $component = Livewire::test(Catalog::class, ['module' => 'categorias'])
            ->call('edit', $category->id)->assertSet('editing', $category->id)->assertDispatched('catalog-form-focus');

        $component->call('confirmDelete', $category->id)
            ->assertSet('confirmation.operation', 'delete')
            ->assertSee('Confirmar exclusão?')
            ->assertSee('Deseja realmente excluir este registro? Essa ação poderá afetar informações relacionadas, conforme as regras do sistema.')
            ->assertSee('Confirmar exclusão');
        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
        $component->call('dismissConfirmation');
        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);

        $component->call('delete', $category->id)->assertHasErrors('confirmation');
        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);

        $component->call('confirmDelete', $category->id)->call('confirmOperation')
            ->assertHasNoErrors()->assertSee('Categoria excluída com sucesso!')->assertSet('editing', null);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $component->call('edit', $category->id)->assertSet('editing', null)->assertSee('Registro excluído: restaure-o antes de editar.');
        $component->call('confirmDelete', $category->id)->assertSee('O registro já está excluído.')->assertSet('confirmation', null);
        $component->set('showDeleted', true)->assertSee('Lazer')->call('restore', $category->id)->assertSee('Categoria restaurada com sucesso!');
        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
        $this->get(route('catalog', 'categorias'))->assertSee('Ajuda: atalhos de arraste da tela de cadastros');
    }

    public function test_catalog_records_with_financial_history_must_be_deactivated_not_deleted(): void
    {
        $category = app(CatalogService::class)->create(Category::class, ['name' => 'Servicos', 'type' => 'income']);
        $this->createTitle(['category_id' => $category->id, 'financial_account_id' => $this->account->id]);

        foreach ([['categorias', $category], ['contas', $this->account]] as [$module, $record]) {
            Livewire::test(Catalog::class, ['module' => $module])
                ->assertSee('Exclusão indisponível')
                ->call('confirmDelete', $record->id)->assertSet('confirmation', null)
                ->assertSee('Este cadastro possui vínculos financeiros. Desative-o para preservar o histórico.');
            $this->assertNotSoftDeleted($record->getTable(), ['id' => $record->id]);
        }
        try {
            app(CatalogService::class)->delete($category);
            $this->fail('Exclusao de cadastro com vínculos deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delete', $exception->errors());
        }

        $this->settle(Title::sole());
        Livewire::test(Catalog::class, ['module' => 'formas'])->call('confirmDelete', $this->method->id)
            ->assertSee('Este cadastro possui vínculos financeiros.');
        $this->assertNotSoftDeleted('payment_types', ['id' => $this->method->id]);
    }

    public function test_contacts_keep_history_when_deleted_logically(): void
    {
        $contact = app(CatalogService::class)->create(Contact::class, ['name' => 'Cliente', 'type' => 'client']);
        $title = $this->createTitle(['contact_id' => $contact->id]);
        Livewire::test(Catalog::class, ['module' => 'contatos'])->call('confirmDelete', $contact->id)
            ->call('confirmOperation')->assertSee('Contato excluído com sucesso!');
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertSame('Cliente', $title->fresh()->contact->name);
    }

    public function test_deleted_catalog_records_leave_selections_and_cannot_be_linked(): void
    {
        $category = app(CatalogService::class)->create(Category::class, ['name' => 'Removida', 'type' => 'income']);
        app(CatalogService::class)->delete($category);

        $this->titles()->assertDontSee('Removida');
        $this->expectException(ModelNotFoundException::class);
        $this->createTitle(['category_id' => $category->id]);
    }

    public function test_defaults_initialization_does_not_recreate_deleted_defaults(): void
    {
        $default = config('financial_defaults.categories')[0]['name'];
        app(FinancialDefaultsService::class)->initialize($this->user, 'categories');
        app(CatalogService::class)->delete(Category::where('name', $default)->sole());

        $created = app(FinancialDefaultsService::class)->initialize($this->user, 'categories');
        $this->assertSame(0, $created['categories']);
        $this->assertSame(0, Category::where('name', $default)->count());
        $this->assertSame(1, Category::withTrashed()->where('name', $default)->count());
    }

    public function test_catalog_policies_allow_only_logical_delete_and_restore_for_owner(): void
    {
        foreach ([$this->account, $this->method] as $record) {
            $this->assertTrue(Gate::allows('delete', $record));
            $this->assertTrue(Gate::allows('restore', $record));
            $this->assertFalse(Gate::allows('forceDelete', $record));
        }
        $this->actingAs(User::factory()->create());
        $this->assertFalse(Gate::allows('delete', $this->account));
    }

    public function test_success_messages_follow_standard_with_entity_names(): void
    {
        $component = Livewire::test(Catalog::class, ['module' => 'contas'])
            ->set('form.name', 'Carteira')->call('save')->assertSee('Conta financeira cadastrada com sucesso!');
        $account = FinancialAccount::where('name', 'Carteira')->sole();
        $component->call('edit', $account->id)->set('form.name', 'Carteira digital')->call('save')
            ->assertSee('Conta financeira alterada com sucesso!');
        $component->call('toggle', $account->id)->assertSee('Conta financeira desativada com sucesso!');

        $this->titles()->set('form.description', 'Venda')->set('form.original_value', '10.00')->call('save')
            ->assertSee('Título cadastrado com sucesso!');
    }

    public function test_feedback_component_renders_icon_text_and_close_action(): void
    {
        $this->assertSame('o-trash', Feedback::kind('deleted')['icon']);
        $this->assertSame('o-arrow-uturn-left', Feedback::kind('reversed')['icon']);
        $this->assertSame('alert', Feedback::kind('error')['role']);
        $this->assertSame(0, Feedback::kind('warning')['timeout']);
        foreach (Feedback::CONFIRMATIONS as $operation => $config) {
            $this->assertNotEmpty($config['icon'], $operation);
            $this->assertNotEmpty($config['confirm'], $operation);
        }

        $title = $this->createTitle();
        Feedback::flash('settled', 'Baixa realizada com sucesso!', 'Detalhe');
        $html = $this->get(route('titles.show', $title))->assertOk()
            ->assertSee('Baixa realizada com sucesso!')
            ->assertSee('feedback--success', false)
            ->assertSee('aria-label="Fechar mensagem"', false)
            ->getContent();
        $this->assertStringContainsString('role="status"', $html);

        $this->expectException(InvalidArgumentException::class);
        Feedback::kind('desconhecido');
    }

    public function test_reversal_service_still_protects_reopened_title_after_shortcut(): void
    {
        $title = $this->createTitle();
        $settlement = $this->settle($title);
        app(SettlementReversalService::class)->reverse($settlement, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-16', 'reason' => 'Correção',
        ]);
        // Sem baixa ativa, o mesmo gesto passa a significar cancelamento.
        $this->titles()->call('startReversalOrCancellation', $title->id)->assertSet('confirmation.operation', 'cancel');
    }
}
