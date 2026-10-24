<x-layout title="Seu financeiro em um só lugar">
    <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-14 md:grid-cols-2 md:py-24">
        <div>
            <p class="eyebrow mb-5">Clareza para o seu dia a dia</p>
            <h1 class="text-4xl font-bold leading-tight tracking-tight md:text-6xl">Mais controle.<br><span class="text-primary">Menos complicação.</span></h1>
            <p class="mt-6 max-w-md text-lg text-base-content/60">Organize receitas, despesas e contas. Acompanhe seus compromissos e mantenha o histórico financeiro em um só lugar.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Abrir meu painel</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary">Criar minha conta</a>
                    <a href="{{ route('login') }}" class="btn btn-outline">Já tenho uma conta</a>
                @endauth
            </div>
            <p class="mt-5 muted">Você começa sem saldos ou lançamentos fictícios.</p>
        </div>
        <div class="panel relative">
            <div class="mb-8 flex items-center justify-between"><span class="font-semibold">Tudo conectado</span><span class="badge badge-primary badge-outline">MoneyControl</span></div>
            @foreach([
                ['o-building-library', 'Suas contas', 'Saldo de abertura e movimentos realizados'],
                ['o-calendar-days', 'Seus compromissos', 'Receitas e despesas com parcelamento'],
                ['o-document-check', 'Seu histórico', 'Baixas, estornos e recibos preservados'],
            ] as [$icon, $heading, $description])
                <div class="mb-5 flex gap-4 rounded-xl bg-base-200 p-5">
                    <x-mary-icon :name="$icon" class="h-6 w-6 shrink-0 text-primary" />
                    <div><h2 class="font-semibold">{{ $heading }}</h2><p class="mt-1 muted">{{ $description }}</p></div>
                </div>
            @endforeach
            <p class="muted">Um ponto de partida para organizar seu dinheiro com cuidado.</p>
        </div>
    </section>
</x-layout>
