<x-store-layout title="My account" noindex>
    <div class="container-store py-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">My account</p>
                <h1 class="mt-2 font-serif text-4xl">Hello, {{ auth()->user()->name }}</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('profile.edit') }}" class="btn-outline btn-sm">Profile &amp; password</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-outline btn-sm">Sign out</button></form>
            </div>
        </div>
        @if (auth()->user()->isStaff())
            <p class="mt-6 rounded-lg border border-gold-300 bg-gold-50 px-4 py-3 text-sm">You have staff access. <a href="{{ route('admin.dashboard') }}" class="link">Open the admin dashboard</a>.</p>
        @endif

        <h2 class="mt-10 font-serif text-2xl">Your orders</h2>
        @if ($orders->isEmpty())
            <p class="mt-4 text-stone-600">You haven't placed any orders yet. <a href="{{ route('shop') }}" class="link">Start shopping</a></p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-gold-200">
                <table class="min-w-full divide-y divide-gold-100 text-sm">
                    <thead class="bg-cream text-left text-xs uppercase tracking-wider text-stone-600">
                        <tr><th class="px-4 py-3">Order</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3"><span class="sr-only">View</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gold-100">
                        @foreach ($orders as $order)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $order->number }}</td>
                                <td class="px-4 py-3">{{ $order->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3">{{ $order->statusHeadline() }}</td>
                                <td class="px-4 py-3 text-right">{{ money($order->total) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('account.orders.show', $order) }}" class="link">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-store-layout>
