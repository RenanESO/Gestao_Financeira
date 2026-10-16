<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class FinancialAccountService
{
    /**
     * Saldo realizado ate a data: abertura + formas recebidas - formas pagas.
     * Inclui historico de titulos excluidos logicamente e contas desativadas.
     *
     * @return array{as_of: string, initial_balance: string, receipts: string, payments: string, balance: string}
     */
    public function balance(FinancialAccount $conta, ?string $data = null): array
    {
        if (Auth::id() === null) {
            throw new AuthenticationException;
        }
        $data = Validator::make(['date' => $data ?? now()->toDateString()], [
            'date' => ['required', 'date_format:Y-m-d'],
        ])->validate()['date'];

        return DB::transaction(function () use ($conta, $data) {
            $usuario = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $conta = FinancialAccount::whereKey($conta->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('view', $conta);
            $recebimentos = 0;
            $pagamentos = 0;

            // Somar strings em PHP evita o SUM com ponto flutuante usado pelo SQLite.
            $movimentos = DB::table('title_settlement_methods as methods')
                ->join('title_settlements as settlements', 'settlements.id', '=', 'methods.settlement_id')
                ->join('titles', 'titles.id', '=', 'settlements.title_id')
                ->leftJoin('settlement_reversals as reversals', 'reversals.settlement_id', '=', 'settlements.id')
                ->where('methods.financial_account_id', $conta->id)
                ->where('titles.user_id', $usuario->id)
                ->whereDate('settlements.settlement_date', '<=', $data)
                ->where(fn ($query) => $query->whereNull('reversals.id')->orWhereDate('reversals.reversal_date', '>', $data))
                ->select('titles.title_type')->selectRaw('CAST(methods.value AS CHAR) AS value')->cursor();

            foreach ($movimentos as $movimento) {
                $valor = Money::cents($movimento->value, 'movements');
                if ($movimento->title_type === 'receivable') {
                    $recebimentos += $valor;
                    Money::decimal($recebimentos, 'receipts');
                } else {
                    $pagamentos += $valor;
                    Money::decimal($pagamentos, 'payments');
                }
            }
            $abertura = Money::signedCents($conta->initial_balance, 'initial_balance');

            return [
                'as_of' => $data,
                'initial_balance' => Money::signedDecimal($abertura),
                'receipts' => Money::decimal($recebimentos),
                'payments' => Money::decimal($pagamentos),
                'balance' => Money::signedDecimal($abertura + $recebimentos - $pagamentos, 'balance'),
            ];
        }, 3);
    }
}
