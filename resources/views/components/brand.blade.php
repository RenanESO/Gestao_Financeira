@php
    $branding = auth()->user()?->branding ?? [];
    $hasIcon = !empty($branding['icon_path']);
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    @if($hasIcon)
        <img src="{{ route('branding.icon') }}" alt="{{ \App\Support\Branding::name() }}" class="size-9 shrink-0 object-contain">
    @endif
    @if(!$hasIcon || ($branding['show_name'] ?? true))
        @if(\App\Support\Branding::name() === 'MoneyControl')
            <span>Money<span class="text-primary">Control</span></span>
        @else
            <span class="break-words">{{ \App\Support\Branding::name() }}</span>
        @endif
    @endif
</span>
