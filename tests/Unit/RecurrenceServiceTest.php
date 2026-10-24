<?php

namespace Tests\Unit;

use App\Models\Title;
use App\Services\RecurrenceService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecurrenceServiceTest extends TestCase
{
    public static function cycles(): array
    {
        return [
            ['2026-01-31', 'monthly', 31, null, '2026-02-28'],
            ['2026-02-28', 'monthly', 31, null, '2026-03-31'],
            ['2026-12-31', 'monthly', 31, null, '2027-01-31'],
            ['2024-02-29', 'yearly', 29, 2, '2025-02-28'],
            ['2027-02-28', 'yearly', 29, 2, '2028-02-29'],
        ];
    }

    #[DataProvider('cycles')]
    public function test_next_cycle_uses_nominal_due_date_not_payment_date(string $vencimento, string $tipo, int $dia, ?int $mes, string $esperado): void
    {
        $titulo = new Title([
            'due_date' => $vencimento, 'is_recurring' => true,
            'recurrence_type' => $tipo, 'recurrence_day' => $dia, 'recurrence_month' => $mes,
        ]);
        $this->assertSame($esperado, app(RecurrenceService::class)->nextDueDate($titulo)->toDateString());
        $this->assertSame($vencimento, $titulo->due_date->toDateString());
    }

    public static function firstCycles(): array
    {
        return [
            'dia ainda no mês da emissão' => ['2026-10-16', 'monthly', 20, null, '2026-10-20'],
            'mesmo dia da emissão' => ['2026-10-16', 'monthly', 16, null, '2026-10-16'],
            'dia já passou: próximo mês' => ['2026-10-16', 'monthly', 10, null, '2026-11-10'],
            'dia 31 em fevereiro' => ['2026-02-01', 'monthly', 31, null, '2026-02-28'],
            'dia 31 após fim de janeiro' => ['2026-01-31', 'monthly', 31, null, '2026-01-31'],
            'virada de ano' => ['2026-12-20', 'monthly', 5, null, '2027-01-05'],
            'anual no mesmo ano' => ['2026-10-16', 'yearly', 16, 10, '2026-10-16'],
            'anual já passou' => ['2026-10-16', 'yearly', 10, 3, '2027-03-10'],
            'anual 29/02 fora de bissexto' => ['2026-03-01', 'yearly', 29, 2, '2027-02-28'],
        ];
    }

    #[DataProvider('firstCycles')]
    public function test_first_due_date_is_first_configured_day_from_issue_date(string $emissao, string $tipo, int $dia, ?int $mes, string $esperado): void
    {
        $this->assertSame($esperado, app(RecurrenceService::class)->firstDueDate($emissao, $tipo, $dia, $mes)->toDateString());
    }

    public static function invalidFirstCycles(): array
    {
        return [
            'emissão invalida' => ['16/10/2026', 'monthly', 10, null, 'issue_date'],
            'emissão inexistente' => ['2026-02-30', 'monthly', 10, null, 'issue_date'],
            'dia inválido' => ['2026-10-16', 'monthly', 32, null, 'recurrence_day'],
            'mês ausente na anual' => ['2026-10-16', 'yearly', 10, null, 'recurrence_month'],
            'tipo inválido' => ['2026-10-16', 'weekly', 10, null, 'recurrence_type'],
        ];
    }

    #[DataProvider('invalidFirstCycles')]
    public function test_first_due_date_rejects_invalid_configuration(string $emissao, string $tipo, int $dia, ?int $mes, string $campo): void
    {
        try {
            app(RecurrenceService::class)->firstDueDate($emissao, $tipo, $dia, $mes);
            $this->fail('Configuracao invalida deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    public function test_non_recurring_titles_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(RecurrenceService::class)->nextDueDate(new Title(['due_date' => '2026-01-31']));
    }
}
