<x-admin-layout :title="'Stock · '.$variant->displayName()">
    <a href="{{ route('admin.inventory.index') }}" class="text-sm underline">&larr; Inventory</a>
    <div class="mt-4 grid gap-6 lg:grid-cols-[360px_1fr]">
        <x-admin.card title="Adjust stock">
            <p class="text-3xl font-semibold">{{ $variant->stock_quantity }} <span class="text-sm font-normal text-stone-500">on hand</span></p>
            <form method="post" action="{{ route('admin.inventory.adjust', $variant) }}" class="mt-4 space-y-3">
                @csrf
                <div><label class="label" for="mode">Action</label>
                    <select id="mode" name="mode" class="input"><option value="add">Add units</option><option value="remove">Remove units</option><option value="set">Set exact count (stocktake)</option></select></div>
                <x-admin.field name="quantity" label="Quantity" type="number" min="0" required />
                <div><label class="label" for="reason">Reason</label>
                    <select id="reason" name="reason" class="input">@foreach ($reasons as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <x-admin.field name="note" label="Note (optional)" />
                <button class="btn-primary btn-sm w-full">Apply</button>
            </form>
            <p class="mt-4 text-xs text-stone-500">Changes are applied with a row lock, so they never conflict with orders being placed at the same moment.</p>
            <a href="{{ route('admin.products.edit', $variant->product) }}" class="mt-4 inline-block text-sm text-umber-700 underline">Edit product</a>
        </x-admin.card>
        <x-admin.card title="History">
            @include('admin.inventory._movements', ['movements' => $movements])
        </x-admin.card>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-admin-layout>
