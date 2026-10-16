<?php

namespace App\Models\Scopes;

use App\Models\FinancialModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class OwnerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $usuarioId = Auth::id();

        // Sem identidade, consultas financeiras nunca retornam dados de todos os usuarios.
        if ($usuarioId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        /** @var FinancialModel $model */
        $model->constrainToOwner($builder, (int) $usuarioId);
    }
}
