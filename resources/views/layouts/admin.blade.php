@php
    $user = auth()->user();
    $nav = [
        ['Dashboard', 'admin.dashboard', 'admin.access', 'M3 12l9-8 9 8M5 10v10h14V10'],
        ['Orders', 'admin.orders.index', 'orders.view', 'M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2'],
        ['Products', 'admin.products.index', 'catalog.manage', 'M4 7l8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7'],
        ['Categories', 'admin.categories.index', 'catalog.manage', 'M4 5h7v7H4zM13 5h7v7h-7zM4 14h7v6H4zM13 14h7v6h-7z'],
        ['Inventory', 'admin.inventory.index', 'inventory.manage', 'M3 7h18M5 7v13h14V7M9 11h6'],
        ['Customers', 'admin.customers.index', 'customers.manage', 'M16 11a4 4 0 1 0-8 0M3 21c1.5-4 5-6 9-6s7.5 2 9 6'],
        ['Discounts', 'admin.discounts.index', 'promotions.manage', 'M7 7h.01M3 3h8l10 10-8 8L3 11V3Z'],
        ['Banners', 'admin.banners.index', 'promotions.manage', 'M4 5h16v10H4zM8 19h8'],
        ['Homepage', 'admin.homepage.edit', 'content.manage', 'M4 4h16v16H4zM4 9h16'],
        ['Pages', 'admin.pages.index', 'content.manage', 'M7 3h7l5 5v13H7zM14 3v5h5'],
        ['Messages', 'admin.messages.index', 'messages.view', 'M4 6h16v12H4zM4 6l8 7 8-7'],
        ['Shipping', 'admin.shipping-methods.index', 'settings.manage', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-.01M17 19a2 2 0 1 0 0-.01'],
        ['Sales tax', 'admin.tax-rates.index', 'settings.manage', 'M6 18 18 6M8 8h.01M16 16h.01'],
        ['Settings', 'admin.settings.edit', 'settings.manage', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19 12l2-1-2-4-2 1-2-2V4h-4v2L9 8 7 7 5 11l2 1v2l-2 1 2 4 2-1 2 2v2h4v-2l2-2 2 1 2-4-2-1Z'],
        ['Staff', 'admin.staff.index', 'users.manage', 'M9 11a4 4 0 1 0 0-.01M2 21c1-4 4-6 7-6s6 2 7 6M17 11h5M19.5 8.5v5'],
    ];
    $unread = $user->can('messages.view') ? \App\Models\ContactMessage::whereNull('read_at')->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}Admin · {{ setting('store.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=open-sans:400,600,700|prata:400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="bg-stone-100" x-data="{ nav: false }">
<div class="flex min-h-screen">
    <div x-show="nav" x-cloak class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="nav = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-ink text-stone-300 transition lg:static lg:translate-x-0" :class="nav ? 'translate-x-0' : ''" aria-label="Admin navigation">
        <div class="flex h-16 items-center gap-2 border-b border-white/10 px-5">
            <a href="{{ route('admin.dashboard') }}" class="font-serif text-lg tracking-[0.15em] text-white">INFINITE <span class="text-gold-400">ADMIN</span></a>
        </div>
        <nav class="space-y-0.5 p-3 text-sm">
            @foreach ($nav as [$label, $route, $permission, $icon])
                @can($permission)
                    @php($active = request()->routeIs(Str::beforeLast($route, '.').'*'))
                    <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ $active ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white' }}" @if($active) aria-current="page" @endif>
                        <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-gold-400' : 'text-stone-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        <span class="flex-1">{{ $label }}</span>
                        @if ($label === 'Messages' && $unread)<span class="rounded-full bg-gold-400 px-2 text-xs font-bold text-ink">{{ $unread }}</span>@endif
                    </a>
                @endcan
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-3 text-sm">
            <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5 hover:text-white">View store ↗</a>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-stone-200 bg-white px-4 sm:px-6">
            <button type="button" class="rounded-lg p-2 lg:hidden" @click="nav = true" aria-label="Open navigation">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <h1 class="truncate font-serif text-xl">{{ $title }}</h1>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden text-stone-600 sm:inline">{{ $user->name }} · {{ $user->roleLabel() }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Sign out</button></form>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
