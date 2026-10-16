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

    public function test_non_recurring_titles_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(RecurrenceService::class)->nextDueDate(new Title(['due_date' => '2026-01-31']));
    }
}
