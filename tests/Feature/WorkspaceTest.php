<?php

namespace Tests\Feature;

use App\Livewire\Catalog;
use App\Livewire\Titles;
use App\Livewire\UserPreferences;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\Notification;
use App\Models\PaymentType;
use App\Models\Title;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\FinancialDefaultsService;
use App\Services\TitleService;
use App\Support\Display;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_pages_and_protected_routes(): void
    {
        foreach (['home', 'login', 'register', 'password.request'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['dashboard', 'income', 'expenses', 'receipts.index', 'notifications.index', 'preferences'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
            $this->getJson(route($route))->assertUnauthorized();
        }
    }

    public function test_registration_initializes_only_defaults_and_ignores_owner_input(): void
    {
        $this->post(route('register.store'), [
            'name' => ' Ana ', 'email' => 'ANA@example.test',
            'password' => 'Secure1234', 'password_confirmation' => 'Secure1234', 'user_id' => 999,
        ])->assertRedirect(route('dashboard'));
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('ana@example.test', $user->email);
        $this->assertSame('Ana', $user->name);
        $this->assertTrue(Hash::check('Secure1234', $user->password));
        $this->assertSame(12, $user->categories()->count());
        $this->assertSame(7, $user->paymentTypes()->count());
        $this->assertDatabaseCount('financial_accounts', 0);
        $this->assertDatabaseCount('titles', 0);
    }

    public function test_registration_rejects_duplicate_normalized_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);
        $this->post(route('register.store'), [
            'name' => 'Ana', 'email' => 'ANA@example.test', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_onboarding_failure_rolls_back_user_and_defaults(): void
    {
        $this->mock(FinancialDefaultsService::class, function ($mock) {
            $mock->shouldReceive('initialize')->once()->andThrow(new \RuntimeException('defaults failure'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('register.store'), [
                'name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'Secure1234', 'password_confirmation' => 'Secure1234',
            ]);
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('defaults failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertGuest();
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Secure1234']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Secure1234'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('login'))->assertRedirect(route('dashboard'));
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_throttles_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas.', session('errors')->first('email'));
    }

    public function test_password_reset_sends_notification_and_accepts_valid_token(): void
    {
        NotificationFacade::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        NotificationFacade::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'Changed123', 'password_confirmation' => 'Changed123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Changed123', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_password_reset_rejects_invalid_token(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.update'), [
            'token' => 'invalid', 'email' => $user->email,
            'password' => 'Changed123', 'password_confirmation' => 'Changed123',
        ])->assertSessionHasErrors('email');
    }

    public function test_missing_email_gets_same_reset_confirmation(): void
    {
        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertSessionHas('status', 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha.');
    }

    public function test_all_workspace_pages_render_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['dashboard', 'income', 'expenses', 'receipts.index', 'notifications.index', 'preferences'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('MoneyControl');
        }
        foreach (array_keys(Catalog::MODULES) as $module) {
            $this->get(route('catalog', $module))->assertOk();
        }
        $this->get('/cadastros/invalid')->assertNotFound();
    }

    public function test_catalog_creates_edits_and_deactivates_using_service(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Catalog::class, ['module' => 'contas'])
            ->set('form.name', 'Conta real')->set('form.initial_balance', '-10.50')->call('save')->assertHasNoErrors();
        $account = FinancialAccount::sole();
        $this->assertSame($user->id, $account->user_id);
        $this->assertSame('-10.50', $account->initial_balance);
        $component->call('edit', $account->id)->set('form.name', 'Conta editada')->call('save')->assertHasNoErrors();
        $this->assertSame('Conta editada', $account->fresh()->name);
        $component->call('toggle', $account->id);
        $this->assertFalse($account->fresh()->active);
    }

    public function test_catalog_rejects_invalid_money_and_foreign_record(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $account = app(CatalogService::class)->create(FinancialAccount::class, ['name' => 'Foreign account', 'type' => 'cash']);
        $other = User::factory()->create();
        Livewire::actingAs($other)->test(Catalog::class, ['module' => 'contas'])
            ->set('form.name', 'Bad')->set('form.initial_balance', '1,50')->call('save')->assertHasErrors('initial_balance');
        $this->assertMissingRecord(fn () => Livewire::actingAs($other)->test(Catalog::class, ['module' => 'contas'])->call('edit', $account->id));
        $this->assertMissingRecord(fn () => Livewire::actingAs($other)->test(Catalog::class, ['module' => 'contas'])->call('toggle', $account->id));
    }

    public function test_contacts_support_full_details_archive_and_restore(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Catalog::class, ['module' => 'contatos'])
            ->set('form.name', '  Ana Cliente  ')
            ->set('form.type', 'client')
            ->set('form.document', '123.456.789-00')
            ->set('form.email', 'ana@example.test')
            ->set('form.phone', '(11) 99999-0000')
            ->set('form.address', 'Rua das Flores, 10')
            ->set('form.neighborhood', 'Centro')
            ->set('form.city', 'Sao Paulo')
            ->set('form.state', 'sp')
            ->set('form.zip_code', '01000-000')
            ->set('form.notes', 'Contato preferencial')
            ->call('save')
            ->assertHasNoErrors();

        $contact = Contact::sole();
        $this->assertSame('CLI-0001', $contact->code);
        $this->assertSame('SP', $contact->state);
        $this->assertSame('Contato preferencial', $contact->notes);

        $component->call('archive', $contact->id);
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);

        $component->set('showArchived', true)->call('restore', $contact->id);
        $this->assertNull($contact->fresh()->deleted_at);
    }

    public function test_user_preferences_are_validated_persisted_and_applied_to_layout(): void
    {
        $user = User::factory()->create(['theme' => 'dark', 'notification_days_before' => 7]);

        Livewire::actingAs($user)->test(UserPreferences::class)
            ->set('theme', 'light')
            ->set('notificationDaysBefore', 14)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('light', $user->fresh()->theme);
        $this->assertSame(14, $user->fresh()->notification_days_before);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertSee('data-theme="light"', false);

        Livewire::actingAs($user)->test(UserPreferences::class)
            ->set('notificationDaysBefore', 366)
            ->call('save')
            ->assertHasErrors('notification_days_before');
    }

    public function test_dashboard_uses_remaining_principal_and_monthly_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = app(CatalogService::class)->create(FinancialAccount::class, ['name' => 'Conta', 'type' => 'cash']);
        $method = app(CatalogService::class)->create(PaymentType::class, ['name' => 'Pix', 'type' => 'both']);
        $title = app(TitleService::class)->create([
            'title_type' => 'receivable',
            'description' => 'Contrato parcial',
            'original_value' => '100.00',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);
        app(TitleService::class)->settle($title, $title->installments->sole(), [
            'idempotency_key' => (string) Str::uuid(),
            'settlement_date' => now()->toDateString(),
            'original_value' => '40.00',
            'discount' => '0.00',
            'interest' => '0.00',
            'fine' => '0.00',
            'payment_methods' => [[
                'payment_type_id' => $method->id,
                'financial_account_id' => $account->id,
                'value' => '40.00',
            ]],
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Saldo restante das parcelas')
            ->assertSee('R$ 60,00')
            ->assertSee('Recebido no periodo')
            ->assertSee('R$ 40,00');
    }

    public function test_titles_creation_preserves_exact_installments_and_owner(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Contrato real')->set('form.original_value', '10.00')
            ->set('form.installments_count', 3)->call('save')->assertHasNoErrors();
        $title = Title::sole();
        $this->assertSame($user->id, $title->user_id);
        $this->assertSame('receivable', $title->title_type);
        $this->assertSame(['3.34', '3.33', '3.33'], $title->installments->pluck('value')->all());
        $this->get(route('titles.show', $title))->assertOk()->assertSee('Contrato real');
    }

    public function test_titles_reject_foreign_category_and_invalid_dates(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $category = app(CatalogService::class)->create(Category::class, ['name' => 'Foreign category', 'type' => 'income']);
        $other = User::factory()->create();
        $this->assertMissingRecord(fn () => Livewire::actingAs($other)->test(Titles::class, ['type' => 'receivable'])
            ->set('form.description', 'Invalid')->set('form.original_value', '10.00')
            ->set('form.category_id', $category->id)->call('save'));
        Livewire::actingAs($other)->test(Titles::class, ['type' => 'payable'])
            ->set('form.description', 'Invalid')->set('form.original_value', '10.00')
            ->set('form.issue_date', '2026-10-16')->set('form.due_date', '2026-10-15')->call('save')->assertHasErrors('due_date');
        $this->assertDatabaseCount('titles', 0);
    }

    public function test_dashboard_and_lists_do_not_expose_another_owner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $title = app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Private title', 'original_value' => '40.00',
            'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
        ]);
        $owner->financialNotifications()->create(['title' => 'Private alert', 'message' => 'Private message', 'type' => 'info']);
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Private title')->assertDontSee('Private alert');
        $this->get(route('income'))->assertOk()->assertDontSee('Private title');
        $this->get(route('notifications.index'))->assertOk()->assertDontSee('Private alert');
        $this->get(route('titles.show', $title->id))->assertNotFound();
    }

    public function test_notification_read_routes_preserve_other_owner(): void
    {
        $owner = User::factory()->create();
        $foreign = $owner->financialNotifications()->create(['title' => 'Private', 'message' => 'Private', 'type' => 'info']);
        $other = User::factory()->create();
        $own = $other->financialNotifications()->create(['title' => 'Own', 'message' => 'Own', 'type' => 'info']);
        $this->actingAs($other);
        $this->post(route('notifications.read-web', $foreign->id))->assertNotFound();
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertSame(0, Notification::unread()->count());
    }

    public function test_notification_refresh_creates_due_and_overdue_alerts_without_duplicates(): void
    {
        $user = User::factory()->create(['notification_days_before' => 7]);
        $this->actingAs($user);
        app(TitleService::class)->create([
            'title_type' => 'receivable', 'description' => 'Vence em breve', 'original_value' => '10.00',
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(3)->toDateString(),
        ]);
        app(TitleService::class)->create([
            'title_type' => 'payable', 'description' => 'Em atraso', 'original_value' => '20.00',
            'issue_date' => now()->subDays(2)->toDateString(), 'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->post(route('notifications.refresh'))
            ->assertRedirect()
            ->assertSessionHas('status', '2 alerta(s) atualizado(s).');
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'title' => 'Parcela vencida']);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'title' => 'Parcela vencendo em breve']);

        $this->post(route('notifications.refresh'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Nenhum novo alerta encontrado para a antecedencia configurada.');
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_display_formats_signed_money_without_float(): void
    {
        $this->assertSame('R$ 1.234,56', Display::money('1234.56'));
        $this->assertSame('-R$ 0,01', Display::money('-0.01'));
        $this->assertSame('R$ 9.999.999.999.999,99', Display::money('9999999999999.99'));
    }

    public function test_catalog_module_cannot_be_changed_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs(User::factory()->create())->test(Catalog::class, ['module' => 'contas'])->set('module', 'contatos');
    }

    public function test_title_type_cannot_be_changed_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs(User::factory()->create())->test(Titles::class, ['type' => 'receivable'])->set('type', 'payable');
    }

    private function assertMissingRecord(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected owner-scoped record to be unavailable');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        } finally {
            $this->withExceptionHandling();
        }
    }
}
