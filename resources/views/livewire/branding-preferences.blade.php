<section class="panel mt-6 max-w-2xl">
    <h2 class="text-lg font-semibold">Personalização do sistema</h2>
    <p class="muted mt-2">Nome, ícone e cores somente para o seu ambiente. Outros usuários não serão afetados.</p>
    <x-feedback />
    <form wire:submit="save" class="mt-5 space-y-5">
        <label class="field">Nome do sistema
            <input class="input w-full" wire:model="name" maxlength="80" required>
        </label>
        <label class="flex items-center gap-3">
            <input class="checkbox checkbox-primary" type="checkbox" wire:model="showName">
            Exibir o nome junto ao ícone
        </label>
        <div class="space-y-3">
            @if(auth()->user()->branding['icon_path'] ?? null)
                <img src="{{ route('branding.icon') }}" alt="Ícone atual do sistema" class="size-16 object-contain">
            @endif
            <label class="field">Ícone do sistema
                <input class="file-input w-full" type="file" wire:model="icon" accept="image/png,image/jpeg,image/webp">
                <span class="muted">PNG, JPG ou WebP, até 2 MB e 2048 × 2048 pixels. SVG não é aceito.</span>
            </label>
            <p wire:loading wire:target="icon" class="muted">Enviando ícone...</p>
            <label class="flex items-center gap-3"><input class="checkbox" type="checkbox" wire:model="removeIcon">Remover ícone atual</label>
        </div>
        <div class="border-t border-base-content/10 pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-semibold">Cores do tema</h3>
                <button type="button" class="btn btn-ghost btn-sm" wire:click="resetColors">Restaurar cores do tema</button>
            </div>
            <p class="muted mt-2">Os campos mostram as cores atuais. Limpe um campo para usar a cor original do tema claro/escuro. Alterações são aplicadas ao salvar.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach($colorLabels as $key => $label)
                    <label class="field" wire:key="brand-color-{{ $key }}">{{ $label }}
                        <div class="flex items-center gap-2" x-data="themeColor(@js($key), $wire.entangle(@js('colors.'.$key)))">
                            <input type="color" class="size-11 shrink-0 cursor-pointer rounded border border-base-content/20" :value="displayedColor" @input="color = $event.target.value" aria-label="Selecionar cor {{ mb_strtolower($label) }}">
                            <input class="input w-full" :value="color" @input="color = $event.target.value" maxlength="7" :placeholder="original" aria-label="Código hexadecimal: {{ $label }}">
                        </div>
                    </label>
                @endforeach
            </div>
            <p class="muted mt-3">Mantenha contraste entre fundo e texto. As cores semânticas de gráficos e relatórios financeiros são preservadas.</p>
        </div>
        <x-mary-button type="submit" label="Salvar personalização" class="btn-primary" spinner="save" wire:loading.attr="disabled" wire:target="icon,save" />
    </form>
</section>
