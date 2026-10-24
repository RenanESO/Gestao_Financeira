{{--
    Linha com atalhos de arraste. $right/$left: ['label', 'icon', 'tone', 'method', 'args'] ou null.
    Tons: success, warning, error, info, neutral (acao indisponível; o servidor informa o motivo).
--}}
@props(['right' => null, 'left' => null, 'disabled' => false])
<li {{ $attributes->class('swipe-row') }}
    x-data="swipeRow({{ \Illuminate\Support\Js::from(['right' => $right, 'left' => $left, 'disabled' => (bool) $disabled]) }})"
    :class="{ 'is-dragging': dragging, 'is-busy': busy, 'is-armed': armed }">
    @foreach(['right' => $right, 'left' => $left] as $side => $action)
        @if($action)
            <div class="swipe-row__action swipe-row__action--{{ $side }} swipe-tone--{{ $action['tone'] }}" x-show="direction === '{{ $side }}'" x-cloak aria-hidden="true">
                <x-mary-icon :name="$action['icon']" class="size-5 shrink-0" />
                <span class="grid leading-tight">
                    <strong>{{ $action['label'] }}</strong>
                    <small x-text="armed ? 'Solte para continuar' : 'Continue arrastando'"></small>
                </span>
            </div>
        @endif
    @endforeach
    <div class="swipe-row__content" :style="offset ? `transform: translateX(${offset}px)` : ''"
        @pointerdown="start($event)" @pointermove="move($event)" @pointerup="end($event)" @pointercancel="cancel()"
        @click.capture="guardClick($event)" @dragstart.prevent @keydown.escape.window="dragging && cancel()">
        {{ $slot }}
    </div>
    <div class="swipe-row__busy" x-show="busy" x-cloak role="status">
        <span class="loading loading-spinner loading-sm" aria-hidden="true"></span>Processando operação...
    </div>
</li>
