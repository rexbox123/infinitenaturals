<x-store-layout :title="isset($header) ? strip_tags($header) : null" noindex>
    <div class="container-store py-10">
        @isset($header)
            <h1 class="mb-6 font-serif text-3xl">{{ $header }}</h1>
        @endisset
        {{ $slot }}
    </div>
</x-store-layout>
