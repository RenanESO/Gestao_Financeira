<?php

namespace App\Http\Controllers;

use App\Livewire\Catalog;
use App\Models\FinancialAccount;
use App\Models\Notification;
use App\Models\Receipt;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Services\FinancialAccountService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function dashboard(FinancialAccountService $balances): View
    {
        $accounts = FinancialAccount::orderBy('name')->limit(6)->get()->map(fn ($account) => [
            'name' => $account->name,
            'active' => $account->active,
            'balance' => $balances->balance($account)['balance'],
        ]);

        return view('dashboard', [
            'accounts' => $accounts,
            'receivables' => Title::where('title_type', 'receivable')->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'payables' => Title::where('title_type', 'payable')->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'unread' => Notification::unread()->count(),
            'upcoming' => TitleInstallment::whereIn('status', ['pending', 'overdue'])
                ->whereHas('title', fn ($query) => $query->whereIn('status', ['pending', 'partial', 'overdue']))
                ->with('title')->orderBy('due_date')->limit(6)->get(),
        ]);
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
}
