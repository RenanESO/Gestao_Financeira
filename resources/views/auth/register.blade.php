<x-layout title="Criar conta">
    <section class="mx-auto max-w-md px-5 py-10">
        <div class="panel">
            <p class="eyebrow mb-3">Seu novo ponto de partida</p>
            <h1 class="page-title mb-2">Crie sua conta</h1>
            <p class="muted mb-7">Categorias e formas padrão prontas. Você cadastra suas contas e seus lançamentos.</p>
            <x-feedback />
            <form action="{{ route('register.store') }}" method="POST" class="space-y-5">
                @csrf
                <label class="field">Nome<input class="input w-full" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" autofocus></label>
                <label class="field">E-mail<input class="input w-full" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username"></label>
                <label class="field">Senha<input class="input w-full" name="password" type="password" required minlength="8" autocomplete="new-password"><span class="muted">Mínimo de 8 caracteres, com letras e números.</span></label>
                <label class="field">Confirmar senha<input class="input w-full" name="password_confirmation" type="password" required autocomplete="new-password"></label>
                <button class="btn btn-primary w-full">Criar conta e começar</button>
            </form>
            <p class="mt-6 text-center muted">Já cadastrado? <a href="{{ route('login') }}" class="link link-primary">Entre na sua conta</a></p>
        </div>
    </section>
</x-layout>
