<x-layout title="Visao geral">
    <p class="muted mb-2">{{ now()->format('d/m/Y') }}</p>
    <h1 class="page-title">Ola, {{ auth()->user()->name }}.</h1>
    <p class="mt-3 mb-8 text-base-content/60">Vamos organizar seus proximos passos.</p>
    <x-feedback />
    <div class="grid gap-5 md:grid-cols-3">
        @foreach([['income', 'Receitas em aberto', $receivables, 'o-arrow-trending-up'], ['expenses', 'Despesas em aberto', $payables, 'o-arrow-trending-down'], ['notifications.index', 'Alertas nao lidos', $unread, 'o-bell']] as [$route, $label, $count, $icon])
            <a href="{{ route($route) }}" class="panel transition-colors hover:border-primary/40">
                <div class="flex items-center justify-between"><p class="muted">{{ $label }}</p><x-mary-icon :name="$icon" class="h-5 w-5 text-primary" /></div>
                <p class="mt-4 text-4xl font-bold">{{ $count }}</p>
                <p class="muted mt-3">Abrir detalhes &rarr;</p>
            </a>
        @endforeach
    </div>
    <div class="my-7 flex flex-wrap gap-3">
        <a class="btn btn-primary" href="{{ route('income') }}">Nova receita</a>
        <a class="btn btn-outline" href="{{ route('expenses') }}">Nova despesa</a>
        <a class="btn btn-ghost" href="{{ route('catalog', 'contas') }}">Gerenciar contas</a>
    </div>
    <section class="mb-7">
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="text-lg font-semibold">Resumo de {{ $period }}</h2>
            <p class="muted">Valores realizados consideram estornos no periodo.</p>
        </div>
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['A receber em aberto', $periodDue['receivable'], 'o-arrow-trending-up'],
                ['A pagar em aberto', $periodDue['payable'], 'o-arrow-trending-down'],
                ['Recebido no periodo', $periodSettled['receivable'], 'o-banknotes'],
                ['Pago no periodo', $periodSettled['payable'], 'o-credit-card'],
            ] as [$label, $value, $icon])
                <div class="panel">
                    <div class="flex items-center justify-between"><p class="muted">{{ $label }}</p><x-mary-icon :name="$icon" class="h-5 w-5 text-primary" /></div>
                    <p class="mt-4 text-2xl font-bold">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($value)) }}</p>
                </div>
            @endforeach
        </div>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <h2 class="text-lg font-semibold">Proximos compromissos</h2>
            <p class="muted mt-1 mb-5">Saldo restante das parcelas em aberto, incluindo vencidas.</p>
            @forelse($upcoming as $installment)
                <a href="{{ route('titles.show', $installment['title']) }}" class="flex items-center justify-between gap-3 border-t border-base-content/10 py-4">
                    <div class="min-w-0"><p class="truncate font-medium">{{ $installment['title']->description }}</p><p class="muted">{{ $installment['due_date']->format('d/m/Y') }} · Parcela {{ $installment['installment_number'] }}</p></div>
                    <span class="shrink-0 text-sm">{{ \App\Support\Display::money($installment['remaining']) }}</span>
                </a>
            @empty
                <div class="rounded-xl bg-base-200 p-6"><p class="font-medium">Nenhum compromisso cadastrado.</p><p class="muted mt-2">Comece adicionando sua primeira receita ou despesa.</p></div>
            @endforelse
        </section>
        <section class="panel">
            <h2 class="text-lg font-semibold">Suas contas</h2>
            <p class="muted mt-1 mb-5">Saldo realizado ate hoje. Exibindo ate 6 contas.</p>
            @forelse($accounts as $account)
                <div class="flex items-center justify-between gap-3 border-t border-base-content/10 py-4">
                    <div><p class="font-medium">{{ $account['name'] }}</p><p class="muted">{{ $account['active'] ? 'Ativa' : 'Inativa, com historico' }}</p></div>
                    <span class="font-semibold">{{ \App\Support\Display::money($account['balance']) }}</span>
                </div>
            @empty
                <div class="rounded-xl bg-base-200 p-6"><p class="font-medium">Cadastre sua primeira conta.</p><p class="muted mt-2 mb-4">Informe o saldo inicial real para comecar.</p><a href="{{ route('catalog', 'contas') }}" class="btn btn-primary btn-sm">Cadastrar conta</a></div>
            @endforelse
        </section>
    </div>
</x-layout>
