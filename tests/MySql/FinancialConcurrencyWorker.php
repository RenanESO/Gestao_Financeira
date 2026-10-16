<?php

// Processo auxiliar persistente da suite; nunca executar contra o banco da aplicacao.
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReceiptService;
use App\Services\SettlementReversalService;
use App\Services\TitleService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $conexao = json_decode(getenv('FINANCE_TEST_MYSQL_CONNECTION'), true, 512, JSON_THROW_ON_ERROR);
    if (($conexao['database'] ?? null) !== 'gestao_financeira_testing' || ($conexao['driver'] ?? null) !== 'mysql') {
        throw new RuntimeException('Banco de teste obrigatorio.');
    }
    $conexao['url'] = null;
    config(['database.default' => 'mysql', 'database.connections.mysql' => $conexao,
        'cache.default' => 'array', 'session.driver' => 'array']);
    DB::purge('mysql');
    if (DB::selectOne('SELECT DATABASE() AS nome')->nome !== 'gestao_financeira_testing') {
        throw new RuntimeException('Banco fisico inesperado.');
    }
    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    Carbon::setTestNow('2026-10-16 10:00:00');
    CarbonImmutable::setTestNow('2026-10-16 10:00:00');
    $pedido = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    if (! empty($pedido['receipt_root'])) {
        $prefixo = preg_quote(sys_get_temp_dir().'/financial-concurrency-', '/');
        if (! preg_match('/^'.$prefixo.'[a-f0-9-]{36}$/D', $pedido['receipt_root'])) {
            throw new RuntimeException('Disco temporario de teste obrigatorio.');
        }
        config(['filesystems.disks.receipts.root' => $pedido['receipt_root']]);
    }
    if (! preg_match('/^concurrency-[a-f0-9-]+@example\.test$/D', $pedido['email'] ?? '')) {
        throw new RuntimeException('Identidade de fixture obrigatoria.');
    }
    $usuario = User::whereKey($pedido['user'])->where('email', $pedido['email'])->firstOrFail();
    Auth::login($usuario);
    echo 'READY:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
    flush();

    $service = app(TitleService::class);
    $registro = match ($pedido['operation']) {
        'create' => $service->create($pedido['data']),
        'settle' => $service->settle(Title::findOrFail($pedido['title']),
            TitleInstallment::findOrFail($pedido['installment']), $pedido['data']),
        'update' => $service->update(Title::findOrFail($pedido['title']), $pedido['data']),
        'cancel' => $service->cancel(Title::findOrFail($pedido['title'])),
        'reverse' => app(SettlementReversalService::class)->reverse(TitleSettlement::findOrFail($pedido['settlement']), $pedido['data']),
        'receipt' => app(ReceiptService::class)->generate(TitleSettlement::findOrFail($pedido['settlement'])),
        'notify' => app(NotificationService::class)->generateFor($usuario, '2026-10-16'),
        default => throw new RuntimeException('Operacao nao suportada.'),
    };
    echo 'RESULT:'.json_encode(['status' => 'ok', 'id' => is_int($registro) ? $registro : $registro->id], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException $exception) {
    echo 'RESULT:'.json_encode(['status' => 'validation', 'fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    // Nao imprimir conexao/credenciais ou dumps do pedido nos resultados.
    fwrite(STDERR, 'Falha no worker: '.$exception::class."\n");
    exit(2);
}
