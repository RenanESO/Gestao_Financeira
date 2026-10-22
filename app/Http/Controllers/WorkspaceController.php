<?php

namespace App\Http\Controllers;

use App\Livewire\Catalog;
use App\Models\FinancialAccount;
use App\Models\Notification;
use App\Models\Receipt;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Services\FinancialAccountService;
use App\Services\NotificationService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function dashboard(FinancialAccountService $balances): View
    {
        $start = CarbonImmutable::now()->startOfMonth()->toDateString();
        $end = CarbonImmutable::now()->endOfMonth()->toDateString();
        $accounts = FinancialAccount::orderBy('name')->limit(6)->get()->map(fn ($account) => [
            'name' => $account->name,
            'active' => $account->active,
            'balance' => $balances->balance($account)['balance'],
        ]);
        $upcoming = TitleInstallment::whereIn('status', ['pending', 'overdue'])
            ->whereHas('title', fn ($query) => $query->whereIn('status', ['pending', 'partial', 'overdue']))
            ->with(['title', 'settlements.reversal'])->orderBy('due_date')->limit(6)->get()
            ->map(function (TitleInstallment $installment) {
                $settled = $installment->settlements
                    ->filter(fn (TitleSettlement $settlement) => $settlement->reversal === null)
                    ->sum(fn (TitleSettlement $settlement) => Money::cents($settlement->original_value));

                return [
                    'title' => $installment->title,
                    'due_date' => $installment->due_date,
                    'installment_number' => $installment->installment_number,
                    'remaining' => Money::decimal(Money::cents($installment->value) - $settled),
                ];
            });
        $dueThisMonth = TitleInstallment::whereBetween('due_date', [$start, $end])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->with(['title', 'settlements.reversal'])->get();
        $settlements = TitleSettlement::with(['title', 'reversal'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('settlement_date', [$start, $end])
                    ->orWhereHas('reversal', fn ($reversal) => $reversal->whereBetween('reversal_date', [$start, $end]));
            })->get();

        return view('dashboard', [
            'accounts' => $accounts,
            'receivables' => Title::where('title_type', 'receivable')->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'payables' => Title::where('title_type', 'payable')->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'unread' => Notification::unread()->count(),
            'upcoming' => $upcoming,
            'period' => CarbonImmutable::now()->format('m/Y'),
            'periodDue' => $this->totalsByType($dueThisMonth, fn (TitleInstallment $installment) => $this->remaining($installment)),
            'periodSettled' => $this->settlementsByType($settlements, $start, $end),
        ]);
    }

    private function totalsByType($installments, callable $value): array
    {
        return $installments->reduce(function (array $totals, TitleInstallment $installment) use ($value) {
            $type = $installment->title->title_type;
            $totals[$type] += $value($installment);

            return $totals;
        }, ['receivable' => 0, 'payable' => 0]);
    }

    private function settlementsByType($settlements, string $start, string $end): array
    {
        return $settlements->reduce(function (array $totals, TitleSettlement $settlement) use ($start, $end) {
            $type = $settlement->title->title_type;
            $value = Money::cents($settlement->total_value);

            if ($settlement->settlement_date->betweenIncluded($start, $end)) {
                $totals[$type] += $value;
            }
            if ($settlement->reversal?->reversal_date->betweenIncluded($start, $end)) {
                $totals[$type] -= $value;
            }

            return $totals;
        }, ['receivable' => 0, 'payable' => 0]);
    }

    private function remaining(TitleInstallment $installment): int
    {
        $settled = $installment->settlements
            ->filter(fn (TitleSettlement $settlement) => $settlement->reversal === null)
            ->sum(fn (TitleSettlement $settlement) => Money::cents($settlement->original_value));

        return Money::cents($installment->value) - $settled;
    }

    public function catalog(string $module): View
    {
        abort_unless(isset(Catalog::MODULES[$module]), 404);

        return view('catalog', compact('module'));
    }

    public function titles(string $type): View
    {
        return view('titles', compact('type'));
    }

    public function title(Title $title): View
    {
        Gate::authorize('view', $title);

        return view('title', compact('title'));
    }

    public function receipts(): View
    {
        return view('receipts', ['receipts' => Receipt::orderByDesc('id')->paginate(15)]);
    }

    public function notifications(): View
    {
        return view('notifications', ['notifications' => Notification::orderByDesc('id')->paginate(15)]);
    }

    public function preferences(): View
    {
        return view('preferences');
    }

    public function read(Notification $notification, NotificationService $service): RedirectResponse
    {
        $service->markRead($notification);

        return back()->with('status', 'Notificacao marcada como lida.');
    }

    public function readAll(NotificationService $service): RedirectResponse
    {
        $service->markAllRead();

        return back()->with('status', 'Notificacoes marcadas como lidas.');
    }

    public function refreshNotifications(NotificationService $service): RedirectResponse
    {
        $created = $service->generateFor(auth()->user());

        return back()->with('status', $created === 0
            ? 'Nenhum novo alerta encontrado para a antecedencia configurada.'
            : $created.' alerta(s) atualizado(s).');
    }
}
