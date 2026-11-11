@php($logo = setting('store.logo_path'))
@if ($logo)
    <img src="{{ Storage::disk('public')->url($logo) }}" alt="{{ setting('store.name') }}" {{ $attributes->merge(['class' => 'h-10 w-auto']) }}>
@else
    {{-- Typographic wordmark until the owner uploads the logo in Admin → Settings. --}}
    <span {{ $attributes->merge(['class' => 'inline-flex flex-col items-center leading-none']) }}>
        <span class="font-serif text-xl tracking-[0.18em] text-ink sm:text-2xl">INFINITE</span>
        <span class="mt-1 text-[0.6rem] font-semibold tracking-[0.55em] text-gold-700 sm:text-[0.65rem]">NATURALS</span>
    </span>
@endif
