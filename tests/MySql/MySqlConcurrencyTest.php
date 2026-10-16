<?php

namespace Tests\MySql;

use App\Models\Title;
use App\Models\User;
use App\Services\TitleService;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlConcurrencyTest extends TestCase
{
    private ?User $usuario = null;

    /** @var list<Process> */
    private array $processos = [];

    private int $conta;

    private int $forma;

    private ?string $receiptRoot = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('gestao_financeira_testing', DB::selectOne('SELECT DATABASE() AS nome')->nome);
        $this->assertSame(0, DB::transactionLevel());
        $this->travelTo(CarbonImmutable::parse('2026-10-16 10:00:00'));
        $this->usuario = User::factory()->create(['email' => 'concurrency-'.Str::uuid().'@example.test']);
        $this->actingAs($this->usuario);
        $this->conta = $this->usuario->financialAccounts()->create(['name' => 'Fixture', 'type' => 'cash'])->id;
        $this->forma = $this->usuario->paymentTypes()->create(['name' => 'Fixture', 'type' => 'both'])->id;
    }

    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($this->processos as $processo) {
                if ($processo->isRunning()) {
                    $processo->stop(1);
                }
            }
            if ($this->receiptRoot !== null) {
                (new Filesystem)->deleteDirectory($this->receiptRoot);
            }
            $this->cleanupOwnedFixture();
        } finally {
            parent::tearDown();
        }
    }

    private function titleInput(array $dados = []): array
    {
        return array_replace(['title_type' => 'receivable', 'description' => 'Concorrencia',
            'original_value' => '100.00', 'issue_date' => '2026-01-01', 'due_date' => '2026-12-31'], $dados);
    }

    private function paymentInput(string $principal = '100.00'): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'settlement_date' => '2026-10-16',
            'original_value' => $principal, 'payment_methods' => [[
                'payment_type_id' => $this->forma, 'financial_account_id' => $this->conta, 'value' => $principal,
            ]]];
    }

    private function runRace(array $pedidos): array
    {
        // Commit de fixtures e necessario: os workers usam conexoes/processos independentes.
        DB::beginTransaction();
        DB::table('users')->where('id', $this->usuario->id)->lockForUpdate()->first();
        $conexoes = [];
        foreach ($pedidos as $pedido) {
            $processo = new Process([PHP_BINARY, base_path('tests/MySql/FinancialConcurrencyWorker.php')],
                base_path(), [
                    'APP_ENV' => 'testing',
                    'FINANCE_TEST_MYSQL_CONNECTION' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR),
                ], json_encode([...$pedido, 'user' => $this->usuario->id, 'email' => $this->usuario->email,
                    'receipt_root' => $this->receiptRoot], JSON_THROW_ON_ERROR), 30);
            $this->processos[] = $processo;
            $processo->start();
            $pronto = $processo->waitUntil(fn () => str_contains($processo->getOutput(), 'READY:'));
            $this->assertTrue($pronto, $processo->getErrorOutput());
            preg_match('/READY:([0-9]+)/', $processo->getOutput(), $partes);
            $conexoes[] = (int) $partes[1];
        }

        // Confirmar contencao real no InnoDB antes de liberar o lock de partida.
        $limite = microtime(true) + 8;
        do {
            $esperas = DB::table('information_schema.innodb_trx')
                ->whereIn('trx_mysql_thread_id', $conexoes)->where('trx_state', 'LOCK WAIT')->count();
            if ($esperas === count($pedidos)) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $limite);
        $this->assertSame(count($pedidos), $esperas, 'Workers devem disputar o lock antes da liberacao.');
        DB::commit();

        $resultados = [];
        foreach ($this->processos as $processo) {
            $this->assertSame(0, $processo->wait(), $processo->getErrorOutput());
            preg_match('/RESULT:(.+)/', $processo->getOutput(), $partes);
            $resultados[] = json_decode($partes[1], true, 512, JSON_THROW_ON_ERROR);
        }

        return $resultados;
    }

    private function cleanupOwnedFixture(): void
    {
        if ($this->usuario === null) {
            return;
        }
        if (DB::selectOne('SELECT DATABASE() AS nome')->nome !== 'gestao_financeira_testing'
            || ! DB::table('users')->where('id', $this->usuario->id)->where('email', $this->usuario->email)->exists()
            || ! str_starts_with($this->usuario->email, 'concurrency-')) {
            throw new \RuntimeException('Limpeza recusada: banco ou identidade da fixture inesperados.');
        }

        DB::transaction(function () {
            $id = $this->usuario->id;
            $titulos = DB::table('titles')->where('user_id', $id)->pluck('id');
            $baixas = DB::table('title_settlements')->whereIn('title_id', $titulos)->pluck('id');
            DB::table('receipts')->where('user_id', $id)->delete();
            DB::table('settlement_reversals')->where('user_id', $id)->delete();
            DB::table('title_settlement_methods')->whereIn('settlement_id', $baixas)->delete();
            DB::table('titles')->where('user_id', $id)->update(['generated_by_settlement_id' => null, 'parent_title_id' => null]);
            DB::table('title_settlements')->whereIn('id', $baixas)->delete();
            DB::table('title_installments')->whereIn('title_id', $titulos)->delete();
            DB::table('titles')->where('user_id', $id)->delete();
            foreach (['notifications', 'contacts', 'categories', 'financial_accounts', 'payment_types'] as $tabela) {
                DB::table($tabela)->where('user_id', $id)->delete();
            }
            DB::table('users')->where('id', $id)->where('email', $this->usuario->email)->delete();
        });
    }

    public function test_parallel_creations_allocate_distinct_numbers(): void
    {
        $resultados = $this->runRace([
            ['operation' => 'create', 'data' => $this->titleInput()],
            ['operation' => 'create', 'data' => $this->titleInput()],
        ]);
        $this->assertSame(['ok', 'ok'], array_column($resultados, 'status'));
        $this->assertSame(['REC-0001', 'REC-0002'], Title::orderBy('title_number')->pluck('title_number')->all());
    }

    public function test_parallel_identical_payments_create_one_settlement_and_one_recurring_cycle(): void
    {
        $titulo = app(TitleService::class)->create($this->titleInput([
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));
        $pedido = ['operation' => 'settle', 'title' => $titulo->id,
            'installment' => $titulo->installments->first()->id, 'data' => $this->paymentInput()];
        $resultados = $this->runRace([$pedido, $pedido]);
        $this->assertSame(['ok', 'ok'], array_column($resultados, 'status'));
        $this->assertSame($resultados[0]['id'], $resultados[1]['id']);
        $this->assertSame(1, $titulo->settlements()->count());
        $this->assertSame(1, $titulo->children()->count());
    }

    public function test_parallel_partial_payments_cannot_overdraw_remaining_principal(): void
    {
        $titulo = app(TitleService::class)->create($this->titleInput());
        $base = ['operation' => 'settle', 'title' => $titulo->id, 'installment' => $titulo->installments->first()->id];
        $resultados = $this->runRace([
            [...$base, 'data' => $this->paymentInput('60.00')],
            [...$base, 'data' => $this->paymentInput('60.00')],
        ]);
        $estados = array_column($resultados, 'status');
        sort($estados);
        $this->assertSame(['ok', 'validation'], $estados);
        $this->assertSame(1, $titulo->settlements()->count());
        $this->assertSame('partial', $titulo->fresh()->status);
    }

    public function test_parallel_cancel_and_payment_never_leave_a_cancelled_paid_title(): void
    {
        $titulo = app(TitleService::class)->create($this->titleInput());
        $resultados = $this->runRace([
            ['operation' => 'cancel', 'title' => $titulo->id],
            ['operation' => 'settle', 'title' => $titulo->id, 'installment' => $titulo->installments->first()->id,
                'data' => $this->paymentInput()],
        ]);
        $estados = array_column($resultados, 'status');
        sort($estados);
        $this->assertSame(['ok', 'validation'], $estados);
        $titulo = $titulo->fresh();
        $this->assertSame($titulo->status === 'paid' ? 1 : 0, $titulo->settlements()->count());
        $this->assertContains($titulo->status, ['paid', 'cancelled']);
    }

    public function test_parallel_edit_and_payment_use_the_committed_parcel_value(): void
    {
        $titulo = app(TitleService::class)->create($this->titleInput());
        $resultados = $this->runRace([
            ['operation' => 'update', 'title' => $titulo->id, 'data' => ['original_value' => '200.00']],
            ['operation' => 'settle', 'title' => $titulo->id, 'installment' => $titulo->installments->first()->id,
                'data' => $this->paymentInput()],
        ]);
        $titulo = $titulo->fresh();
        if ($titulo->original_value === '200.00') {
            $this->assertSame('partial', $titulo->status);
            $this->assertSame(['ok', 'ok'], array_column($resultados, 'status'));
        } else {
            $this->assertSame('100.00', $titulo->original_value);
            $this->assertSame('paid', $titulo->status);
            $this->assertSame(['validation', 'ok'], array_column($resultados, 'status'));
        }
        $this->assertSame('100.00', $titulo->settlements()->first()->original_value);
    }

    public function test_parallel_identical_reversals_create_one_audit_record(): void
    {
        $titulo = app(TitleService::class)->create($this->titleInput());
        $baixa = app(TitleService::class)->settle($titulo, $titulo->installments->first(), $this->paymentInput());
        $pedido = ['operation' => 'reverse', 'settlement' => $baixa->id, 'data' => [
            'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-16', 'reason' => 'Teste concorrente',
        ]];
        $resultados = $this->runRace([$pedido, $pedido]);
        $this->assertSame(['ok', 'ok'], array_column($resultados, 'status'));
        $this->assertSame($resultados[0]['id'], $resultados[1]['id']);
        $this->assertSame(1, $baixa->reversal()->count());
        $this->assertSame('pending', $titulo->fresh()->status);
    }

    public function test_parallel_origin_reversal_and_next_cycle_payment_preserve_the_chain(): void
    {
        $titulo = app(TitleService::class)->create($this->titleInput([
            'is_recurring' => true, 'recurrence_type' => 'monthly', 'recurrence_day' => 31,
        ]));
        $baixa = app(TitleService::class)->settle($titulo, $titulo->installments->first(), $this->paymentInput());
        $filho = $baixa->fresh()->generatedTitle;
        $resultados = $this->runRace([
            ['operation' => 'reverse', 'settlement' => $baixa->id, 'data' => [
                'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-16', 'reason' => 'Teste de cadeia',
            ]],
            ['operation' => 'settle', 'title' => $filho->id, 'installment' => $filho->installments->first()->id,
                'data' => $this->paymentInput('40.00')],
        ]);
        $estados = array_column($resultados, 'status');
        sort($estados);
        $this->assertSame(['ok', 'validation'], $estados);
        if ($titulo->fresh()->status === 'pending') {
            $this->assertSame('cancelled', $filho->fresh()->status);
            $this->assertSame(0, $filho->settlements()->count());
        } else {
            $this->assertSame('paid', $titulo->fresh()->status);
            $this->assertSame('partial', $filho->fresh()->status);
            $this->assertSame(0, $baixa->reversal()->count());
        }
    }

    private function useTemporaryReceiptDisk(): void
    {
        $this->receiptRoot = sys_get_temp_dir().'/financial-concurrency-'.Str::uuid();
        config(['filesystems.disks.receipts.root' => $this->receiptRoot]);
        Storage::forgetDisk('receipts');
    }

    public function test_parallel_receipt_issuance_creates_one_number_and_one_file(): void
    {
        $this->useTemporaryReceiptDisk();
        $titulo = app(TitleService::class)->create($this->titleInput());
        $baixa = app(TitleService::class)->settle($titulo, $titulo->installments->first(), $this->paymentInput());
        $pedido = ['operation' => 'receipt', 'settlement' => $baixa->id];
        $resultados = $this->runRace([$pedido, $pedido]);
        $this->assertSame(['ok', 'ok'], array_column($resultados, 'status'));
        $this->assertSame($resultados[0]['id'], $resultados[1]['id']);
        $this->assertSame(1, $baixa->receipt()->count());
        $this->assertCount(1, Storage::disk('receipts')->allFiles());
    }

    public function test_parallel_notification_runs_cannot_duplicate_alerts(): void
    {
        app(TitleService::class)->create($this->titleInput(['due_date' => '2026-10-20']));
        $pedido = ['operation' => 'notify'];
        $resultados = $this->runRace([$pedido, $pedido]);
        $this->assertSame(['ok', 'ok'], array_column($resultados, 'status'));
        $this->assertSame(1, array_sum(array_column($resultados, 'id')));
        $this->assertSame(1, DB::table('notifications')->where('user_id', $this->usuario->id)->count());
    }

    public function test_parallel_receipt_and_reversal_never_leave_a_valid_receipt_of_reversed_payment(): void
    {
        $this->useTemporaryReceiptDisk();
        $titulo = app(TitleService::class)->create($this->titleInput());
        $baixa = app(TitleService::class)->settle($titulo, $titulo->installments->first(), $this->paymentInput());
        $resultados = $this->runRace([
            ['operation' => 'receipt', 'settlement' => $baixa->id],
            ['operation' => 'reverse', 'settlement' => $baixa->id, 'data' => [
                'idempotency_key' => (string) Str::uuid(), 'reversal_date' => '2026-10-16', 'reason' => 'Teste de recibo',
            ]],
        ]);
        $this->assertSame('ok', $resultados[1]['status']);
        $recibo = $baixa->fresh()->receipt;
        if ($recibo !== null) {
            $this->assertNotNull($recibo->voided_at);
            $this->assertSame('ok', $resultados[0]['status']);
        } else {
            $this->assertSame('validation', $resultados[0]['status']);
            $this->assertCount(0, Storage::disk('receipts')->allFiles());
        }
    }
}
