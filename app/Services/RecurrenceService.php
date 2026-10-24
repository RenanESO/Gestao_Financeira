<?php

namespace App\Services;

use App\Models\Title;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Validation\ValidationException;

class RecurrenceService
{
    /**
     * Calcula o proximo ciclo nominal, mantendo o dia configurado mesmo apos fevereiro.
     */
    public function nextDueDate(Title $titulo): CarbonImmutable
    {
        $dia = $titulo->recurrence_day;
        $mes = $titulo->recurrence_month;

        if (! $titulo->is_recurring || ! is_int($dia) || $dia < 1 || $dia > 31) {
            throw ValidationException::withMessages(['recurrence_day' => 'Recorrência inválida.']);
        }

        $vencimento = CarbonImmutable::parse($titulo->due_date);
        if ($titulo->recurrence_type === 'monthly') {
            $proximo = $vencimento->startOfMonth()->addMonth();
        } elseif ($titulo->recurrence_type === 'yearly' && is_int($mes) && $mes >= 1 && $mes <= 12) {
            $proximo = $vencimento->startOfYear()->addYear()->month($mes);
        } else {
            throw ValidationException::withMessages(['recurrence_type' => 'Tipo ou mês de recorrência inválido.']);
        }

        return $proximo->day(min($dia, $proximo->daysInMonth))->startOfDay();
    }

    /**
     * Primeiro vencimento de um titulo recorrente: a primeira data igual ou posterior a emissao
     * que corresponda ao dia (e, na anual, ao mes) configurado, limitado ao ultimo dia do mes.
     */
    public function firstDueDate(mixed $emissao, mixed $tipo, mixed $dia, mixed $mes = null): CarbonImmutable
    {
        try {
            $data = is_string($emissao) ? CarbonImmutable::createFromFormat('!Y-m-d', $emissao) : false;
        } catch (InvalidFormatException) {
            $data = false;
        }
        if ($data === false || $data->format('Y-m-d') !== $emissao) {
            throw ValidationException::withMessages(['issue_date' => 'Informe uma data de emissão válida.']);
        }
        $dia = filter_var($dia, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
        if ($dia === false) {
            throw ValidationException::withMessages(['recurrence_day' => 'Informe um dia previsto entre 1 e 31.']);
        }

        if ($tipo === 'monthly') {
            $ciclo = $data->startOfMonth();
            $passo = 'addMonthNoOverflow';
        } elseif ($tipo === 'yearly') {
            $mes = filter_var($mes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
            if ($mes === false) {
                throw ValidationException::withMessages(['recurrence_month' => 'Informe um mês previsto entre 1 e 12.']);
            }
            $ciclo = $data->startOfYear()->month($mes);
            $passo = 'addYearNoOverflow';
        } else {
            throw ValidationException::withMessages(['recurrence_type' => 'Tipo de recorrência inválido.']);
        }

        $vencimento = $ciclo->day(min($dia, $ciclo->daysInMonth));
        if ($vencimento->lessThan($data)) {
            $ciclo = $ciclo->{$passo}();
            $vencimento = $ciclo->day(min($dia, $ciclo->daysInMonth));
        }

        return $vencimento;
    }
}
