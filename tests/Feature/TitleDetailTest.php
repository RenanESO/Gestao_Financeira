<?php

namespace Tests\Feature;

use App\Livewire\TitleDetail;
use App\Livewire\Titles;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Receipt;
use App\Models\Title;
use App\Models\TitleSettlement;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FinancialAccountService;
use App\Services\TitleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToWriteFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class TitleDetailTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    private FinancialAccount $account;

    private PaymentType $method;

    private Title $title;

    private string $receiptRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(10, 0));
        $this->receiptRoot = sys_get_temp_dir().'/moneycontrol-detail-'.Str::uuid();
        config(['filesystems.disks.receipts.root' => $this->receiptRoot]);
        Storage::forgetDisk('receipts');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->account = app(CatalogService::class)->create(FinancialAccount::class, ['name' => 'Conta real', 'type' => 'cash']);
        $this->method = app(CatalogService::class)->create(PaymentType::class, ['name' => 'Pix', 'type' => 'both']);
        $this->title = $this->createTitle();
    }

    protected function tearDown(): void
    {
        try {
            (new Filesystem)->deleteDirectory($this->receiptRoot);
            Storage::forgetDisk('receipts');
            // SQLite nao permite DROP da tabela com autorreferencias restritivas preenchidas.
            $this->assertSame('sqlite', DB::getDriverName());
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
            DB::table('titles')->update(['parent_title_id' => null]);
        } finally {
            parent::tearDown();
        }
    }

    private function createTitle(array $data = []): Title
    {
        return app(TitleService::class)->create(array_replace([
            'title_type' => 'receivable', 'description' => 'Contrato',
            'original_value' => '100.00', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-16',
        ], $data));
    }

    private function detail(?Title $title = null)
    {
        return Livewire::test(TitleDetail::class, ['titleId' => ($title ?? $this->title)->id]);
    }

    private function payment($component, string $value = '100.00')
    {
        return $component->call('openPayment', $this->title->installments->first()->id)
            ->set('payment.original_value', $value)
            ->set('payment.payment_methods.0.payment_type_id', $this->method->id)
            ->set('payment.payment_methods.0.financial_account_id', $this->account->id)
            ->set('payment.payment_methods.0.value', $value);
    }

    public function test_full_payment_and_replay_create_one_movement(): void
    {
        $component = $this->payment($this->detail());
        $key = $component->get('paymentKey');
        $component->call('requestSettle')->call('confirmOperation')->assertHasNoErrors()->assertSee('Pedido confirmado');
        $component->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame($key, $component->get('paymentKey'));
        $this->assertDatabaseCount('title_settlements', 1);
        $this->assertSame('paid', $this->title->fresh()->status);
        $this->assertSame('100.00', app(FinancialAccountService::class)->balance($this->account)['balance']);
    }

    public function test_partial_payment_and_explicit_new_request_pay_remaining(): void
    {
        $component = $this->payment($this->detail(), '30.00')->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('partial', $this->title->fresh()->status);
        $key = $component->get('paymentKey');
        $component->call('closePayment')->call('openPayment', $this->title->installments->first()->id);
        $this->assertNotSame($key, $component->get('paymentKey'));
        $component->assertSet('payment.original_value', '70,00')
            ->set('payment.payment_methods.0.payment_type_id', $this->method->id)
            ->set('payment.payment_methods.0.financial_account_id', $this->account->id)
            ->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertDatabaseCount('title_settlements', 2);
        $this->assertSame('paid', $this->title->fresh()->status);
    }

    public function test_installments_and_history_show_due_date_settlement_date_and_values(): void
    {
        $component = $this->payment($this->detail(), '30.00')
            ->assertSee('Vencimento da parcela')->assertSee('Vence hoje')
            ->set('payment.discount', '1.00')->set('payment.payment_methods.0.value', '29.00')
            ->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $settlement = TitleSettlement::sole();

        $component->assertSee('Valor da parcela')->assertSee('Data da baixa')
            ->assertDontSee('Principal original')->assertDontSee('Principal restante')
            ->assertSee('Restante R$ 70,00')
            ->assertSee($this->title->title_number.'/1 · 16/10/2026 · R$ 100,00')
            ->assertSee('Baixa #'.$settlement->id)->assertSee('Vencimento 16/10/2026')
            ->assertSee('Desconto R$ 1,00')->assertSee('Juros R$ 0,00')->assertSee('Multa R$ 0,00')
            ->assertSee('Pix · Conta real · R$ 29,00')
            ->assertSeeHtml('<span class="block whitespace-nowrap">16/10/2026</span>');

        // Baixa estornada deixa de aparecer como data de baixa da parcela.
        $component->call('openReversal', $settlement->id)->set('reversal.reason', 'Correção')
            ->call('requestReverse')->call('confirmOperation')->assertHasNoErrors()
            ->assertDontSee('Restante R$ 70,00')
            ->assertDontSeeHtml('<span class="block whitespace-nowrap">16/10/2026</span>');
    }

    public function test_multiple_methods_with_discount_interest_and_fine(): void
    {
        $component = $this->payment($this->detail(), '50.00')
            ->set('payment.discount', '5.00')->set('payment.interest', '2.00')->set('payment.fine', '1.00')
            ->set('payment.payment_methods.0.value', '20.00')->call('addMethod')
            ->set('payment.payment_methods.1.payment_type_id', $this->method->id)
            ->set('payment.payment_methods.1.financial_account_id', $this->account->id)
            ->set('payment.payment_methods.1.value', '28.00')
            ->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('48.00', TitleSettlement::sole()->total_value);
        $this->assertSame('48.00', app(FinancialAccountService::class)->balance($this->account)['balance']);
        $component->assertSee('R$ 50,00');
    }

    public function test_payment_summary_compares_adjusted_total_with_methods_before_submission(): void
    {
        $component = $this->payment($this->detail(), '50.00')
            ->set('payment.discount', '5.00')
            ->set('payment.interest', '2.00')
            ->set('payment.fine', '1.00')
            ->set('payment.payment_methods.0.value', '47.00');

        $component->assertSee('Total esperado')
            ->assertSee('R$ 48,00')
            ->assertSee('Faltam R$ 1,00');

        $component->set('payment.payment_methods.0.value', '48.00')
            ->assertSee('Valores conferem');
    }

    public function test_failed_validation_keeps_key_and_allows_correction(): void
    {
        $component = $this->payment($this->detail(), '30.00');
        $key = $component->get('paymentKey');
        $component->set('payment.payment_methods.0.value', '29.00')->call('requestSettle')->call('confirmOperation')->assertHasErrors('payment_methods');
        $this->assertSame($key, $component->get('paymentKey'));
        $this->assertDatabaseCount('title_settlements', 0);
        $component->set('payment.payment_methods.0.value', '30.00')->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
    }

    public function test_payment_requires_active_account_and_compatible_method_before_opening_request(): void
    {
        app(CatalogService::class)->update($this->account, ['active' => false]);
        $this->detail()->call('openPayment', $this->title->installments->first()->id)
            ->assertHasErrors('payment')
            ->assertSee('Cadastre e ative uma conta financeira');

        app(CatalogService::class)->update($this->account, ['active' => true]);
        app(CatalogService::class)->update($this->method, ['active' => false]);
        $this->detail()->call('openPayment', $this->title->installments->first()->id)
            ->assertHasErrors('payment')
            ->assertSee('Cadastre e ative uma forma compatível');
    }

    public function test_stale_snapshot_replay_is_idempotent_and_changed_payload_is_rejected(): void
    {
        // Snapshot capturado com a confirmacao pendente, antes do envio ao Service.
        $first = $this->payment($this->detail(), '30.00')->call('requestSettle');
        $snapshot = $first->snapshot;
        $first->call('confirmOperation')->assertHasNoErrors();
        $payload = ['components' => [[
            'snapshot' => json_encode($snapshot), 'updates' => [],
            'calls' => [['method' => 'confirmOperation', 'params' => [], 'path' => '']],
        ]]];
        $this->withHeaders(['X-Livewire' => 'true'])->postJson(app('livewire')->getUpdateUri(), $payload)->assertOk();
        $payload['components'][0]['updates'] = ['payment.original_value' => '20.00', 'payment.payment_methods.0.value' => '20.00'];
        $response = $this->postJson(app('livewire')->getUpdateUri(), $payload)->assertOk();
        $this->assertStringContainsString('Esta chave já foi usada para outra solicitação.', $response->json('components.0.effects.html'));
        $this->assertDatabaseCount('title_settlements', 1);
    }

    public function test_reversal_and_replay_restore_balance_preserving_history(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $settlement = TitleSettlement::sole();
        $component->call('openReversal', $settlement->id)->set('reversal.reason', 'Correção de pagamento')
            ->call('requestReverse')->call('confirmOperation')->assertHasNoErrors()->call('requestReverse')->call('confirmOperation')->assertHasNoErrors();
        $this->assertDatabaseCount('settlement_reversals', 1);
        $this->assertDatabaseCount('title_settlements', 1);
        $this->assertSame('pending', $this->title->fresh()->status);
        $this->assertSame('0.00', app(FinancialAccountService::class)->balance($this->account)['balance']);
        $this->assertSame('100.00', $settlement->fresh()->original_value);
        $component->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertDatabaseCount('title_settlements', 1);
    }

    public function test_reversal_requires_reason_and_non_future_date(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $component->call('openReversal', TitleSettlement::sole()->id)
            ->set('reversal.reversal_date', '2026-10-17')->call('requestReverse')->call('confirmOperation')
            ->assertHasErrors(['reason', 'reversal_date']);
        $this->assertDatabaseCount('settlement_reversals', 0);
    }

    public function test_receipt_is_separate_idempotent_and_invalidated_by_reversal(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $this->assertDatabaseCount('receipts', 0);
        $settlement = TitleSettlement::sole();
        $component->call('emitReceipt', $settlement->id)->assertHasNoErrors()
            ->call('emitReceipt', $settlement->id)->assertHasNoErrors();
        $receipt = Receipt::sole();
        $this->assertTrue(Storage::disk('receipts')->exists($receipt->pdf_path));
        $this->get(route('receipts.download', $receipt))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $component->call('openReversal', $settlement->id)->set('reversal.reason', 'Correção')
            ->call('requestReverse')->call('confirmOperation')->assertHasNoErrors()->call('emitReceipt', $settlement->id)->assertHasErrors('receipt');
        $this->assertNotNull($receipt->fresh()->voided_at);
        $this->assertTrue(Storage::disk('receipts')->exists($receipt->pdf_path));
    }

    public function test_corrupt_pdf_is_not_replaced_and_payment_is_preserved(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $id = TitleSettlement::sole()->id;
        $component->call('emitReceipt', $id);
        $receipt = Receipt::sole();
        Storage::disk('receipts')->put($receipt->pdf_path, 'corrupted');
        $component->call('emitReceipt', $id)->assertHasErrors('receipt');
        $this->assertSame('corrupted', Storage::disk('receipts')->get($receipt->pdf_path));
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame('paid', $this->title->fresh()->status);
    }

    public function test_missing_pdf_displays_error_without_regeneration(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $id = TitleSettlement::sole()->id;
        $component->call('emitReceipt', $id);
        $receipt = Receipt::sole();
        Storage::disk('receipts')->delete($receipt->pdf_path);
        $component->call('emitReceipt', $id)->assertHasErrors('receipt')->assertSee('PDF ausente');
        $this->assertFalse(Storage::disk('receipts')->exists($receipt->pdf_path));
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame('paid', $this->title->fresh()->status);
    }

    public function test_storage_failure_does_not_undo_payment(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $disk = \Mockery::mock(FilesystemAdapter::class)->makePartial();
        $disk->shouldReceive('put')->once()->andThrow(UnableToWriteFile::atLocation('test.pdf'));
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('receipts')->andReturn($disk);
        Storage::shouldReceive('forgetDisk')->with('receipts')->once();
        $component->call('emitReceipt', TitleSettlement::sole()->id)->assertHasErrors('receipt');
        $this->assertDatabaseCount('receipts', 0);
        $this->assertSame('paid', $this->title->fresh()->status);
    }

    public function test_edit_preserves_installment_ids_and_only_changes_submitted_fields(): void
    {
        $title = $this->createTitle(['installments_count' => 3, 'financial_account_id' => $this->account->id]);
        app(CatalogService::class)->update($this->account, ['active' => false]);
        $ids = $title->installments->pluck('id')->all();
        $this->detail($title)->call('openEdit')->set('edit.original_value', '10.00')->call('saveEdit')->assertHasNoErrors();
        $title->refresh();
        $this->assertSame($ids, $title->installments->pluck('id')->all());
        $this->assertSame(['3.34', '3.33', '3.33'], $title->installments->pluck('value')->all());
        $this->assertSame($this->account->id, $title->financial_account_id);
    }

    public function test_edit_can_enable_yearly_recurrence_before_any_payment(): void
    {
        $this->detail()->call('openEdit')
            ->set('edit.is_recurring', true)
            ->set('edit.recurrence_type', 'yearly')
            ->set('edit.recurrence_day', 20)
            ->set('edit.recurrence_month', 11)
            ->set('edit.due_date', '2026-11-20')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $title = $this->title->fresh()->load('installments');
        $this->assertTrue($title->is_recurring);
        $this->assertSame('yearly', $title->recurrence_type);
        $this->assertSame(20, $title->recurrence_day);
        $this->assertSame(11, $title->recurrence_month);
        $this->assertSame('2026-11-20', $title->installments->sole()->due_date->toDateString());
    }

    public function test_edit_can_disable_recurrence_before_any_payment(): void
    {
        $title = $this->createTitle([
            'is_recurring' => true,
            'recurrence_type' => 'monthly',
            'recurrence_day' => 16,
        ]);

        $this->detail($title)->call('openEdit')
            ->set('edit.is_recurring', false)
            ->call('saveEdit')
            ->assertHasNoErrors();

        $title->refresh();
        $this->assertFalse($title->is_recurring);
        $this->assertNull($title->recurrence_type);
        $this->assertNull($title->recurrence_day);
        $this->assertNull($title->recurrence_month);
    }

    public function test_metadata_edit_after_reversal_and_financial_injection_rejected(): void
    {
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $component->call('openReversal', TitleSettlement::sole()->id)->set('reversal.reason', 'Correção')->call('requestReverse')->call('confirmOperation')
            ->call('openEdit')->set('edit.original_value', '1.00')->call('saveEdit')->assertHasErrors('original_value');
        $component->call('openEdit')->set('edit.is_recurring', true)->call('saveEdit')->assertHasErrors('is_recurring');
        $component->call('openEdit')->set('edit.description', 'Descrição corrigida')->call('saveEdit')->assertHasNoErrors();
        $this->assertSame('Descrição corrigida', $this->title->fresh()->description);
        $this->assertSame('100.00', $this->title->fresh()->original_value);
    }

    public function test_payment_during_open_edit_blocks_financial_change(): void
    {
        $editor = $this->detail()->call('openEdit')->set('edit.original_value', '80.00');
        $this->payment($this->detail(), '30.00')->call('requestSettle')->call('confirmOperation');
        $editor->call('saveEdit')->assertHasErrors('status');
        $this->assertSame('100.00', $this->title->fresh()->original_value);
    }

    public function test_cancel_requires_reversing_active_payments(): void
    {
        $component = $this->payment($this->detail(), '30.00')->call('requestSettle')->call('confirmOperation')
            ->call('confirmCancel')->assertHasErrors('status');
        $component->call('openReversal', TitleSettlement::sole()->id)->set('reversal.reason', 'Correção')
            ->call('requestReverse')->call('confirmOperation')->call('confirmCancel')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('cancelled', $this->title->fresh()->status);
        $this->assertDatabaseCount('title_settlements', 1);
        $this->assertDatabaseCount('settlement_reversals', 1);
    }

    public function test_monthly_recurrence_creation_payment_and_reversal(): void
    {
        Livewire::test(Titles::class, ['type' => 'payable'])
            ->set('form.description', 'Mensalidade')->set('form.original_value', '100.00')
            ->set('form.due_date', '2026-10-31')->set('form.is_recurring', true)
            ->set('form.recurrence_type', 'monthly')->set('form.recurrence_day', 31)
            ->call('save')->assertHasNoErrors();
        $this->title = Title::where('description', 'Mensalidade')->sole()->load('installments');
        $component = $this->payment($this->detail())->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $child = $this->title->children()->sole();
        $this->assertSame('2026-11-30', $child->due_date->toDateString());
        $component->call('openReversal', $this->title->settlements()->sole()->id)
            ->set('reversal.reason', 'Correção')->call('requestReverse')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('cancelled', $child->fresh()->status);
    }

    public function test_yearly_recurrence_ignores_installments_and_manual_due_date(): void
    {
        Livewire::test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Anuidade')->set('form.original_value', '50.00')
            ->set('form.installments_count', 2)->set('form.due_date', '2026-12-01')
            ->set('form.is_recurring', true)->set('form.recurrence_type', 'yearly')
            ->set('form.recurrence_day', 10)->set('form.recurrence_month', 3)
            ->assertSee('10/03/2027')->assertSee('Uma parcela por ciclo.')
            ->call('save')->assertHasNoErrors();
        $title = Title::where('description', 'Anuidade')->sole();
        $this->assertSame('yearly', $title->recurrence_type);
        $this->assertSame(1, $title->installments_count);
        $this->assertSame('2027-03-10', $title->due_date->toDateString());
    }

    public function test_monthly_recurrence_due_date_is_calculated_from_issue_date(): void
    {
        $component = Livewire::test(Titles::class, ['type' => 'payable'])
            ->set('form.description', 'Aluguel')->set('form.original_value', '900.00')
            ->set('form.is_recurring', true)->set('form.recurrence_day', 10)
            ->assertSee('10/11/2026')
            // Para registrar o ciclo de outubro (dia 10 ja passou), basta uma emissao anterior.
            ->set('form.issue_date', '2026-10-01')->assertSee('10/10/2026')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('2026-10-10', Title::where('description', 'Aluguel')->sole()->due_date->toDateString());

        $component->set('form.is_recurring', true)->set('form.recurrence_day', 40)
            ->set('form.description', 'Inválido')->set('form.original_value', '1.00')
            ->assertSee('Informe emissão e dia previsto')
            ->call('save')->assertHasErrors('recurrence_day');
        $this->assertSame(0, Title::where('description', 'Inválido')->count());
    }

    public function test_settlement_history_is_ordered_by_installment_number(): void
    {
        $title = $this->createTitle(['description' => 'Parcelado', 'installments_count' => 3, 'original_value' => '300.00']);
        $settle = function (int $number, string $date) use ($title) {
            app(TitleService::class)->settle($title, $title->installments->firstWhere('installment_number', $number), [
                'idempotency_key' => (string) Str::uuid(), 'settlement_date' => $date, 'original_value' => '100.00',
                'payment_methods' => [['payment_type_id' => $this->method->id, 'financial_account_id' => $this->account->id, 'value' => '100.00']],
            ]);
        };
        $settle(3, '2026-10-10');
        $settle(1, '2026-10-12');
        $settle(2, '2026-10-11');

        $html = $this->detail($title)->html();
        $positions = array_map(fn (string $header) => strpos($html, e($header)), [
            $title->title_number.'/1 · 12/10/2026', $title->title_number.'/2 · 11/10/2026', $title->title_number.'/3 · 10/10/2026',
        ]);
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    public function test_added_payment_method_is_prefilled_with_missing_value(): void
    {
        $component = $this->payment($this->detail())
            ->set('payment.interest', '5.00')->set('payment.payment_methods.0.value', '30.00')
            ->call('addMethod')->assertSet('payment.payment_methods.1.value', '75,00')
            ->call('addMethod')->assertSet('payment.payment_methods.2.value', '');
        $component->call('removeMethod', 2)
            ->set('payment.payment_methods.1.payment_type_id', $this->method->id)
            ->set('payment.payment_methods.1.financial_account_id', $this->account->id)
            ->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('105.00', TitleSettlement::sole()->total_value);
    }

    public function test_title_identity_and_request_key_are_locked(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->detail()->set('titleId', $this->title->id + 1);
    }

    public function test_payment_uuid_is_locked(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->payment($this->detail())->set('paymentKey', (string) Str::uuid());
    }

    public function test_other_title_installment_cannot_be_used(): void
    {
        $other = $this->createTitle();
        $this->expectException(ModelNotFoundException::class);
        $this->detail()->call('openPayment', $other->installments->first()->id);
    }

    public function test_other_title_settlement_cannot_be_used_for_pdf(): void
    {
        $this->payment($this->detail())->call('requestSettle')->call('confirmOperation');
        $other = $this->createTitle();
        $this->expectException(ModelNotFoundException::class);
        $this->detail($other)->call('emitReceipt', TitleSettlement::sole()->id);
    }

    public function test_foreign_owner_cannot_mount_detail(): void
    {
        $this->actingAs(User::factory()->create());
        $this->expectException(ModelNotFoundException::class);
        $this->detail();
    }

    public function test_open_request_cannot_silently_rotate_uuid(): void
    {
        $component = $this->payment($this->detail());
        $key = $component->get('paymentKey');
        $component->call('openPayment', $this->title->installments->first()->id)->assertHasErrors('payment');
        $this->assertSame($key, $component->get('paymentKey'));
        $component->call('addMethod')->call('removeMethod', 1)->assertSet('payment.payment_methods', [[
            'payment_type_id' => $this->method->id, 'financial_account_id' => $this->account->id, 'value' => '100.00',
        ]]);
        $this->assertDatabaseCount('settlement_reversals', 0);
    }
}
