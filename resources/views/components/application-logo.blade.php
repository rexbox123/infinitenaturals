@props(['variant' => 'dark'])
@php
    $logo = setting('store.logo_path');
    $lightLogo = setting('store.logo_light_path');
    $light = $variant === 'light';
    $src = $light ? ($lightLogo ?: $logo) : $logo;
@endphp
@if ($src)
    <img src="{{ Storage::disk('public')->url($src) }}" alt="{{ setting('store.name') }}"
         {{ $attributes->merge(['class' => 'h-14 w-auto'.($light && ! $lightLogo ? ' brightness-0 invert' : '')]) }}>
@else
    {{-- Script wordmark in the style of the current logo, until the owner uploads it in Admin → Settings. --}}
    <span {{ $attributes->merge(['class' => 'logo-wordmark '.($light ? 'text-white' : 'text-umber-700')]) }}>{{ setting('store.name') }}</span>
@endif
