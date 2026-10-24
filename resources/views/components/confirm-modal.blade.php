{{--
    Modal de confirmação compartilhado (MaryUI). Requer o trait App\Livewire\Concerns\ConfirmsOperations
    no componente Livewire; textos, icones e botoes vem de App\Support\Feedback::CONFIRMATIONS.
--}}
@props(['confirmation' => null])
@php
    $config = $confirmation ? \App\Support\Feedback::confirmation($confirmation['operation']) : null;
    $button = ['success' => 'btn-success', 'warning' => 'btn-warning', 'error' => 'btn-error'][$config['tone'] ?? 'error'] ?? 'btn-primary';
@endphp
<x-mary-modal wire:model="confirming" box-class="max-w-md" aria-labelledby="confirm-modal-title" aria-describedby="confirm-modal-message">
    @if($config)
        <div class="confirm-modal">
            <span class="confirm-modal__icon swipe-tone--{{ $config['tone'] }}" aria-hidden="true"><x-mary-icon :name="$config['icon']" class="size-6" /></span>
            <div class="min-w-0">
                <h2 id="confirm-modal-title" class="text-lg font-semibold">{{ $config['title'] }}</h2>
                <p id="confirm-modal-message" class="mt-2 text-sm text-base-content/70">{{ $config['message'] }}</p>
                @if($confirmation['subject'])
                    <p class="confirm-modal__subject">{{ $confirmation['subject'] }}</p>
                @endif
            </div>
        </div>
        <div class="modal-action flex-wrap">
            <button type="button" class="btn btn-ghost" wire:click="dismissConfirmation" wire:loading.attr="disabled" wire:target="confirmOperation">
                <x-mary-icon name="o-x-mark" class="size-4" aria-hidden="true" />Cancelar
            </button>
            <button type="button" class="btn {{ $button }}" wire:click="confirmOperation" wire:loading.attr="disabled" wire:target="confirmOperation">
                <span wire:loading.remove wire:target="confirmOperation"><x-mary-icon :name="$config['icon']" class="size-4" aria-hidden="true" /></span>
                <span wire:loading wire:target="confirmOperation" class="loading loading-spinner loading-sm" aria-hidden="true"></span>
                {{ $config['confirm'] }}
            </button>
        </div>
        <p wire:loading wire:target="confirmOperation" role="status" class="mt-3 text-right text-sm text-base-content/60">Processando operação...</p>
    @endif
</x-mary-modal>
