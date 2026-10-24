<x-layout title="Entrar">
    <section class="mx-auto max-w-md px-5 py-10">
        <div class="panel">
            <p class="eyebrow mb-3">Bem-vindo de volta</p>
            <h1 class="page-title mb-2">Acesse sua conta</h1>
            <p class="muted mb-7">Seu financeiro está aqui.</p>
            <x-feedback />
            <form action="{{ route('login.store') }}" method="POST" class="space-y-5">
                @csrf
                <label class="field">E-mail<input class="input w-full" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
                <label class="field">Senha<input class="input w-full" name="password" type="password" required autocomplete="current-password"></label>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <label class="flex items-center gap-2"><input class="checkbox checkbox-sm" type="checkbox" name="remember" value="1">Lembrar de mim</label>
                    <a href="{{ route('password.request') }}" class="link link-primary">Esqueci a senha</a>
                </div>
                <button class="btn btn-primary w-full">Entrar</button>
            </form>
            <p class="mt-6 text-center muted">Primeiro acesso? <a href="{{ route('register') }}" class="link link-primary">Crie sua conta</a></p>
        </div>
    </section>
</x-layout>
