@props(['color' => 'gray'])
<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full bg-{$color}-100 px-2.5 py-0.5 text-xs font-semibold text-{$color}-800"]) }}>{{ $slot }}</span>
