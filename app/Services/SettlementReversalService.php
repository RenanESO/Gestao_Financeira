<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\SettlementReversal;
use App\Models\Title;
use App\Models\TitleSettlement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettlementReversalService
{
    public function reverse(TitleSettlement $baixa, array $dados): SettlementReversal
    {
        if (Auth::id() === null) {
            throw new AuthenticationException;
        }
        if (is_string($dados['reason'] ?? null)) {
            $dados['reason'] = trim($dados['reason']);
        }
        foreach (array_keys($dados) as $campo) {
            if (! in_array($campo, ['idempotency_key', 'reversal_date', 'reason'], true)) {
                $this->invalid((string) $campo, 'Campo nao permitido no estorno.');
            }
        }
        $dados = Validator::make($dados, [
            'idempotency_key' => ['required', 'uuid'],
            'reversal_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ])->validate();
        $dados['idempotency_key'] = strtolower($dados['idempotency_key']);

        return DB::transaction(function () use ($baixa, $dados) {
            $usuario = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            // Buscar novamente evita confiar em title_id adulterado no objeto recebido.
            $referencia = TitleSettlement::whereKey($baixa->getKey())->firstOrFail();
            $titulo = Title::withTrashed()->whereKey($referencia->title_id)->lockForUpdate()->firstOrFail();
            $parcelas = $titulo->installments()->orderBy('installment_number')->lockForUpdate()->get();
            $baixa = TitleSettlement::whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('reverse', $baixa);
            $hash = hash('sha256', json_encode([$baixa->id, $dados], JSON_THROW_ON_ERROR));
            $existente = SettlementReversal::where('idempotency_key', $dados['idempotency_key'])->lockForUpdate()->first();
            if ($existente !== null) {
                if (! hash_equals($existente->request_hash, $hash)) {
                    $this->invalid('idempotency_key', 'Esta chave ja foi usada em outro estorno.');
                }

                return $existente;
            }
            if ($baixa->reversal()->lockForUpdate()->first() !== null) {
                $this->invalid('settlement_id', 'Baixa ja estornada. Reutilize a chave original para repetir o pedido.');
            }
            if ($dados['reversal_date'] < $baixa->settlement_date->toDateString()) {
                $this->invalid('reversal_date', 'Estorno nao pode anteceder a baixa.');
            }
            if ($titulo->status === 'cancelled' || $parcelas->count() !== $titulo->installments_count
                || ! $parcelas->contains('id', $baixa->title_installment_id)) {
                $this->invalid('installments', 'Titulo ou vinculo da parcela inconsistente.');
            }
            $total = 0;
            foreach ($parcelas as $indice => $parcela) {
                $valor = Money::cents($parcela->value);
                if ($valor <= 0 || $parcela->installment_number !== $indice + 1 || $parcela->status === 'cancelled') {
                    $this->invalid('installments', 'Parcelamento inconsistente.');
                }
                $total += $valor;
                Money::decimal($total, 'installments');
            }
            if ($total !== Money::cents($titulo->original_value)) {
                $this->invalid('installments', 'Soma das parcelas diverge do titulo.');
            }
            $principal = Money::cents($baixa->original_value);
            $desconto = Money::cents($baixa->discount);
            $totalBaixa = $principal - $desconto + Money::cents($baixa->interest) + Money::cents($baixa->fine);
            $formas = $baixa->methods()->with(['financialAccount', 'paymentType'])->lockForUpdate()->get();
            $somaFormas = 0;
            foreach ($formas as $forma) {
                $valor = Money::cents($forma->value);
                if ($valor <= 0 || $forma->financialAccount === null || $forma->paymentType === null) {
                    $this->invalid('settlement', 'Forma ou vinculo historico da baixa inconsistente.');
                }
                $somaFormas += $valor;
                Money::decimal($somaFormas, 'settlement');
            }
            if ($principal <= 0 || $desconto > $principal || $totalBaixa <= 0
                || $totalBaixa !== Money::cents($baixa->total_value) || $somaFormas !== $totalBaixa) {
                $this->invalid('settlement', 'Valores historicos da baixa exigem reconciliacao.');
            }

            $this->cancelUnmovedGeneratedTitles($titulo);
            $recibos = Receipt::where('settlement_id', $baixa->id)->lockForUpdate()->get();
            // Uma referencia cruzada legada nao pode ser invalidada em nome de outro dono.
            if (DB::table('receipts')->where('settlement_id', $baixa->id)->count() !== $recibos->count()) {
                $this->invalid('receipt', 'Recibo pertence a outro usuario.');
            }

            $estorno = new SettlementReversal;
            $estorno->forceFill([...$dados, 'request_hash' => $hash]);
            $estorno->user()->associate($usuario);
            $estorno->settlement()->associate($baixa);
            $estorno->save();
            foreach ($recibos as $recibo) {
                $recibo->voided_at = $recibo->voided_at ?? now();
                $recibo->save();
            }

            $temPrincipal = false;
            $temVencida = false;
            $todasPagas = true;
            foreach ($parcelas as $parcela) {
                $pago = 0;
                foreach ($parcela->settlements()->active()->lockForUpdate()->get() as $ativa) {
                    if ((int) $ativa->title_id !== (int) $titulo->id) {
                        $this->invalid('installments', 'Baixa vinculada a outro titulo.');
                    }
                    $pago += Money::cents($ativa->original_value);
                }
                if ($pago > Money::cents($parcela->value)) {
                    $this->invalid('installments', 'Principal liquidado excede a parcela.');
                }
                $temPrincipal = $temPrincipal || $pago > 0;
                $parcela->status = $pago === Money::cents($parcela->value) ? 'paid'
                    : ($parcela->due_date->toDateString() < now()->toDateString() ? 'overdue' : 'pending');
                $temVencida = $temVencida || $parcela->status === 'overdue';
                $todasPagas = $todasPagas && $parcela->status === 'paid';
                $parcela->save();
            }
            $titulo->status = $todasPagas ? 'paid' : ($temPrincipal ? 'partial' : ($temVencida ? 'overdue' : 'pending'));
            $titulo->save();

            return $estorno;
        }, 3);
    }

    private function cancelUnmovedGeneratedTitles(Title $titulo): void
    {
        $gerados = Title::withTrashed()->whereIn('generated_by_settlement_id',
            $titulo->settlements()->active()->select('title_settlements.id'))->lockForUpdate()->get();
        if ($titulo->is_recurring && $titulo->status === 'paid' && $gerados->isEmpty()) {
            $this->invalid('recurrence', 'Recorrencia legada sem origem rastreavel exige reconciliacao antes do estorno.');
        }
        foreach ($gerados as $gerado) {
            if ($gerado->settlements()->lockForUpdate()->first() !== null
                || Title::withTrashed()->whereIn('generated_by_settlement_id',
                    $gerado->settlements()->select('title_settlements.id'))->lockForUpdate()->first() !== null
                || $gerado->status === 'paid' || $gerado->status === 'partial'
                || $gerado->installments()->where('status', 'paid')->lockForUpdate()->first() !== null) {
                $this->invalid('recurrence', 'O proximo ciclo possui historico. Trate a cadeia antes de estornar a origem.');
            }
            $gerado->installments()->update(['status' => 'cancelled']);
            $gerado->status = 'cancelled';
            $gerado->save();
        }
    }

    private function invalid(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
