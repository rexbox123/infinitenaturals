@props(['name', 'label', 'checked' => false, 'help' => null])
<label {{ $attributes->merge(['class' => 'flex items-start gap-3']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="mt-0.5 rounded border-stone-300 text-umber-700 focus:ring-umber-700">
    <span class="text-sm"><span class="font-semibold text-stone-800">{{ $label }}</span>@if($help)<span class="block text-xs text-stone-500">{{ $help }}</span>@endif</span>
</label>
