<?php

namespace Tests\Concerns;

use App\Models\Title;
use App\Models\TitleSettlement;
use App\Models\TitleSettlementMethod;
use App\Models\User;
use App\Services\TitleService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

trait TitleServiceScenarios
{
    private function service(): TitleService
    {
        return app(TitleService::class);
    }

    private function fixtures(): array
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $conta = $usuario->financialAccounts()->create(['name' => 'Conta', 'type' => 'checking']);
        $forma = $usuario->paymentTypes()->create(['name' => 'PIX', 'type' => 'both']);

        return [$usuario, $conta, $forma];
    }

    private function titleInput(array $alteracoes = []): array
    {
        return array_replace([
            'title_type' => 'receivable', 'description' => 'Teste Service', 'original_value' => '100.00',
            'issue_date' => '2026-01-01', 'due_date' => '2026-01-31',
        ], $alteracoes);
    }

    private function settlementInput($conta, $forma, string $valor = '100.00', array $alteracoes = []): array
    {
        return array_replace([
            'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-01-31',
            'original_value' => $valor,
            'payment_methods' => [['payment_type_id' => $forma->id,
                'financial_account_id' => $conta->id, 'value' => $valor]],
        ], $alteracoes);
    }

    public function test_service_distributes_cents_and_preserves_month_end_dates(): void
    {
        [, $conta] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput([
            'original_value' => '100.00', 'installments_count' => 3, 'financial_account_id' => $conta->id,
        ]));
        $this->assertSame(['33.34', '33.33', '33.33'], $titulo->installments->pluck('value')->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'],
            $titulo->installments->map(fn ($parcela) => $parcela->due_date->toDateString())->all());
        $this->assertSame([1, 2, 3], $titulo->installments->pluck('installment_number')->all());
        $this->assertSame([$conta->id, $conta->id, $conta->id], $titulo->installments->pluck('financial_account_id')->all());
    }

    public function test_numbering_is_scoped_by_owner_and_type_and_keeps_soft_deleted_numbers(): void
    {
        [$primeiro] = $this->fixtures();
        $apagado = $this->service()->create($this->titleInput());
        $apagado->delete();
        $this->assertSame('REC-0002', $this->service()->create($this->titleInput())->title_number);
        $this->assertSame('PAG-0001', $this->service()->create($this->titleInput(['title_type' => 'payable']))->title_number);
        $this->fixtures();
        $this->assertSame('REC-0001', $this->service()->create($this->titleInput())->title_number);
        $this->actingAs($primeiro);
        $this->assertSame('REC-0003', $this->service()->create($this->titleInput())->title_number);
    }

    public static function invalidTitleInputs(): array
    {
        return [
            'zero' => [['original_value' => '0']],
            'float' => [['original_value' => 100.10]],
            'three decimals' => [['original_value' => '100.001']],
            'too few cents' => [['original_value' => '0.01', 'installments_count' => 2]],
            'too many installments' => [['installments_count' => 121]],
            'invalid date' => [['due_date' => '2026-02-31']],
            'due before issue' => [['issue_date' => '2026-02-01']],
            'invalid type' => [['title_type' => 'invalid']],
            'foreign owner' => [['user_id' => 999]],
            'provided number' => [['title_number' => 'REC-9999']],
            'provided status' => [['status' => 'paid']],
            'provided parent' => [['parent_title_id' => 999]],
            'recurrence missing type' => [['is_recurring' => true, 'recurrence_day' => 31]],
            'recurrence invalid day' => [['is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 32]],
            'recurrence wrong date' => [['is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 10]],
            'yearly missing month' => [['is_recurring' => true, 'recurrence_type' => 'yearly', 'recurrence_day' => 31]],
            'recurring installments' => [['is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31, 'installments_count' => 2]],
            'non recurring with recurrence data' => [['recurrence_day' => 31]],
        ];
    }

    #[DataProvider('invalidTitleInputs')]
    public function test_invalid_creation_does_not_persist_titles(array $alteracoes): void
    {
        $this->fixtures();
        try {
            $this->service()->create($this->titleInput($alteracoes));
            $this->fail('Criacao deveria falhar.');
        } catch (ValidationException) {
            $this->assertSame(0, Title::count());
        }
    }

    public function test_creation_rejects_foreign_or_incompatible_links(): void
    {
        [$primeiro] = $this->fixtures();
        [, $contaAlheia] = $this->fixtures();
        $this->actingAs($primeiro);
        try {
            $this->service()->create($this->titleInput(['financial_account_id' => $contaAlheia->id]));
            $this->fail('Conta alheia deveria ser rejeitada.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, Title::count());
        }
        $categoria = $primeiro->categories()->create(['name' => 'Despesa', 'type' => 'expense']);
        $this->expectException(ValidationException::class);
        $this->service()->create($this->titleInput(['category_id' => $categoria->id]));
    }

    public function test_partial_and_total_settlements_preserve_exact_principal_and_adjustments(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $parcela = $titulo->installments->sole();
        $primeira = $this->service()->settle($titulo, $parcela, $this->settlementInput($conta, $forma, '40.00', [
            'discount' => '5.00', 'interest' => '2.00', 'fine' => '1.00', 'total_value' => '38.00',
            'payment_methods' => [
                ['payment_type_id' => $forma->id, 'financial_account_id' => $conta->id, 'value' => '30.00'],
                ['payment_type_id' => $forma->id, 'financial_account_id' => $conta->id, 'value' => '8.00'],
            ],
        ]));
        $this->assertSame('38.00', $primeira->total_value);
        $this->assertSame('40.00', $primeira->original_value);
        $this->assertSame(['30.00', '8.00'], $primeira->methods->pluck('value')->all());
        $this->assertSame('partial', $titulo->fresh()->status);
        $this->assertNotSame('paid', $parcela->fresh()->status);
        $segunda = $this->service()->settle($titulo, $parcela, $this->settlementInput($conta, $forma, '60.00'));
        $this->assertSame('60.00', $segunda->original_value);
        $this->assertSame('paid', $titulo->fresh()->status);
        $this->assertSame('paid', $parcela->fresh()->status);
        $this->assertSame(2, TitleSettlement::count());
    }

    public function test_idempotency_returns_the_original_partial_or_full_settlement(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $pedido = $this->settlementInput($conta, $forma, '40.00');
        $primeira = $this->service()->settle($titulo, $titulo->installments->sole(), $pedido);
        $repetida = $this->service()->settle($titulo, $titulo->installments->sole(), $pedido);
        $this->assertSame($primeira->id, $repetida->id);
        $this->assertSame(1, TitleSettlement::count());
        $this->assertSame(1, TitleSettlementMethod::count());
        $this->assertArrayNotHasKey('request_hash', $primeira->toArray());
        $total = $this->settlementInput($conta, $forma, '60.00');
        $ultima = $this->service()->settle($titulo, $titulo->installments->sole(), $total);
        $this->assertSame($ultima->id, $this->service()->settle($titulo, $titulo->installments->sole(), $total)->id);
    }

    public function test_idempotency_rejects_a_changed_payload(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $pedido = $this->settlementInput($conta, $forma, '40.00');
        $this->service()->settle($titulo, $titulo->installments->sole(), $pedido);
        $pedido['notes'] = 'Alterado';
        $this->expectException(ValidationException::class);
        $this->service()->settle($titulo, $titulo->installments->sole(), $pedido);
    }

    public static function invalidSettlementInputs(): array
    {
        return [
            'zero principal' => [['original_value' => '0']],
            'float principal' => [['original_value' => 100.0]],
            'negative discount' => [['discount' => '-1']],
            'discount above principal' => [['discount' => '101']],
            'wrong total' => [['total_value' => '99']],
            'wrong method sum' => [['original_value' => '99']],
            'invalid uuid' => [['idempotency_key' => 'invalid']],
            'invalid date' => [['settlement_date' => '2026-02-31']],
            'date before issue' => [['settlement_date' => '2025-12-31']],
            'empty methods' => [['payment_methods' => []]],
            'pdf unavailable' => [['generate_receipt' => true]],
        ];
    }

    #[DataProvider('invalidSettlementInputs')]
    public function test_invalid_settlement_does_not_change_financial_state(array $alteracoes): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        try {
            $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma, '100.00', $alteracoes));
            $this->fail('Baixa deveria falhar.');
        } catch (ValidationException) {
            $this->assertSame(0, TitleSettlement::count());
            $this->assertSame('pending', $titulo->fresh()->status);
            $this->assertSame('pending', $titulo->installments->sole()->fresh()->status);
        }
    }

    public function test_remaining_principal_cannot_be_exceeded_and_paid_installments_reject_new_keys(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $parcela = $titulo->installments->sole();
        $this->service()->settle($titulo, $parcela, $this->settlementInput($conta, $forma, '40.00'));
        try {
            $this->service()->settle($titulo, $parcela, $this->settlementInput($conta, $forma, '61.00'));
            $this->fail('Saldo insuficiente deveria impedir baixa.');
        } catch (ValidationException) {
            $this->assertSame(1, TitleSettlement::count());
        }
        $this->service()->settle($titulo, $parcela, $this->settlementInput($conta, $forma, '60.00'));
        $this->expectException(ValidationException::class);
        $this->service()->settle($titulo, $parcela, $this->settlementInput($conta, $forma, '60.00'));
    }

    public function test_settlement_rejects_another_titles_installment(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $primeiro = $this->service()->create($this->titleInput());
        $segundo = $this->service()->create($this->titleInput());
        $this->expectException(ModelNotFoundException::class);
        $this->service()->settle($primeiro, $segundo->installments->sole(), $this->settlementInput($conta, $forma));
    }

    public function test_settlement_reloads_owner_instead_of_trusting_modified_objects(): void
    {
        [$primeiro] = $this->fixtures();
        [, $conta, $forma] = $this->fixtures();
        $alheio = $this->service()->create($this->titleInput());
        $this->actingAs($primeiro);
        $alheio->user_id = $primeiro->id;
        $this->expectException(ModelNotFoundException::class);
        $this->service()->settle($alheio, $alheio->installments->sole(), $this->settlementInput($conta, $forma));
    }

    public function test_settlement_rejects_foreign_accounts_even_with_a_valid_method_sum(): void
    {
        [$primeiro, , $forma] = $this->fixtures();
        [, $contaAlheia] = $this->fixtures();
        $this->actingAs($primeiro);
        $titulo = $this->service()->create($this->titleInput());
        $this->expectException(ModelNotFoundException::class);
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($contaAlheia, $forma));
    }

    public function test_inactive_or_incompatible_payment_types_are_rejected(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $forma->update(['type' => 'payment']);
        $this->expectException(ValidationException::class);
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma));
    }

    public function test_failure_after_settlement_insert_rolls_back_methods_and_statuses(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $evento = 'eloquent.creating: '.TitleSettlementMethod::class;
        Event::listen($evento, fn () => throw new RuntimeException('Falha simulada.'));
        try {
            $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma));
            $this->fail('Gravacao deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame(0, TitleSettlement::count());
            $this->assertSame(0, TitleSettlementMethod::count());
            $this->assertSame('pending', $titulo->fresh()->status);
            $this->assertSame('pending', $titulo->installments->sole()->fresh()->status);
        } finally {
            Event::forget($evento);
        }
    }

    public function test_monthly_recurrence_keeps_root_and_creates_only_one_next_cycle(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $raiz = $this->service()->create($this->titleInput([
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));
        $pedido = $this->settlementInput($conta, $forma);
        $this->service()->settle($raiz, $raiz->installments->sole(), $pedido);
        $this->service()->settle($raiz, $raiz->installments->sole(), $pedido);
        $filho = Title::where('parent_title_id', $raiz->id)->sole();
        $this->assertSame('2026-02-28', $filho->due_date->toDateString());
        $this->assertSame(31, $filho->recurrence_day);
        $this->assertSame('REC-0002', $filho->title_number);
        $this->assertCount(1, $filho->installments);
        $this->service()->settle($filho, $filho->installments->sole(), $this->settlementInput($conta, $forma, '100.00', [
            'settlement_date' => '2026-03-01',
        ]));
        $neto = Title::where('title_number', 'REC-0003')->firstOrFail();
        $this->assertSame($raiz->id, $neto->parent_title_id);
        $this->assertSame('2026-03-31', $neto->due_date->toDateString());
    }

    public function test_partial_settlement_does_not_create_a_recurring_title(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $raiz = $this->service()->create($this->titleInput([
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));
        $this->service()->settle($raiz, $raiz->installments->sole(), $this->settlementInput($conta, $forma, '40.00'));
        $this->assertSame(1, Title::count());
    }

    public function test_cancellation_preserves_records_and_blocks_settlement(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $cancelado = $this->service()->cancel($titulo);
        $this->assertSame('cancelled', $cancelado->status);
        $this->assertSame('cancelled', $titulo->installments->sole()->fresh()->status);
        $this->assertSame(1, Title::count());
        $this->expectException(ValidationException::class);
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma));
    }

    public function test_titles_with_partial_settlements_cannot_be_cancelled_without_reversal(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma, '40.00'));
        $this->expectException(ValidationException::class);
        $this->service()->cancel($titulo);
    }

    public function test_installment_title_is_paid_only_after_every_installment_is_settled(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput(['installments_count' => 2]));
        $this->service()->settle($titulo, $titulo->installments[0], $this->settlementInput($conta, $forma, '50.00'));
        $this->assertSame('partial', $titulo->fresh()->status);
        $this->service()->settle($titulo, $titulo->installments[1], $this->settlementInput($conta, $forma, '50.00'));
        $this->assertSame('paid', $titulo->fresh()->status);
    }

    public function test_failure_while_creating_next_cycle_rolls_back_the_entire_payment(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput([
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));
        $evento = 'eloquent.creating: '.Title::class;
        Event::listen($evento, fn () => throw new RuntimeException('Falha na recorrência.'));
        try {
            $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma));
            $this->fail('Recorrência deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame(0, TitleSettlement::count());
            $this->assertSame(0, TitleSettlementMethod::count());
            $this->assertSame(1, Title::count());
            $this->assertSame('pending', $titulo->fresh()->status);
            $this->assertSame('pending', $titulo->installments->sole()->fresh()->status);
        } finally {
            Event::forget($evento);
        }
    }

    public function test_yearly_leap_day_recurrence_clamps_to_february(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput([
            'issue_date' => '2024-01-01', 'due_date' => '2024-02-29', 'is_recurring' => true,
            'recurrence_type' => 'yearly', 'recurrence_day' => 29, 'recurrence_month' => 2,
        ]));
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma, '100.00', [
            'settlement_date' => '2024-02-29',
        ]));
        $filho = Title::where('parent_title_id', $titulo->id)->sole();
        $this->assertSame('2025-02-28', $filho->due_date->toDateString());
        $this->assertSame(29, $filho->recurrence_day);
    }

    public function test_inactive_accounts_cannot_be_used_for_a_settlement(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        $conta->update(['active' => false]);
        $this->expectException(ValidationException::class);
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma));
    }

    public function test_cancel_reloads_and_rejects_another_owners_title(): void
    {
        [$primeiro] = $this->fixtures();
        $this->fixtures();
        $alheio = $this->service()->create($this->titleInput());
        $this->actingAs($primeiro);
        $alheio->user_id = $primeiro->id;
        $this->expectException(ModelNotFoundException::class);
        $this->service()->cancel($alheio);
    }

    public function test_guest_cannot_settle_a_previously_loaded_title(): void
    {
        [, $conta, $forma] = $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        Auth::logout();
        $this->expectException(AuthenticationException::class);
        $this->service()->settle($titulo, $titulo->installments->sole(), $this->settlementInput($conta, $forma));
    }

    public function test_guest_cannot_create_or_cancel_titles(): void
    {
        $this->fixtures();
        $titulo = $this->service()->create($this->titleInput());
        Auth::logout();
        try {
            $this->service()->create($this->titleInput());
            $this->fail('Criacao anonima deveria falhar.');
        } catch (AuthenticationException) {
            $this->expectException(AuthenticationException::class);
            $this->service()->cancel($titulo);
        }
    }
}
