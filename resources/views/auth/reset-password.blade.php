<x-layout title="Redefinir senha">
    <section class="mx-auto max-w-md px-5 py-10"><div class="panel">
        <h1 class="page-title mb-6">Nova senha</h1>
        <x-feedback />
        <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="field">E-mail<input class="input w-full" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"></label>
            <label class="field">Nova senha<input class="input w-full" name="password" type="password" required minlength="8" autocomplete="new-password"><span class="muted">Mínimo de 8 caracteres, com letras e números.</span></label>
            <label class="field">Confirmar senha<input class="input w-full" name="password_confirmation" type="password" required autocomplete="new-password"></label>
            <button class="btn btn-primary w-full">Redefinir senha</button>
        </form>
    </div></section>
</x-layout>
