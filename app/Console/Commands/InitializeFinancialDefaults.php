<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FinancialDefaultsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InitializeFinancialDefaults extends Command
{
    protected $signature = 'finance:initialize {user : ID de um usuário existente}';

    protected $description = 'Cria categorias e formas padrão somente quando ausentes, sem usuário de exemplo';

    public function handle(FinancialDefaultsService $service): int
    {
        try {
            $dados = Validator::make(['user' => $this->argument('user')], [
                'user' => ['required', 'integer', 'min:1', 'exists:users,id'],
            ])->validate();
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $resultado = $service->initialize(User::findOrFail($dados['user']));
        $this->info("Categorias criadas: {$resultado['categories']}; formas criadas: {$resultado['payment_types']}.");

        return self::SUCCESS;
    }
}
