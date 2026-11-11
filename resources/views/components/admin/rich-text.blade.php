@props(['name', 'label', 'value' => null, 'help' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}_editor" class="label">{{ $label }}</label>
    <input id="{{ $name }}" type="hidden" name="{{ $name }}" value="{{ old($name, $value) }}">
    <trix-editor id="{{ $name }}_editor" input="{{ $name }}" class="prose prose-sm prose-store max-w-none border"></trix-editor>
    @if ($help)<p class="mt-1 text-xs text-stone-500">{{ $help }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
