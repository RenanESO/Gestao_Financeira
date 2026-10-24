<x-layout title="Recuperar senha">
    <section class="mx-auto max-w-md px-5 py-10"><div class="panel">
        <h1 class="page-title mb-3">Recuperar senha</h1>
        <p class="muted mb-7">Informe seu e-mail para solicitar um link de redefinição.</p>
        <x-feedback />
        <form action="{{ route('password.email') }}" method="POST" class="space-y-5">
            @csrf
            <label class="field">E-mail<input class="input w-full" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
            <button class="btn btn-primary w-full">Solicitar link</button>
        </form>
        <a href="{{ route('login') }}" class="link mt-6 inline-block text-sm">Voltar para o login</a>
    </div></section>
</x-layout>
