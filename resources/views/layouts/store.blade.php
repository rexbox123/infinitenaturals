@php
    $storeName = setting('store.name');
    $pageTitle = $title ? "{$title} – {$storeName}" : "{$storeName} – ".setting('store.tagline');
    $metaDescription = $description ?: setting('store.footer_blurb');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ Str::limit(strip_tags($metaDescription), 160) }}">
    @if ($noindex)<meta name="robots" content="noindex">@endif
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:site_name" content="{{ $storeName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($metaDescription), 200) }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($image)<meta property="og:image" content="{{ $image }}">@endif
    <meta name="theme-color" content="#6e1900">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=open-sans:400,600,700|prata:400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

@foreach ($announcements as $announcement)
    <div class="bg-ink px-4 py-2 text-center text-xs tracking-wide text-gold-200 sm:text-sm">
        {{ $announcement->title }}
        @if ($announcement->button_url)
            <a href="{{ $announcement->button_url }}" class="ml-1 underline underline-offset-2 hover:text-white">{{ $announcement->button_label ?: 'Learn more' }}</a>
        @endif
    </div>
@endforeach

<header x-data="{ menu: false, search: false }" class="sticky top-0 z-40 border-b border-gold-200 bg-white">
    <div class="container-store flex h-16 items-center justify-between gap-3 sm:h-20">
        <div class="flex flex-1 items-center gap-2 lg:hidden">
            <button type="button" @click="menu = true" class="-ml-2 rounded-full p-2 text-ink hover:bg-gold-50" aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <button type="button" @click="search = !search" class="rounded-full p-2 text-ink hover:bg-gold-50" aria-label="Search">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            </button>
        </div>

        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ $storeName }} home"><x-application-logo /></a>

        <nav class="hidden flex-1 items-center justify-center gap-5 whitespace-nowrap text-[0.8rem] font-semibold uppercase tracking-wider text-stone-700 lg:flex xl:gap-7" aria-label="Main">
            <a href="{{ route('shop') }}" class="hover:text-umber-700 {{ request()->routeIs('shop') ? 'text-umber-700' : '' }}">Shop all</a>
            @foreach ($navCategories as $navCategory)
                <a href="{{ route('category.show', $navCategory) }}" class="hidden hover:text-umber-700 xl:inline {{ request()->is('product-category/'.$navCategory->slug) ? 'text-umber-700' : '' }}">{{ $navCategory->name }}</a>
            @endforeach
            <a href="{{ url('/about-us') }}" class="hover:text-umber-700">About</a>
            <a href="{{ route('contact') }}" class="hover:text-umber-700">Contact</a>
        </nav>

        <div class="flex flex-1 items-center justify-end gap-1 sm:gap-2 lg:flex-none">
            <form action="{{ route('shop') }}" method="get" role="search" class="relative hidden xl:block">
                <label for="header-search" class="sr-only">Search products</label>
                <input id="header-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search products" class="w-48 rounded-full border-stone-300 py-2 pl-4 pr-9 text-sm focus:border-umber-700 focus:ring-umber-700">
                <svg class="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            </form>
            <button type="button" @click="search = !search" class="hidden rounded-full p-2 text-ink hover:bg-gold-50 lg:inline-flex xl:hidden" aria-label="Search">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            </button>
            @auth
                <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('dashboard') }}" class="rounded-full p-2 text-ink hover:bg-gold-50" aria-label="My account">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="rounded-full p-2 text-ink hover:bg-gold-50" aria-label="Sign in">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                </a>
            @endauth
            <a href="{{ route('cart.show') }}" class="relative rounded-full p-2 text-ink hover:bg-gold-50" aria-label="Cart, {{ $cartCount }} {{ Str::plural('item', $cartCount) }}">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8h12l-1 12H7L6 8Z"/><path stroke-linecap="round" d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                @if ($cartCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-umber-700 px-1 text-[0.65rem] font-bold text-white">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <div x-show="search" x-cloak x-transition class="border-t border-gold-100 bg-white py-3 xl:hidden">
        <form action="{{ route('shop') }}" method="get" role="search" class="container-store flex gap-2">
            <label for="mobile-search" class="sr-only">Search products</label>
            <input id="mobile-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search serums, soaps, ingredients…" class="input rounded-full">
            <button class="btn-primary btn-sm">Search</button>
        </form>
    </div>

    {{-- Mobile menu --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
        <div x-show="menu" x-transition.opacity class="absolute inset-0 bg-ink/40" @click="menu = false"></div>
        <nav x-show="menu" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             class="relative flex h-full w-80 max-w-[85%] flex-col overflow-y-auto bg-white p-6 shadow-xl" @keydown.escape.window="menu = false">
            <div class="mb-6 flex items-center justify-between">
                <x-application-logo />
                <button type="button" @click="menu = false" class="rounded-full p-2 hover:bg-gold-50" aria-label="Close menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <ul class="space-y-1 text-base">
                <li><a href="{{ route('shop') }}" class="block rounded-lg px-3 py-3 font-semibold hover:bg-gold-50">Shop all</a></li>
                @foreach ($navCategories as $navCategory)
                    <li><a href="{{ route('category.show', $navCategory) }}" class="block rounded-lg px-3 py-3 hover:bg-gold-50">{{ $navCategory->name }}</a></li>
                @endforeach
            </ul>
            <ul class="mt-4 space-y-1 border-t border-gold-100 pt-4 text-base">
                <li><a href="{{ url('/about-us') }}" class="block rounded-lg px-3 py-3 hover:bg-gold-50">About us</a></li>
                <li><a href="{{ route('contact') }}" class="block rounded-lg px-3 py-3 hover:bg-gold-50">Contact</a></li>
                <li><a href="{{ route('orders.lookup') }}" class="block rounded-lg px-3 py-3 hover:bg-gold-50">Track your order</a></li>
                @auth
                    <li><a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-3 hover:bg-gold-50">My account</a></li>
                @else
                    <li><a href="{{ route('login') }}" class="block rounded-lg px-3 py-3 hover:bg-gold-50">Sign in</a></li>
                @endauth
            </ul>
        </nav>
    </div>
</header>

<main id="main" class="flex-1">
    @if (session('status'))
        <div class="container-store pt-6" role="status">
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        </div>
    @endif
    @if (session('cart_added'))
        <div class="container-store pt-6" role="status">
            <div class="flex flex-col gap-3 rounded-lg border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-ink sm:flex-row sm:items-center sm:justify-between">
                <span><strong>{{ session('cart_added') }}</strong> was added to your cart.</span>
                <span class="flex gap-2">
                    <a href="{{ route('cart.show') }}" class="btn-outline btn-sm">View cart</a>
                    <a href="{{ route('checkout.show') }}" class="btn-primary btn-sm">Checkout</a>
                </span>
            </div>
        </div>
    @endif

    {{ $slot }}
</main>

<footer class="mt-16 bg-ink text-stone-300">
    <div class="container-store grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="font-serif text-xl tracking-[0.15em] text-white">INFINITE <span class="text-gold-400">NATURALS</span></p>
            <p class="mt-4 text-sm leading-relaxed">{{ setting('store.footer_blurb') }}</p>
        </div>
        <div>
            <h2 class="font-sans text-xs font-semibold uppercase tracking-[0.2em] text-gold-400">Shop</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('shop') }}" class="hover:text-white">All products</a></li>
                @foreach ($navCategories as $navCategory)
                    <li><a href="{{ route('category.show', $navCategory) }}" class="hover:text-white">{{ $navCategory->name }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="font-sans text-xs font-semibold uppercase tracking-[0.2em] text-gold-400">Help</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('orders.lookup') }}" class="hover:text-white">Track your order</a></li>
                @foreach ($footerPages as $footerPage)
                    <li><a href="{{ $footerPage->url() }}" class="hover:text-white">{{ $footerPage->title }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="font-sans text-xs font-semibold uppercase tracking-[0.2em] text-gold-400">Contact</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @if (setting('store.phone'))<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store.phone')) }}" class="hover:text-white">{{ setting('store.phone') }}</a></li>@endif
                @if (setting('store.email'))<li><a href="mailto:{{ setting('store.email') }}" class="hover:text-white">{{ setting('store.email') }}</a></li>@endif
                @if (setting('store.address'))<li>{{ setting('store.address') }}</li>@endif
            </ul>
            <div class="mt-4 flex gap-4 text-sm">
                @if (setting('store.social_facebook'))<a href="{{ setting('store.social_facebook') }}" class="hover:text-white" rel="noopener">Facebook</a>@endif
                @if (setting('store.social_instagram'))<a href="{{ setting('store.social_instagram') }}" class="hover:text-white" rel="noopener">Instagram</a>@endif
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-store flex flex-col gap-2 py-6 text-xs text-stone-400 sm:flex-row sm:justify-between">
            <p>&copy; {{ now()->year }} {{ $storeName }}. All rights reserved.</p>
            @if (\App\Services\Payments\StripeCheckout::isAvailable())<p>Secure checkout powered by Stripe.</p>@endif
        </div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
