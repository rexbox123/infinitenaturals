<x-admin-layout :title="$method->exists ? 'Edit shipping method' : 'New shipping method'">
    <a href="{{ route('admin.shipping-methods.index') }}" class="text-sm underline">&larr; Shipping</a>
    <form method="post" action="{{ $method->exists ? route('admin.shipping-methods.update', $method) : route('admin.shipping-methods.store') }}" class="mt-4 max-w-xl">
        @csrf @if($method->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4">
                <x-admin.field name="name" label="Name shown to customers" :value="$method->name" required />
                <x-admin.field name="description" label="Delivery estimate / description" :value="$method->description" />
                <x-admin.field name="rate" label="Rate ($)" :value="\App\Support\Money::toDecimal($method->rate ?? 0)" required />
                <x-admin.field name="free_over" label="Free when merchandise total is at least ($)" :value="\App\Support\Money::toDecimal($method->free_over)" help="Leave blank if this method is never free." />
                <x-admin.field name="sort_order" label="Order" type="number" :value="$method->sort_order ?? 0" />
                <x-admin.checkbox name="is_active" label="Active (offered at checkout)" :checked="$method->is_active" />
            </div>
        </x-admin.card>
        <button class="btn-primary btn-sm mt-4">Save</button>
    </form>
    @if ($method->exists)
        <form method="post" action="{{ route('admin.shipping-methods.destroy', $method) }}" class="mt-6" onsubmit="return confirm('Delete this shipping method?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Delete</button></form>
    @endif
</x-admin-layout>
