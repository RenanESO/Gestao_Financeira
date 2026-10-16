<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SendDueSoonNotifications extends Command
{
    protected $signature = 'notifications:send-due-soon {--user= : ID do usuario} {--date= : Corte Y-m-d}';

    protected $description = 'Cria alertas internos de parcelas vencidas/proximas, sem duplicar reexecucoes';

    public function handle(NotificationService $service): int
    {
        try {
            $dados = Validator::make(['user' => $this->option('user'),
                'date' => $this->option('date') ?? now()->toDateString()], [
                    'user' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
                    'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                ])->validate();
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $criadas = 0;
        User::query()->when($dados['user'] !== null, fn ($query) => $query->whereKey($dados['user']))
            ->chunkById(100, function ($usuarios) use ($service, $dados, &$criadas) {
                foreach ($usuarios as $usuario) {
                    $criadas += $service->generateFor($usuario, $dados['date']);
                }
            });
        $this->info("Notificacoes criadas: {$criadas}.");

        return self::SUCCESS;
    }
}
