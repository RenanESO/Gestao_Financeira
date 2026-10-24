@props(['title' => 'MoneyControl'])
<!DOCTYPE html>
<html lang="pt-BR" data-theme="{{ auth()->user()?->theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | MoneyControl</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div x-data="{ menu: false }">
    @auth
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-base-content/10 bg-base-100 px-5 py-4 lg:hidden">
            <a href="{{ route('dashboard') }}" class="font-bold text-xl">Money<span class="text-primary">Control</span></a>
            <button class="btn btn-ghost btn-sm" @click="menu = !menu" :aria-expanded="menu" aria-controls="sidebar">Menu</button>
        </header>
        <div x-cloak x-show="menu" class="fixed inset-0 z-30 bg-black/60 lg:hidden" @click="menu = false"></div>
        <aside id="sidebar" :class="menu ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-base-content/10 bg-base-100 transition-transform lg:translate-x-0 print:hidden">
            <a href="{{ route('dashboard') }}" class="px-7 pt-8 pb-2 text-2xl font-bold">Money<span class="text-primary">Control</span></a>
            <p class="px-7 pb-8 muted">Seu financeiro, organizado.</p>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3" aria-label="Navegação principal">
                @foreach([
                    ['dashboard', [], 'Visão geral', 'o-squares-2x2'],
                    ['financial.dashboard', [], 'Dashboard financeira', 'o-chart-bar'],
                    ['income', [], 'Receitas', 'o-arrow-trending-up'],
                    ['expenses', [], 'Despesas', 'o-arrow-trending-down'],
                    ['catalog', ['module' => 'contas'], 'Contas financeiras', 'o-building-library'],
                    ['catalog', ['module' => 'contatos'], 'Contatos', 'o-users'],
                    ['catalog', ['module' => 'categorias'], 'Categorias', 'o-tag'],
                    ['catalog', ['module' => 'formas'], 'Formas de pagamento', 'o-credit-card'],
                    ['receipts.index', [], 'Recibos', 'o-document-text'],
                    ['notifications.index', [], 'Notificações', 'o-bell'],
                    ['preferences', [], 'Preferências', 'o-cog-6-tooth'],
                ] as [$name, $params, $label, $icon])
                    @php($active = request()->routeIs($name) && (!isset($params['module']) || request()->route('module') === $params['module']))
                    <a href="{{ route($name, $params) }}" class="nav-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                        <x-mary-icon :name="$icon" class="h-5 w-5 shrink-0" />{{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="m-4 border-t border-base-content/10 pt-4">
                <p class="truncate px-3 text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="truncate px-3 muted">{{ auth()->user()->email }}</p>
                <form action="{{ route('logout') }}" method="POST" class="mt-3">@csrf<button class="btn btn-ghost btn-sm w-full">Sair da conta</button></form>
            </div>
        </aside>
        <main class="mx-auto max-w-screen-2xl px-5 py-8 lg:ml-64 lg:px-10 print:ml-0 print:max-w-none print:p-0">
            <div class="mb-8 flex items-center justify-between gap-3 print:hidden">
                <p class="eyebrow">Espaço financeiro</p>
                <a class="btn btn-ghost btn-sm" href="{{ route('preferences') }}">Preferências</a>
            </div>
            {{ $slot }}
        </main>
    @else
        <header class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-6">
            <a href="{{ route('home') }}" class="text-2xl font-bold">Money<span class="text-primary">Control</span></a>
            <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Acessar</a>
        </header>
        <main>{{ $slot }}</main>
        <footer class="mx-auto max-w-6xl px-5 py-8 text-center muted">MoneyControl · Controle financeiro pessoal</footer>
    @endauth
</div>
@livewireScripts
</body>
</html>
