{{--
    Componente unico de mensagens: notificação flutuante (App\Support\Feedback) e resumo de erros.
    Mensagens antigas em session('status') sao exibidas como informacao.
--}}
@php
    $feedback = session('feedback');
    if (! $feedback && session('status')) {
        $feedback = \App\Support\Feedback::make('info', session('status'));
    }
    $meta = $feedback ? \App\Support\Feedback::kind($feedback['kind']) : null;
@endphp
@if($feedback)
    <div class="feedback-region" wire:key="feedback-{{ $feedback['id'] }}">
        <div class="feedback feedback--{{ $meta['tone'] }}" role="{{ $meta['role'] }}" aria-live="{{ $meta['role'] === 'alert' ? 'assertive' : 'polite' }}"
            x-data="{ show: true }" x-show="show" x-transition.opacity.duration.200ms
            x-init="{{ $meta['timeout'] }} && setTimeout(() => show = false, {{ $meta['timeout'] }})">
            <x-mary-icon :name="$meta['icon']" class="feedback__icon" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="feedback__message">{{ $feedback['message'] }}</p>
                @if($feedback['detail'])<p class="feedback__detail">{{ $feedback['detail'] }}</p>@endif
            </div>
            <button type="button" class="feedback__close" @click="show = false" aria-label="Fechar mensagem"><x-mary-icon name="o-x-mark" class="size-4" aria-hidden="true" /></button>
        </div>
    </div>
@endif
{{-- Erro já exibido na notificação flutuante não e repetido no resumo. --}}
@if($errors->any() && ($meta['tone'] ?? null) !== 'error')
    <div class="feedback feedback--error mb-5 scroll-mt-24" role="alert" x-data="{ show: true }" x-show="show" wire:key="feedback-errors-{{ md5(implode('|', $errors->all())) }}"
        x-init="$el.getBoundingClientRect().top < 0 && $el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
        <x-mary-icon name="o-x-circle" class="feedback__icon" aria-hidden="true" />
        <div class="min-w-0 flex-1">
            <p class="feedback__message">Não foi possível concluir a operação. Confira os campos informados:</p>
            <ul class="feedback__detail list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        <button type="button" class="feedback__close" @click="show = false" aria-label="Fechar mensagem"><x-mary-icon name="o-x-mark" class="size-4" aria-hidden="true" /></button>
    </div>
@endif
