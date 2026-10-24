<div>
    <h1 class="page-title">Preferências</h1>
    <p class="muted mt-3 mb-7">Defina como a sua área financeira deve se comportar.</p>
    <x-feedback />

    <form wire:submit="save" class="panel max-w-2xl space-y-6">
        <div>
            <h2 class="text-lg font-semibold">Aparência</h2>
            <p class="muted mt-1">A escolha será aplicada nas próximas páginas abertas.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach(['dark' => 'Escuro', 'light' => 'Claro'] as $value => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-base-content/10 p-4 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                        <input class="radio radio-primary radio-sm" type="radio" wire:model="theme" value="{{ $value }}">
                        <span class="font-medium">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="border-t border-base-content/10 pt-6">
            <h2 class="text-lg font-semibold">Alertas de vencimento</h2>
            <label class="field mt-4 max-w-xs">Dias de antecedência
                <input class="input w-full" type="number" min="0" max="365" wire:model="notificationDaysBefore" required>
                <span class="muted">Use 0 para alertar somente no dia do vencimento.</span>
            </label>
        </div>

        <x-mary-button type="submit" label="Salvar preferências" class="btn-primary" spinner="save" />
    </form>
</div>
