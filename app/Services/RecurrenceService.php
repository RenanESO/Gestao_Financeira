<?php

namespace App\Services;

use App\Models\Title;
use Carbon\CarbonImmutable;
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
            throw ValidationException::withMessages(['recurrence_day' => 'Recorrencia invalida.']);
        }

        $vencimento = CarbonImmutable::parse($titulo->due_date);
        if ($titulo->recurrence_type === 'monthly') {
            $proximo = $vencimento->startOfMonth()->addMonth();
        } elseif ($titulo->recurrence_type === 'yearly' && is_int($mes) && $mes >= 1 && $mes <= 12) {
            $proximo = $vencimento->startOfYear()->addYear()->month($mes);
        } else {
            throw ValidationException::withMessages(['recurrence_type' => 'Tipo ou mes de recorrencia invalido.']);
        }

        return $proximo->day(min($dia, $proximo->daysInMonth))->startOfDay();
    }
}
