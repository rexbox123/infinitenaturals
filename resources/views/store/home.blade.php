<x-store-layout :description="setting('home.intro_body')">
    {{-- Hero (Admin → Banners → Homepage hero) --}}
    @if ($heroes->isNotEmpty())
        <section x-data="{ i: 0, n: {{ $heroes->count() }} }" x-init="if (n > 1) setInterval(() => i = (i + 1) % n, 7000)" class="relative overflow-hidden" aria-roledescription="carousel">
            @foreach ($heroes as $hero)
                <div x-show="i === {{ $loop->index }}" @if(! $loop->first) x-cloak @endif x-transition.opacity.duration.700ms
                     class="relative flex min-h-[460px] items-center sm:min-h-[560px] {{ $hero->imageUrl() ? '' : 'bg-gradient-to-br from-cream via-gold-50 to-gold-200' }}">
                    @if ($hero->imageUrl())
                        <img src="{{ $hero->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" @if($loop->first) fetchpriority="high" @endif>
                        <div class="absolute inset-0 bg-gradient-to-r from-ink/70 via-ink/40 to-transparent"></div>
                    @else
                        <svg class="pointer-events-none absolute -right-24 top-1/2 hidden h-[520px] w-[1040px] -translate-y-1/2 text-gold-300/60 md:block" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width=".25" aria-hidden="true"><path d="M4 12c0-4 3-7 7-7 7 0 12 14 19 14 4 0 7-3 7-7s-3-7-7-7c-7 0-12 14-19 14-4 0-7-3-7-7Z" transform="translate(3.5 0)"/></svg>
                    @endif
                    <div class="container-store relative py-16">
                        <div class="max-w-xl {{ $hero->imageUrl() ? 'text-white' : '' }}">
                            @if ($hero->eyebrow)
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] {{ $hero->imageUrl() ? 'text-gold-200' : 'text-gold-700' }}">{{ $hero->eyebrow }}</p>
                            @endif
                            <h1 class="mt-4 font-serif text-4xl leading-tight sm:text-6xl {{ $hero->imageUrl() ? '!text-white' : '' }}">{{ $hero->title }}</h1>
                            @if ($hero->subtitle)
                                <p class="mt-5 text-lg uppercase tracking-[0.2em] {{ $hero->imageUrl() ? 'text-white/90' : 'text-stone-600' }}">{{ $hero->subtitle }}</p>
                            @endif
                            <div class="mt-8 flex flex-wrap gap-3">
                                @if ($hero->button_url)
                                    <a href="{{ $hero->button_url }}" class="btn-primary">{{ $hero->button_label ?: 'Shop now' }}</a>
                                @endif
                                <a href="{{ url('/about-us') }}" class="btn-outline">Our story</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            @if ($heroes->count() > 1)
                <div class="absolute bottom-5 left-1/2 flex -translate-x-1/2 gap-2">
                    @foreach ($heroes as $hero)
                        <button type="button" @click="i = {{ $loop->index }}" class="h-2.5 w-2.5 rounded-full" :class="i === {{ $loop->index }} ? 'bg-umber-700' : 'bg-ink/25'" aria-label="Show slide {{ $loop->iteration }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Store promises — each one is stated in the current shipping/returns policy. --}}
    <section class="border-y border-gold-200 bg-white">
        <ul class="container-store grid grid-cols-2 gap-4 py-5 text-center text-xs font-semibold uppercase tracking-wider text-stone-700 sm:text-sm lg:grid-cols-4">
            <li>Free USPS Standard shipping over $50</li>
            <li>Ships from Ocala, Florida</li>
            <li>30-day returns on unopened items</li>
            <li>Secure online checkout</li>
        </ul>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="container-store py-16">
            <div class="text-center">
                <p class="eyebrow">Shop by category</p>
                <h2 class="section-title mt-2">Find your ritual</h2>
                <div class="divider-gold"></div>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($categories as $cat)
                    @php($sample = $cat->imageUrl() ? null : $cat->products()->visible()->with('primaryImage')->orderByDesc('is_featured')->first())
                    <a href="{{ route('category.show', $cat) }}" class="group relative block aspect-square overflow-hidden rounded-xl bg-gold-50">
                        @if ($cat->imageUrl())
                            <img src="{{ $cat->imageUrl() }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        @elseif ($sample)
                            <x-store.product-image :product="$sample" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                        @endif
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/80 to-transparent p-4 pt-12">
                            <h3 class="font-serif text-lg !text-white sm:text-xl">{{ $cat->name }}</h3>
                            <p class="text-xs uppercase tracking-wider text-gold-200">{{ $cat->products_count }} {{ Str::plural('product', $cat->products_count) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured products --}}
    @if ($featured->isNotEmpty())
        <section class="bg-cream py-16">
            <div class="container-store">
                <div class="text-center">
                    <p class="eyebrow">Best loved</p>
                    <h2 class="section-title mt-2">{{ setting('home.featured_title') }}</h2>
                    <div class="divider-gold"></div>
                    @if (setting('home.featured_subtitle'))
                        <p class="mx-auto max-w-xl text-stone-600">{{ setting('home.featured_subtitle') }}</p>
                    @endif
                </div>
                <div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 {{ $featured->count() === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }}">
                    @foreach ($featured as $product)
                        <x-store.product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-10 text-center"><a href="{{ route('shop') }}" class="btn-primary">Shop all products</a></div>
            </div>
        </section>
    @endif

    {{-- Promotions (Admin → Banners → Homepage promotion) --}}
    @foreach ($promos as $promo)
        <section class="container-store py-12">
            <div class="grid overflow-hidden rounded-2xl bg-ink md:grid-cols-2">
                <div class="relative min-h-[220px] {{ $promo->imageUrl() ? '' : 'bg-gradient-to-br from-gold-300 to-gold-600' }}">
                    @if ($promo->imageUrl())
                        <img src="{{ $promo->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                    @else
                        <svg class="absolute inset-0 m-auto h-24 w-48 text-white/50" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="1" aria-hidden="true"><path d="M4 12c0-4 3-7 7-7 7 0 12 14 19 14 4 0 7-3 7-7s-3-7-7-7c-7 0-12 14-19 14-4 0-7-3-7-7Z" transform="translate(3.5 0)"/></svg>
                    @endif
                </div>
                <div class="p-8 sm:p-12">
                    @if ($promo->eyebrow)<p class="eyebrow text-gold-300">{{ $promo->eyebrow }}</p>@endif
                    <h2 class="font-serif text-3xl !text-white">{{ $promo->title }}</h2>
                    @if ($promo->subtitle)<p class="mt-4 text-stone-300">{{ $promo->subtitle }}</p>@endif
                    @if ($promo->button_url)
                        <a href="{{ $promo->button_url }}" class="btn-gold mt-6">{{ $promo->button_label ?: 'Shop now' }}</a>
                    @endif
                </div>
            </div>
        </section>
    @endforeach

    {{-- Brand story --}}
    <section class="container-store grid gap-12 py-16 lg:grid-cols-2">
        @if (setting('home.intro_body'))
            <div>
                <p class="eyebrow">Welcome to {{ setting('store.name') }}</p>
                <h2 class="section-title mt-2">{{ setting('home.intro_title') }}</h2>
                <div class="my-4 h-px w-16 bg-gold-400"></div>
                <p class="leading-relaxed text-stone-700">{{ setting('home.intro_body') }}</p>
            </div>
        @endif
        @if (setting('home.mission_body'))
            <div class="rounded-2xl border border-gold-200 bg-gold-50 p-8 sm:p-10">
                <h2 class="section-title">{{ setting('home.mission_title') }}</h2>
                <div class="my-4 h-px w-16 bg-gold-400"></div>
                <p class="text-lg leading-relaxed text-stone-700">{{ setting('home.mission_body') }}</p>
                <a href="{{ url('/about-us') }}" class="link mt-6 inline-block text-sm font-semibold uppercase tracking-wider">Read our story</a>
            </div>
        @endif
    </section>

    {{-- Contact --}}
    <section class="bg-cream py-16">
        <div class="container-store grid gap-10 lg:grid-cols-[2fr_1fr] lg:items-center">
            <div>
                <h2 class="section-title">{{ setting('home.contact_title') }}</h2>
                <div class="my-4 h-px w-16 bg-gold-400"></div>
                @if (setting('home.contact_body'))<p class="max-w-2xl leading-relaxed text-stone-700">{{ setting('home.contact_body') }}</p>@endif
            </div>
            <div class="space-y-3 text-sm">
                @if (setting('store.phone'))<p><span class="font-semibold text-ink">Phone:</span> <a class="link" href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store.phone')) }}">{{ setting('store.phone') }}</a></p>@endif
                @if (setting('store.email'))<p><span class="font-semibold text-ink">Email:</span> <a class="link" href="mailto:{{ setting('store.email') }}">{{ setting('store.email') }}</a></p>@endif
                @if (setting('store.address'))<p><span class="font-semibold text-ink">Location:</span> {{ setting('store.address') }}</p>@endif
                <a href="{{ route('contact') }}" class="btn-primary mt-2">Send us a message</a>
            </div>
        </div>
    </section>
</x-store-layout>
