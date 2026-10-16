<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PaymentType;
use App\Models\Scopes\OwnerScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialDefaultsService
{
    /**
     * Inicializacao interna, sem usuario de demonstracao e sem sobrescrever cadastros.
     *
     * @return array{categories: int, payment_types: int}
     */
    public function initialize(User $usuario, ?string $grupo = null): array
    {
        $models = ['categories' => Category::class, 'payment_types' => PaymentType::class];
        if ($grupo !== null && ! array_key_exists($grupo, $models)) {
            throw new InvalidArgumentException('Grupo de cadastros nao suportado.');
        }

        return DB::transaction(function () use ($usuario, $grupo, $models) {
            $usuario = User::whereKey($usuario->getKey())->lockForUpdate()->firstOrFail();
            $resultado = ['categories' => 0, 'payment_types' => 0];
            foreach ($models as $nome => $model) {
                if ($grupo !== null && $grupo !== $nome) {
                    continue;
                }
                foreach (config("financial_defaults.$nome", []) as $dados) {
                    $existe = $model::withoutGlobalScope(OwnerScope::class)->where('user_id', $usuario->id)
                        ->whereRaw('LOWER(name) = LOWER(?)', [$dados['name']])->lockForUpdate()->first();
                    if ($existe !== null) {
                        continue;
                    }
                    $registro = new $model($dados);
                    $registro->user()->associate($usuario);
                    $registro->save();
                    $resultado[$nome]++;
                }
            }

            return $resultado;
        }, 3);
    }
}
