<div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-stone-200 text-sm']) }}>
        @isset($head)
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wider text-stone-600">{{ $head }}</thead>
        @endisset
        <tbody class="divide-y divide-stone-100">{{ $slot }}</tbody>
    </table>
</div>
