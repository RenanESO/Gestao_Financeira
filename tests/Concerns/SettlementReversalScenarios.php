<?php

namespace Tests\Concerns;

use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Receipt;
use App\Models\SettlementReversal;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\TitleSettlementMethod;
use App\Models\User;
use App\Services\FinancialAccountService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

trait SettlementReversalScenarios
{
    private function reversalFixtures(bool $recorrente = false, string $tipo = 'receivable'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 10:00:00'));
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $conta = $usuario->financialAccounts()->create(['name' => 'Conta', 'type' => 'cash', 'initial_balance' => '0.00']);
        $forma = $usuario->paymentTypes()->create(['name' => 'PIX', 'type' => 'both']);
        $titulo = app(TitleService::class)->create([
            'title_type' => $tipo, 'description' => 'Estorno', 'original_value' => '100.00',
            'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'is_recurring' => $recorrente, 'recurrence_type' => $recorrente ? 'monthly' : null,
            'recurrence_day' => $recorrente ? 31 : null,
        ]);

        return [$usuario, $conta, $forma, $titulo];
    }

    private function reversalPayment(Title $titulo, FinancialAccount $conta, PaymentType $forma, string $principal = '100.00', array $dados = []): TitleSettlement
    {
        return app(TitleService::class)->settle($titulo, $titulo->installments->first(), array_replace([
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-15',
            'original_value' => $principal,
            'payment_methods' => [['payment_type_id' => $forma->id, 'financial_account_id' => $conta->id, 'value' => $principal]],
        ], $dados));
    }

    private function reversalInput(array $dados = []): array
    {
        return array_replace([
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-16',
            'reason' => 'Correcao de pagamento',
        ], $dados);
    }

    private function assertReversalValidation(callable $operacao, string $campo): void
    {
        try {
            $operacao();
            $this->fail('Estorno deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    public function test_reversal_preserves_original_and_restores_principal_and_historical_balance(): void
    {
        [$usuario, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma, '40.00');
        $estorno = app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertSame($usuario->id, $estorno->user_id);
        $this->assertSame($baixa->id, $estorno->settlement_id);
        $this->assertSame('40.00', $baixa->fresh()->original_value);
        $this->assertSame(1, TitleSettlementMethod::count());
        $this->assertSame(1, TitleSettlement::count());
        $this->assertSame(0, TitleSettlement::active()->count());
        $this->assertSame('pending', $titulo->fresh()->status);
        $this->assertSame('pending', $titulo->installments->first()->fresh()->status);
        $this->assertSame('40.00', app(FinancialAccountService::class)->balance($conta, '2026-10-15')['balance']);
        $this->assertSame('0.00', app(FinancialAccountService::class)->balance($conta, '2026-10-16')['balance']);
        $nova = $this->reversalPayment($titulo, $conta, $forma, '100.00', ['settlement_date' => '2026-10-16']);
        $this->assertNotSame($baixa->id, $nova->id);
        $this->assertSame('paid', $titulo->fresh()->status);
        $this->assertSame('100.00', app(FinancialAccountService::class)->balance($conta)['balance']);
    }

    public function test_reversal_of_payable_restores_cash_without_deleting_history(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures(false, 'payable');
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $this->assertSame('-100.00', app(FinancialAccountService::class)->balance($conta)['balance']);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertSame('0.00', app(FinancialAccountService::class)->balance($conta)['balance']);
    }

    public function test_reversing_one_of_multiple_payments_preserves_partial_status(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $primeira = $this->reversalPayment($titulo, $conta, $forma, '40.00');
        $this->reversalPayment($titulo, $conta, $forma, '60.00');
        app(SettlementReversalService::class)->reverse($primeira, $this->reversalInput());
        $this->assertSame('partial', $titulo->fresh()->status);
        $this->assertSame('pending', $titulo->installments->first()->fresh()->status);
        $this->assertSame('60.00', app(FinancialAccountService::class)->balance($conta)['balance']);
    }

    public function test_reversing_an_overdue_title_reopens_it_as_overdue(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        app(TitleService::class)->update($titulo, ['due_date' => '2026-10-10']);
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertSame('overdue', $titulo->fresh()->status);
        $this->assertSame('overdue', $titulo->installments->first()->fresh()->status);
    }

    public function test_reversal_is_idempotent_even_after_a_replacement_payment(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $pedido = $this->reversalInput();
        $primeiro = app(SettlementReversalService::class)->reverse($baixa, $pedido);
        $this->reversalPayment($titulo, $conta, $forma, '100.00', ['settlement_date' => '2026-10-16']);
        $repetido = app(SettlementReversalService::class)->reverse($baixa, $pedido);
        $this->assertSame($primeiro->id, $repetido->id);
        $this->assertSame(1, SettlementReversal::count());
        $this->assertSame('paid', $titulo->fresh()->status);
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput()), 'settlement_id');
        $pedido['reason'] = 'Pedido alterado';
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($baixa, $pedido), 'idempotency_key');
    }

    public function test_reversal_key_cannot_be_reused_for_a_different_settlement(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $primeira = $this->reversalPayment($titulo, $conta, $forma, '40.00');
        $segunda = $this->reversalPayment($titulo, $conta, $forma, '60.00');
        $pedido = $this->reversalInput();
        app(SettlementReversalService::class)->reverse($primeira, $pedido);
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($segunda, $pedido), 'idempotency_key');
    }

    public static function invalidReversalInputs(): array
    {
        return [
            [['idempotency_key' => 'invalid'], 'idempotency_key'],
            [['reason' => '  '], 'reason'],
            [['reason' => 'ab'], 'reason'],
            [['reversal_date' => '2026-02-31'], 'reversal_date'],
            [['reversal_date' => '2026-10-17'], 'reversal_date'],
            [['reversal_date' => '2026-10-14'], 'reversal_date'],
            [['user_id' => 999], 'user_id'],
            [['settlement_id' => 999], 'settlement_id'],
        ];
    }

    #[DataProvider('invalidReversalInputs')]
    public function test_invalid_reversals_do_not_mutate_financial_state(array $dados, string $campo): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput($dados)), $campo);
        $this->assertSame(0, SettlementReversal::count());
        $this->assertSame('paid', $titulo->fresh()->status);
    }

    public function test_reversal_invalidates_receipt_without_removing_record_or_path(): void
    {
        [$usuario, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $recibo = new Receipt(['receipt_number' => 'RCB-'.$usuario->id, 'issue_date' => '2026-10-15', 'pdf_path' => 'private/old.pdf']);
        $recibo->user()->associate($usuario);
        $baixa->receipt()->save($recibo);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertNotNull($recibo->fresh()->voided_at);
        $this->assertSame('private/old.pdf', $recibo->fresh()->pdf_path);
        $this->assertSame(1, Receipt::count());
    }

    public function test_recurring_reversal_cancels_unmoved_next_cycle_and_repayment_creates_new_one(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures(true);
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $filho = $baixa->fresh()->generatedTitle;
        $this->assertNotNull($filho);
        $this->assertSame($baixa->id, $filho->generated_by_settlement_id);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertSame('cancelled', $filho->fresh()->status);
        $this->assertSame('cancelled', $filho->installments->first()->fresh()->status);
        $this->assertFalse($filho->fresh()->trashed());
        $nova = $this->reversalPayment($titulo, $conta, $forma, '100.00', ['settlement_date' => '2026-10-16']);
        $this->assertSame('REC-0003', $nova->fresh()->generatedTitle->title_number);
        $this->assertSame($titulo->id, $nova->fresh()->generatedTitle->parent_title_id);
        $this->assertSame('2026-11-30', $nova->fresh()->generatedTitle->due_date->toDateString());
    }

    public function test_reversal_of_an_earlier_partial_payment_also_cancels_the_closing_cycle(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures(true);
        $primeira = $this->reversalPayment($titulo, $conta, $forma, '40.00');
        $ultima = $this->reversalPayment($titulo, $conta, $forma, '60.00');
        app(SettlementReversalService::class)->reverse($primeira, $this->reversalInput());
        $this->assertSame('cancelled', $ultima->fresh()->generatedTitle->status);
        $this->assertSame('partial', $titulo->fresh()->status);
    }

    public function test_reversal_rejects_next_cycle_with_any_payment_history(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures(true);
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $filho = $baixa->fresh()->generatedTitle;
        $this->reversalPayment($filho, $conta, $forma, '40.00', ['settlement_date' => '2026-10-16']);
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput()), 'recurrence');
        $this->assertSame('paid', $titulo->fresh()->status);
        $this->assertSame('partial', $filho->fresh()->status);
        $this->assertSame(0, SettlementReversal::count());
    }

    public function test_legacy_recurring_origin_requires_reconciliation_instead_of_guessing(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures(true);
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $filho = $baixa->fresh()->generatedTitle;
        $filho->generated_by_settlement_id = null;
        $filho->save();
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput()), 'recurrence');
        $this->assertSame('pending', $filho->fresh()->status);
    }

    public function test_reversal_of_soft_deleted_title_preserves_deletion_and_history(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $titulo->delete();
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertTrue(Title::withTrashed()->findOrFail($titulo->id)->trashed());
        $this->assertSame('0.00', app(FinancialAccountService::class)->balance($conta)['balance']);
    }

    public function test_reversal_does_not_unlock_financial_edits_but_allows_cancellation_after_full_reversal(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertReversalValidation(fn () => app(TitleService::class)->update($titulo, ['original_value' => '200.00']), 'status');
        $this->assertSame('cancelled', app(TitleService::class)->cancel($titulo)->status);
        $this->assertSame(1, SettlementReversal::count());
    }

    public function test_replacement_payment_cannot_be_backdated_before_reversal(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertReversalValidation(fn () => $this->reversalPayment($titulo, $conta, $forma), 'settlement_date');
    }

    public function test_reversal_rolls_back_cancelled_cycle_and_receipt_when_installment_write_fails(): void
    {
        [$usuario, $conta, $forma, $titulo] = $this->reversalFixtures(true);
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $filho = $baixa->fresh()->generatedTitle;
        $recibo = new Receipt(['receipt_number' => 'RCB-'.$usuario->id, 'issue_date' => '2026-10-15', 'pdf_path' => 'private/old.pdf']);
        $recibo->user()->associate($usuario);
        $baixa->receipt()->save($recibo);
        $evento = 'eloquent.updating: '.TitleInstallment::class;
        Event::listen($evento, fn () => throw new RuntimeException('Falha simulada.'));
        try {
            app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
            $this->fail('Escrita deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame(0, SettlementReversal::count());
            $this->assertSame('paid', $titulo->fresh()->status);
            $this->assertSame('pending', $filho->fresh()->status);
            $this->assertNull($recibo->fresh()->voided_at);
        } finally {
            Event::forget($evento);
        }
    }

    public function test_reversal_is_private_and_not_generic_crud(): void
    {
        [$usuario, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $estorno = app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertTrue(Gate::allows('view', $estorno));
        $this->assertFalse(Gate::allows('update', $estorno));
        $this->assertFalse(Gate::allows('delete', $estorno));
        $this->assertArrayNotHasKey('request_hash', $estorno->toArray());
        $this->actingAs(User::factory()->create());
        $this->assertNull(SettlementReversal::find($estorno->id));
        $estorno->user_id = Auth::id();
        $this->assertFalse(Gate::allows('view', $estorno));
        $baixa->title_id = 999;
        $this->expectException(ModelNotFoundException::class);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
    }

    public function test_guest_cannot_reverse_a_preloaded_settlement(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        Auth::logout();
        $this->expectException(AuthenticationException::class);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
    }

    public function test_inconsistent_legacy_values_are_rejected_without_canceling_the_next_cycle(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures(true);
        $baixa = $this->reversalPayment($titulo, $conta, $forma);
        $baixa->methods->first()->update(['value' => '99.00']);
        $this->assertReversalValidation(fn () => app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput()), 'settlement');
        $this->assertSame('pending', $baixa->fresh()->generatedTitle->status);
        $this->assertSame(0, SettlementReversal::count());
    }

    public function test_reversal_of_adjusted_payment_removes_actual_total_not_just_principal(): void
    {
        [, $conta, $forma, $titulo] = $this->reversalFixtures();
        $baixa = $this->reversalPayment($titulo, $conta, $forma, '100.00', [
            'discount' => '10.00', 'interest' => '2.00', 'fine' => '1.00',
            'payment_methods' => [['payment_type_id' => $forma->id, 'financial_account_id' => $conta->id, 'value' => '93.00']],
        ]);
        app(SettlementReversalService::class)->reverse($baixa, $this->reversalInput());
        $this->assertSame('93.00', app(FinancialAccountService::class)->balance($conta, '2026-10-15')['balance']);
        $this->assertSame('0.00', app(FinancialAccountService::class)->balance($conta)['balance']);
        $this->assertSame('100.00', $titulo->fresh()->original_value);
    }

    public function test_reversal_preserves_other_paid_installments(): void
    {
        [, $conta, $forma] = $this->reversalFixtures();
        $titulo = app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Parcelamento', 'original_value' => '100.00',
            'installments_count' => 2, 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        ]);
        $primeira = $this->reversalPayment($titulo, $conta, $forma, '50.00');
        app(TitleService::class)->settle($titulo, $titulo->installments[1], [
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-15',
            'original_value' => '50.00',
            'payment_methods' => [['payment_type_id' => $forma->id, 'financial_account_id' => $conta->id, 'value' => '50.00']],
        ]);
        app(SettlementReversalService::class)->reverse($primeira, $this->reversalInput());
        $this->assertSame('partial', $titulo->fresh()->status);
        $this->assertSame(['pending', 'paid'], $titulo->fresh()->installments->pluck('status')->all());
    }
}
