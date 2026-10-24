@props(['model', 'value' => '', 'class' => 'input w-full', 'required' => false, 'live' => false, 'max' => null])
@php
    $dateValue = is_string($value) ? $value : '';
@endphp
<input
    type="date"
    lang="pt-BR"
    class="{{ $class }}"
    value="{{ $dateValue }}"
    @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif
    @if($required) required @endif
    @if($max) max="{{ $max }}" @endif
>
