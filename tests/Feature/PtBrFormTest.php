<?php

namespace Tests\Feature;

use App\Livewire\Catalog;
use App\Livewire\FinancialDashboard;
use App\Livewire\TitleDetail;
use App\Livewire\Titles;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\Title;
use App\Models\TitleSettlement;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PtBrFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
    }

    public static function amounts(): array
    {
        return [
            ['0,50', '0.50'], ['1,25', '1.25'], ['10,50', '10.50'],
            ['100,99', '100.99'], ['1.234,56', '1234.56'], ['-10,50', '-10.50'], ['0', '0.00'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_account_create_edit_and_reload_keep_exact_value(string $input, string $internal): void
    {
        $component = Livewire::test(Catalog::class, ['module' => 'contas'])
            ->assertSet('form.initial_balance', '0,00')
            ->set('form.name', 'Conta')->set('form.initial_balance', $input)->call('save')->assertHasNoErrors();
        $account = FinancialAccount::sole();
        $this->assertSame($internal, $account->initial_balance);
        $component->call('edit', $account->id)
            ->assertSet('form.initial_balance', str_replace('.', ',', $internal))
            ->set('form.name', 'Conta editada')->call('save')->assertHasNoErrors();
        $this->assertSame($internal, $account->fresh()->initial_balance);
    }

    public function test_title_edit_and_adjusted_payment_use_comma_and_preserve_idempotency(): void
    {
        $account = app(CatalogService::class)->create(FinancialAccount::class, ['name' => 'Conta', 'type' => 'cash']);
        $method = app(CatalogService::class)->create(PaymentType::class, ['name' => 'Pix', 'type' => 'both']);
        Livewire::test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Teste pt-BR')->set('form.original_value', '1.234,56')
            ->call('save')->assertHasNoErrors();
        $title = Title::sole();
        $this->assertSame('1234.56', $title->original_value);
        $component = Livewire::test(TitleDetail::class, ['titleId' => $title->id])
            ->call('openEdit')->assertSet('edit.original_value', '1234,56')
            ->set('edit.original_value', '100,99')->call('saveEdit')->assertHasNoErrors()
            ->call('openPayment', $title->installments()->first()->id)
            ->assertSet('payment.original_value', '100,99')
            ->assertSet('payment.discount', '0,00')
            ->set('payment.discount', '0,50')->set('payment.interest', '1,25')->set('payment.fine', '10,50')
            ->set('payment.payment_methods.0.value', '100,99')
            ->set('payment.payment_methods.0.payment_type_id', $method->id)
            ->set('payment.payment_methods.0.financial_account_id', $account->id)
            ->call('addMethod')->assertSet('payment.payment_methods.1.value', '11,25')
            ->set('payment.payment_methods.1.payment_type_id', $method->id)
            ->set('payment.payment_methods.1.financial_account_id', $account->id)
            ->assertSee('R$ 112,24')->assertSee('Valores conferem')
            ->call('requestSettle')->call('confirmOperation')->assertHasNoErrors();
        $this->assertSame('112.24', TitleSettlement::sole()->total_value);
        $this->assertSame('100.99', $title->fresh()->original_value);
        $this->assertSame('100.99', $component->get('completedPayment')['original_value']);
        $component->call('settle')->assertHasNoErrors();
        $this->assertDatabaseCount('title_settlements', 1);
    }

    public function test_invalid_decimals_leave_money_and_typed_input_unchanged(): void
    {
        $component = Livewire::test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Inválido');
        foreach (['-10,50', '1,234.56', '3,1415', '10000000000000,00', ''] as $invalid) {
            $component->set('form.original_value', $invalid)->call('save')->assertHasErrors('original_value')
                ->assertSet('form.original_value', $invalid);
            $this->assertDatabaseCount('titles', 0);
        }
    }

    public function test_native_date_component_preserves_calendar_bindings_limits_and_iso_values(): void
    {
        $html = view('components.date-input', [
            'model' => 'payment.settlement_date', 'value' => '2026-10-26',
            'live' => true, 'required' => true, 'max' => '2026-12-31',
            'attributes' => new ComponentAttributeBag,
        ])->render();
        foreach (['type="date"', 'lang="pt-BR"', 'wire:model.live="payment.settlement_date"',
            'value="2026-10-26"', 'max="2026-12-31"', 'required'] as $attribute) {
            $this->assertStringContainsString($attribute, $html);
        }
        foreach (['2026-01-01', '2026-10-26', '2026-12-31'] as $date) {
            Livewire::test(Titles::class, ['type' => 'receivable'])
                ->set('form.description', 'Data '.$date)->set('form.original_value', '10,50')
                ->set('form.issue_date', $date)->set('form.due_date', $date)
                ->call('save')->assertHasNoErrors();
            $title = Title::latest('id')->first();
            $this->assertSame($date, $title->due_date->toDateString());
            Livewire::test(TitleDetail::class, ['titleId' => $title->id])->call('openEdit')
                ->assertSet('edit.issue_date', $date)->assertSet('edit.due_date', $date)
                ->call('saveEdit')->assertHasNoErrors();
        }
        Livewire::test(FinancialDashboard::class)->set('filters.start', '')->call('applyFilters')
            ->assertHasErrors('start');
        Livewire::test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Data inválida')->set('form.original_value', '10,50')
            ->set('form.issue_date', '2026-02-30')->call('save')->assertHasErrors('issue_date');
    }
}
