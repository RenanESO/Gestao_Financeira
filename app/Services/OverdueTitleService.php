<?php

namespace App\Services;

use App\Models\Scopes\OwnerScope;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OverdueTitleService
{
    /**
     * Rotina interna sem sessao: sempre exige um usuario persistido explicito.
     *
     * @return array{titles: int, installments: int}
     */
    public function updateFor(User $usuario, ?string $data = null): array
    {
        $data = Validator::make(['date' => $data ?? now()->toDateString()], [
            'date' => ['required', 'date_format:Y-m-d'],
        ])->validate()['date'];

        return DB::transaction(function () use ($usuario, $data) {
            $usuario = User::whereKey($usuario->getKey())->lockForUpdate()->firstOrFail();
            $titulos = Title::withoutGlobalScope(OwnerScope::class)
                ->where('user_id', $usuario->id)->whereIn('status', ['pending', 'overdue', 'partial']);
            $parcelas = TitleInstallment::withoutGlobalScope(OwnerScope::class)
                ->whereIn('title_id', (clone $titulos)->select('titles.id'))
                ->where('status', 'pending')->where('due_date', '<', $data)
                ->update(['status' => 'overdue']);
            $quantidade = (clone $titulos)->where('status', 'pending')
                ->whereHas('installments', fn ($query) => $query->withoutGlobalScope(OwnerScope::class)
                    ->where('status', 'overdue')->where('due_date', '<', $data))
                ->update(['status' => 'overdue']);

            return ['titles' => $quantidade, 'installments' => $parcelas];
        }, 3);
    }
}
