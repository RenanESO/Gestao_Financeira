<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinancialAnalyticsService
{
    public const GROUPINGS = [
        'contact' => 'Conta > Contato > Data',
        'date' => 'Conta > Data',
        'category' => 'Conta > Categoria > Data',
    ];

    public function options(): array
    {
        $this->identity();

        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'methods' => PaymentType::orderBy('name')->get(['id', 'name']),
            'accounts' => FinancialAccount::orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::withTrashed()->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function analyze(array $filters): array
    {
        $userId = $this->identity();
        $rules = [
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'grouping' => ['required', Rule::in(array_keys(self::GROUPINGS))],
        ];
        foreach ([
            'excluded_categories' => 'categories',
            'excluded_methods' => 'payment_types',
            'excluded_accounts' => 'financial_accounts',
            'contacts' => 'contacts',
        ] as $field => $table) {
            $rules[$field] = ['present', 'array', 'max:1000'];
            $rules[$field.'.*'] = ['integer', 'distinct', Rule::exists($table, 'id')->where('user_id', $userId)];
        }
        $filters = Validator::make($filters, $rules)->validate();
        if (CarbonImmutable::parse($filters['start'])->diffInDays(CarbonImmutable::parse($filters['end'])) > 1830) {
            throw ValidationException::withMessages(['end' => 'Selecione um periodo de ate cinco anos.']);
        }

        // O mesmo lock das operacoes financeiras garante uma leitura coerente entre baixas e estornos.
        return DB::transaction(function () use ($filters, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $query = DB::table('title_settlement_methods as m')
                ->join('title_settlements as s', 's.id', '=', 'm.settlement_id')
                ->join('titles as t', 't.id', '=', 's.title_id')
                ->join('financial_accounts as a', 'a.id', '=', 'm.financial_account_id')
                ->join('payment_types as p', 'p.id', '=', 'm.payment_type_id')
                ->leftJoin('categories as c', function ($join) use ($userId) {
                    $join->on('c.id', '=', 't.category_id')->where('c.user_id', $userId);
                })
                ->leftJoin('contacts as ct', function ($join) use ($userId) {
                    $join->on('ct.id', '=', 't.contact_id')->where('ct.user_id', $userId);
                })
                ->leftJoin('settlement_reversals as r', 'r.settlement_id', '=', 's.id')
                ->where('t.user_id', $userId)->where('a.user_id', $userId)->where('p.user_id', $userId)
                ->where(function ($q) use ($filters) {
                    $q->where(fn ($q) => $q->whereDate('s.settlement_date', '>=', $filters['start'])
                        ->whereDate('s.settlement_date', '<=', $filters['end']))
                        ->orWhere(fn ($q) => $q->whereDate('r.reversal_date', '>=', $filters['start'])
                            ->whereDate('r.reversal_date', '<=', $filters['end']));
                })
                ->when($filters['excluded_categories'], fn ($q) => $q->where(fn ($q) => $q
                    ->whereNull('t.category_id')->orWhereNotIn('t.category_id', $filters['excluded_categories'])))
                ->when($filters['excluded_methods'], fn ($q) => $q->whereNotIn('m.payment_type_id', $filters['excluded_methods']))
                ->when($filters['excluded_accounts'], fn ($q) => $q->whereNotIn('m.financial_account_id', $filters['excluded_accounts']))
                ->when($filters['contacts'], fn ($q) => $q->whereIn('t.contact_id', $filters['contacts']))
                ->select([
                    'm.id', 's.id as settlement_id', 's.settlement_date', 'r.reversal_date',
                    't.title_number', 't.description', 't.title_type',
                    'a.id as account_id', 'a.name as account', 'p.id as method_id', 'p.name as method',
                    'c.id as category_id', 'c.name as category', 'ct.id as contact_id', 'ct.name as contact',
                ])->selectRaw('CAST(m.value AS CHAR) as value')->orderBy('m.id')->limit(10001)->get();
            if ($query->count() > 10000) {
                throw ValidationException::withMessages(['start' => 'Mais de 10.000 formas no periodo. Restrinja os filtros para gerar a analise completa.']);
            }
            $rows = [];
            foreach ($query as $movement) {
                foreach (['settlement_date' => 1, 'reversal_date' => -1] as $field => $sign) {
                    $date = $movement->$field;
                    if ($date === null) {
                        continue;
                    }
                    $date = CarbonImmutable::parse($date)->toDateString();
                    if ($date < $filters['start'] || $date > $filters['end']) {
                        continue;
                    }
                    $amount = Money::cents($movement->value) * $sign;
                    $rows[] = [
                        'date' => $date,
                        'event' => $sign === 1 ? 'Baixa' : 'Estorno',
                        'event_key' => $movement->settlement_id.':'.$sign,
                        'settlement_id' => $movement->settlement_id,
                        'title_number' => $movement->title_number, 'description' => $movement->description,
                        'account_id' => $movement->account_id, 'account' => $movement->account,
                        'method_id' => $movement->method_id, 'method' => $movement->method,
                        'category_id' => $movement->category_id, 'category' => $movement->category ?? 'Sem categoria',
                        'contact_id' => $movement->contact_id, 'contact' => $movement->contact ?? 'Sem contato',
                        'income' => $movement->title_type === 'receivable' ? $amount : 0,
                        'expense' => $movement->title_type === 'payable' ? $amount : 0,
                    ];
                }
            }
            usort($rows, fn ($a, $b) => [$a['date'], $a['settlement_id'], $a['event']] <=> [$b['date'], $b['settlement_id'], $b['event']]);
            $daily = [];
            $date = CarbonImmutable::parse($filters['start']);
            while ($date->toDateString() <= $filters['end']) {
                $daily[$date->toDateString()] = ['income' => 0, 'expense' => 0];
                $date = $date->addDay();
            }
            foreach ($rows as $row) {
                $daily[$row['date']]['income'] += $row['income'];
                $daily[$row['date']]['expense'] += $row['expense'];
            }
            // Dias com baixa e estorno continuam visiveis mesmo quando o liquido e zero.
            $movementDates = array_fill_keys(array_column($rows, 'date'), true);

            return [
                'filters' => $filters, 'rows' => $rows, 'totals' => $this->totals($rows),
                'daily' => $daily, 'categories' => $this->distribution($rows, 'category'),
                'movement_days' => array_intersect_key($daily, $movementDates),
                'methods' => $this->distribution($rows, 'method'),
                'groups' => $this->groups($rows, $filters['grouping']),
            ];
        }, 3);
    }

    public function totals(array $rows): array
    {
        $income = array_sum(array_column($rows, 'income'));
        $expense = array_sum(array_column($rows, 'expense'));
        Money::signedDecimal($income, 'income');
        Money::signedDecimal($expense, 'expense');
        Money::signedDecimal($income - $expense, 'balance');

        return [
            'income' => $income, 'expense' => $expense, 'balance' => $income - $expense,
            'count' => count(array_unique(array_column($rows, 'event_key'))),
        ];
    }

    private function distribution(array $rows, string $field): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $id = $row[$field.'_id'] ?? 'none';
            $groups[$id] ??= ['label' => $row[$field], 'income' => 0, 'expense' => 0];
            $groups[$id]['income'] += $row['income'];
            $groups[$id]['expense'] += $row['expense'];
        }

        return array_values($groups);
    }

    private function groups(array $rows, string $grouping): array
    {
        $fields = $grouping === 'date' ? ['account', 'date'] : ['account', $grouping, 'date'];

        return $this->groupLevel($rows, $fields);
    }

    private function groupLevel(array $rows, array $fields): array
    {
        $field = array_shift($fields);
        $buckets = [];
        foreach ($rows as $row) {
            $key = $field === 'date' ? $row['date'] : ($row[$field.'_id'] ?? 'none');
            $buckets[$key]['label'] = $row[$field];
            $buckets[$key]['rows'][] = $row;
        }
        uasort($buckets, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));
        foreach ($buckets as &$bucket) {
            $bucket['totals'] = $this->totals($bucket['rows']);
            $bucket['children'] = $fields ? $this->groupLevel($bucket['rows'], $fields) : [];
        }

        return array_values($buckets);
    }

    private function identity(): int
    {
        if (Auth::id() === null) {
            throw new AuthenticationException;
        }

        return (int) Auth::id();
    }
}
