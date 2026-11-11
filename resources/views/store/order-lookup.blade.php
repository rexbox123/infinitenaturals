<x-store-layout title="Track your order" noindex>
    <div class="container-store flex justify-center py-14">
        <div class="w-full max-w-md">
            <h1 class="text-center font-serif text-3xl">Track your order</h1>
            <p class="mt-2 text-center text-sm text-stone-600">Enter the order number from your confirmation email and the email address you used at checkout.</p>
            <form method="post" action="{{ route('orders.lookup.submit') }}" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="number" class="label">Order number</label>
                    <input id="number" name="number" value="{{ old('number') }}" placeholder="e.g. IN-100123" required class="input uppercase">
                    @error('number')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary w-full">Find my order</button>
            </form>
            @auth<p class="mt-6 text-center text-sm"><a href="{{ route('dashboard') }}" class="link">See all orders in your account</a></p>@endauth
        </div>
    </div>
</x-store-layout>
