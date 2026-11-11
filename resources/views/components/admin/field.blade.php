@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php($id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name)))
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-red-700"> *</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $attributes->get('rows', 4) }}" class="input" @required($required)>{{ old(str_replace(['[', ']'], ['.', ''], $name), $value) }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ old(str_replace(['[', ']'], ['.', ''], $name), $value) }}" class="input" @required($required) {{ $attributes->except(['class', 'id', 'rows']) }}>
    @endif
    @if ($help)<p class="mt-1 text-xs text-stone-500">{{ $help }}</p>@endif
    @error(str_replace(['[', ']'], ['.', ''], $name))<p class="field-error">{{ $message }}</p>@enderror
</div>
