<?php

namespace Tests\Concerns;

use App\Models\Notification;
use App\Models\Receipt;
use App\Models\Title;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReceiptService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mockery;
use RuntimeException;

trait ReceiptNotificationScenarios
{
    private string $artifactRoot;

    private array $artifactUsers = [];

    private function bootArtifacts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 10:00:00'));
        $this->artifactRoot = sys_get_temp_dir().'/financial-artifacts-'.Str::uuid();
        config(['filesystems.disks.receipts.root' => $this->artifactRoot]);
        Storage::forgetDisk('receipts');
    }

    private function cleanupArtifacts(): void
    {
        (new Filesystem)->deleteDirectory($this->artifactRoot);
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $this->assertSame('gestao_financeira_testing', DB::selectOne('SELECT DATABASE() AS nome')->nome);
        foreach ($this->artifactUsers as $usuario) {
            $this->assertTrue(DB::table('users')->where('id', $usuario->id)->where('email', $usuario->email)->exists());
            DB::transaction(function () use ($usuario) {
                $id = $usuario->id;
                $titulos = DB::table('titles')->where('user_id', $id)->pluck('id');
                $baixas = DB::table('title_settlements')->whereIn('title_id', $titulos)->pluck('id');
                DB::table('receipts')->where('user_id', $id)->delete();
                DB::table('settlement_reversals')->where('user_id', $id)->delete();
                DB::table('title_settlement_methods')->whereIn('settlement_id', $baixas)->delete();
                DB::table('titles')->where('user_id', $id)->update(['parent_title_id' => null, 'generated_by_settlement_id' => null]);
                DB::table('title_settlements')->whereIn('id', $baixas)->delete();
                DB::table('title_installments')->whereIn('title_id', $titulos)->delete();
                DB::table('titles')->where('user_id', $id)->delete();
                foreach (['notifications', 'contacts', 'categories', 'financial_accounts', 'payment_types'] as $tabela) {
                    DB::table($tabela)->where('user_id', $id)->delete();
                }
                DB::table('users')->where('id', $id)->where('email', $usuario->email)->delete();
            });
        }
    }

    private function artifactFixture(bool $pagar = true): array
    {
        $usuario = User::factory()->create(['email' => 'artifacts-'.Str::uuid().'@example.test']);
        $this->artifactUsers[] = $usuario;
        $this->actingAs($usuario);
        $titulo = $this->artifactTitle();
        $baixa = null;
        if ($pagar) {
            $conta = $usuario->financialAccounts()->create(['name' => 'Conta', 'type' => 'cash']);
            $forma = $usuario->paymentTypes()->create(['name' => 'PIX', 'type' => 'both']);
            $baixa = app(TitleService::class)->settle($titulo, $titulo->installments->first(), [
                'idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-16', 'original_value' => '100.01',
                'payment_methods' => [['financial_account_id' => $conta->id, 'payment_type_id' => $forma->id, 'value' => '100.01']],
            ]);
        }

        return [$usuario, $titulo, $baixa];
    }

    private function artifactTitle(array $dados = []): Title
    {
        return app(TitleService::class)->create(array_replace([
            'title_type' => 'receivable', 'description' => 'Titulo <script>alert(1)</script>',
            'original_value' => '100.01', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-23',
        ], $dados));
    }

    private function artifactValidation(callable $operacao, string $campo = 'receipt'): void
    {
        try {
            $operacao();
            $this->fail('Operacao deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    public function test_pdf_is_real_private_exact_and_idempotent(): void
    {
        [$usuario, , $baixa] = $this->artifactFixture();
        $antes = DB::table('document_sequences')->where('name', 'receipts')->value('last_number');
        $recibo = app(ReceiptService::class)->generate($baixa);
        $pdf = Storage::disk('receipts')->get($recibo->pdf_path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(hash('sha256', $pdf), $recibo->pdf_sha256);
        $this->assertSame('R$ 100,01', $recibo->snapshot['total_value']);
        $this->assertStringStartsWith('users/'.$usuario->id.'/', $recibo->pdf_path);
        $this->assertSame($recibo->id, app(ReceiptService::class)->generate($baixa)->id);
        $this->assertCount(1, Storage::disk('receipts')->allFiles());
        $this->assertSame((int) $antes + 1, (int) DB::table('document_sequences')->where('name', 'receipts')->value('last_number'));
        $this->assertFalse(config('filesystems.disks.receipts.serve'));
        $this->assertSame('private', config('filesystems.disks.receipts.visibility'));
    }

    public function test_receipt_snapshot_and_pdf_do_not_change_with_metadata(): void
    {
        [, $titulo, $baixa] = $this->artifactFixture();
        $recibo = app(ReceiptService::class)->generate($baixa);
        $html = view('receipt', ['snapshot' => $recibo->snapshot])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        app(TitleService::class)->update($titulo, ['description' => 'Alterado']);
        $repetido = app(ReceiptService::class)->generate($baixa);
        $this->assertSame($recibo->snapshot, $repetido->snapshot);
        $this->assertSame($recibo->pdf_sha256, $repetido->pdf_sha256);
    }

    public function test_receipt_http_routes_require_owner_and_reject_guests(): void
    {
        [, , $baixa] = $this->artifactFixture();
        $resposta = $this->postJson(route('receipts.store', $baixa))->assertOk();
        $id = $resposta->json('id');
        $this->get(route('receipts.download', $id))->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->artifactFixture(false);
        $this->getJson(route('receipts.download', $id))->assertNotFound();
        $this->postJson(route('receipts.store', $baixa))->assertNotFound();
        Auth::logout();
        $this->getJson(route('receipts.download', $id))->assertUnauthorized();
        $this->postJson(route('receipts.store', $baixa))->assertUnauthorized();
        $this->get(route('receipts.download', $id))->assertRedirect(route('login'));
    }

    public function test_estorno_blocks_emission_and_download_but_keeps_pdf(): void
    {
        [, , $baixa] = $this->artifactFixture();
        $recibo = app(ReceiptService::class)->generate($baixa);
        app(SettlementReversalService::class)->reverse($baixa, [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-16', 'reason' => 'Correcao',
        ]);
        $this->artifactValidation(fn () => app(ReceiptService::class)->generate($baixa));
        $this->getJson(route('receipts.download', $recibo))->assertUnprocessable();
        $this->assertTrue(Storage::disk('receipts')->exists($recibo->pdf_path));
        $this->assertNotNull($recibo->fresh()->voided_at);
    }

    public function test_corrupt_or_missing_pdf_is_never_silently_replaced(): void
    {
        [, , $baixa] = $this->artifactFixture();
        $recibo = app(ReceiptService::class)->generate($baixa);
        Storage::disk('receipts')->put($recibo->pdf_path, '%PDF-corrupt');
        $this->artifactValidation(fn () => app(ReceiptService::class)->generate($baixa));
        $this->getJson(route('receipts.download', $recibo))->assertUnprocessable();
        Storage::disk('receipts')->delete($recibo->pdf_path);
        $this->getJson(route('receipts.download', $recibo))->assertNotFound();
        $this->assertSame(1, Receipt::count());
    }

    public function test_receipt_failure_removes_file_and_rolls_back_number_without_touching_payment(): void
    {
        [, $titulo, $baixa] = $this->artifactFixture();
        $antes = DB::table('document_sequences')->where('name', 'receipts')->value('last_number');
        $evento = 'eloquent.creating: '.Receipt::class;
        Event::listen($evento, fn () => throw new RuntimeException('Falha de persistencia.'));
        try {
            app(ReceiptService::class)->generate($baixa);
            $this->fail('Emissao deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame(0, Receipt::count());
            $this->assertCount(0, Storage::disk('receipts')->allFiles());
            $this->assertSame($antes, DB::table('document_sequences')->where('name', 'receipts')->value('last_number'));
            $this->assertSame('paid', $titulo->fresh()->status);
        } finally {
            Event::forget($evento);
        }
    }

    public function test_receipt_rejects_outer_transactions(): void
    {
        [, , $baixa] = $this->artifactFixture();
        DB::beginTransaction();
        try {
            $this->expectException(LogicException::class);
            app(ReceiptService::class)->generate($baixa);
        } finally {
            DB::rollBack();
        }
    }

    public function test_receipt_rejects_unsafe_legacy_path(): void
    {
        [$usuario, , $baixa] = $this->artifactFixture();
        $recibo = new Receipt(['receipt_number' => 'LEGADO', 'issue_date' => '2026-10-16', 'pdf_path' => '../secret.pdf']);
        $recibo->user()->associate($usuario);
        $recibo->settlement()->associate($baixa);
        $recibo->save();
        $this->artifactValidation(fn () => app(ReceiptService::class)->generate($baixa));
        $this->getJson(route('receipts.download', $recibo))->assertUnprocessable();
    }

    public function test_notifications_include_unpaid_parcels_only_with_correct_owner(): void
    {
        [$usuario] = $this->artifactFixture(false);
        $this->artifactTitle(['due_date' => '2026-10-15']);
        $this->artifactTitle(['due_date' => '2026-10-24']);
        $cancelado = $this->artifactTitle();
        app(TitleService::class)->cancel($cancelado);
        $this->artifactTitle()->delete();
        $this->artifactFixture();
        $this->assertSame(2, app(NotificationService::class)->generateFor($usuario));
        $this->assertSame(0, Notification::count());
        $this->actingAs($usuario);
        $this->assertSame(['danger', 'warning'], Notification::pluck('type')->sort()->values()->all());
        $this->assertSame(0, app(NotificationService::class)->generateFor($usuario));
    }

    public function test_due_soon_and_overdue_are_distinct_non_daily_alerts(): void
    {
        [$usuario] = $this->artifactFixture(false);
        $this->artifactTitle(['due_date' => '2026-10-16']);
        $service = app(NotificationService::class);
        $this->assertSame(2, $service->generateFor($usuario));
        $this->travelTo(CarbonImmutable::parse('2026-10-17'));
        $this->assertSame(1, $service->generateFor($usuario));
        $this->travelTo(CarbonImmutable::parse('2026-10-18'));
        $this->assertSame(0, $service->generateFor($usuario));
        $this->assertSame(3, Notification::count());
    }

    public function test_rescheduling_creates_a_new_alert_without_overwriting_history(): void
    {
        [$usuario, $titulo] = $this->artifactFixture(false);
        $this->assertSame(1, app(NotificationService::class)->generateFor($usuario));
        app(TitleService::class)->update($titulo, ['due_date' => '2026-10-22']);
        $this->assertSame(1, app(NotificationService::class)->generateFor($usuario));
        $this->assertSame(2, Notification::count());
    }

    public function test_read_actions_are_owner_scoped_and_keep_first_read_timestamp(): void
    {
        [$usuario] = $this->artifactFixture(false);
        app(NotificationService::class)->generateFor($usuario);
        $notificacao = Notification::firstOrFail();
        $this->patchJson(route('notifications.read', $notificacao))->assertOk();
        $primeiro = $notificacao->fresh()->read_at->toDateTimeString();
        $this->travelTo(now()->addHour());
        $this->assertSame($primeiro, app(NotificationService::class)->markRead($notificacao)->read_at->toDateTimeString());
        $this->artifactFixture(false);
        $this->patchJson(route('notifications.read', $notificacao))->assertNotFound();
        $this->assertSame(0, app(NotificationService::class)->markAllRead());
        Auth::logout();
        $this->patchJson(route('notifications.read', $notificacao))->assertUnauthorized();
        $this->actingAs($usuario);
        $this->assertSame(0, app(NotificationService::class)->markAllRead());
    }

    public function test_notification_failure_is_atomic_and_command_reexecution_is_safe(): void
    {
        [$usuario] = $this->artifactFixture(false);
        $this->artifactTitle();
        $evento = 'eloquent.creating: '.Notification::class;
        $criadas = 0;
        Event::listen($evento, function () use (&$criadas) {
            if (++$criadas === 2) {
                throw new RuntimeException('Falha na segunda notificacao.');
            }
        });
        try {
            app(NotificationService::class)->generateFor($usuario);
            $this->fail('Rotina deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSame(0, Notification::count());
        } finally {
            Event::forget($evento);
        }
        Auth::logout();
        $this->artisan('notifications:send-due-soon', ['--user' => $usuario->id])->assertSuccessful();
        $this->artisan('notifications:send-due-soon', ['--user' => $usuario->id])->assertSuccessful();
        $this->assertSame(2, DB::table('notifications')->where('user_id', $usuario->id)->count());
        $this->assertNull(Auth::id());
        $this->artisan('notifications:send-due-soon', ['--date' => '2026-10-17'])->assertFailed();
        $this->artisan('notifications:send-due-soon', ['--user' => 999999999])->assertFailed();
    }

    public function test_failed_disk_write_does_not_consume_number_or_create_receipt(): void
    {
        [, $titulo, $baixa] = $this->artifactFixture();
        $numero = DB::table('document_sequences')->where('name', 'receipts')->value('last_number');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        Storage::shouldReceive('disk')->with('receipts')->andReturn($disk);
        $this->artifactValidation(fn () => app(ReceiptService::class)->generate($baixa));
        $this->assertSame(0, Receipt::count());
        $this->assertSame($numero, DB::table('document_sequences')->where('name', 'receipts')->value('last_number'));
        $this->assertSame('paid', $titulo->fresh()->status);
    }

    public function test_future_settlement_does_not_allow_receipt_issuance(): void
    {
        [, , $baixa] = $this->artifactFixture();
        $baixa->settlement_date = '2026-10-17';
        $baixa->save();
        $this->artifactValidation(fn () => app(ReceiptService::class)->generate($baixa));
        $this->assertSame(0, Receipt::count());
    }

    public function test_voided_receipt_without_reversal_is_also_blocked(): void
    {
        [, , $baixa] = $this->artifactFixture();
        $recibo = app(ReceiptService::class)->generate($baixa);
        $recibo->voided_at = now();
        $recibo->save();
        $this->artifactValidation(fn () => app(ReceiptService::class)->generate($baixa));
        $this->getJson(route('receipts.download', $recibo))->assertUnprocessable();
    }

    public function test_zero_day_window_and_invalid_preferences_are_explicit(): void
    {
        [$usuario] = $this->artifactFixture(false);
        $usuario->update(['notification_days_before' => 0]);
        $this->artifactTitle(['due_date' => '2026-10-16']);
        $this->assertSame(1, app(NotificationService::class)->generateFor($usuario));
        $usuario->update(['notification_days_before' => 366]);
        $this->artifactValidation(fn () => app(NotificationService::class)->generateFor($usuario), 'days');
        $this->assertSame(1, Notification::count());
    }

    public function test_mark_all_read_does_not_change_other_users_notifications(): void
    {
        [$primeiro] = $this->artifactFixture(false);
        app(NotificationService::class)->generateFor($primeiro);
        [$segundo] = $this->artifactFixture(false);
        app(NotificationService::class)->generateFor($segundo);
        $this->assertSame(1, app(NotificationService::class)->markAllRead());
        $this->assertSame(0, app(NotificationService::class)->markAllRead());
        $this->actingAs($primeiro);
        $this->assertSame(1, Notification::unread()->count());
    }
}
