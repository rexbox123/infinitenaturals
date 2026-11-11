@props(['title' => null, 'actions' => null])
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-xl border border-stone-200 bg-white shadow-sm']) }}>
    @if ($title || $actions)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-3">
            @if ($title)<h2 class="font-sans text-sm font-semibold uppercase tracking-wider text-stone-700">{{ $title }}</h2>@endif
            {{ $actions }}
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</section>
