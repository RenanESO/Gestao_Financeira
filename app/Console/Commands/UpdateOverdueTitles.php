<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OverdueTitleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateOverdueTitles extends Command
{
    protected $signature = 'titles:update-overdue {--user= : Processar somente este usuário} {--date= : Data de corte Y-m-d}';

    protected $description = 'Atualiza parcelas e títulos vencidos, preservando pagamentos e cancelamentos';

    public function handle(OverdueTitleService $service): int
    {
        try {
            $dados = Validator::make([
                'user' => $this->option('user'), 'date' => $this->option('date') ?? now()->toDateString(),
            ], [
                'user' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
                'date' => ['required', 'date_format:Y-m-d'],
            ])->validate();
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $titulos = 0;
        $parcelas = 0;
        User::query()->when($dados['user'] !== null, fn ($query) => $query->whereKey($dados['user']))
            ->chunkById(100, function ($usuarios) use ($service, $dados, &$titulos, &$parcelas) {
                foreach ($usuarios as $usuario) {
                    $resultado = $service->updateFor($usuario, $dados['date']);
                    $titulos += $resultado['titles'];
                    $parcelas += $resultado['installments'];
                }
            });
        $this->info("Titulos atualizados: {$titulos}; parcelas atualizadas: {$parcelas}.");

        return self::SUCCESS;
    }
}
