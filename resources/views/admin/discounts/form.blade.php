@php($type = old('type', $discount->type?->value ?? 'percent'))
<x-admin-layout :title="$discount->exists ? 'Edit '.$discount->code : 'New discount code'">
    <a href="{{ route('admin.discounts.index') }}" class="text-sm underline">&larr; Discount codes</a>
    <form method="post" action="{{ $discount->exists ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}" class="mt-4 max-w-2xl" x-data="{ type: '{{ $type }}' }">
        @csrf @if($discount->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="code" label="Code" :value="$discount->code" required help="Letters, numbers, - and _. Not case-sensitive for customers." />
                <x-admin.field name="description" label="Internal description" :value="$discount->description" />
                <div class="sm:col-span-2">
                    <label class="label" for="type">Type</label>
                    <select id="type" name="type" class="input" x-model="type">
                        @foreach (\App\Enums\DiscountType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                    </select>
                </div>
                <div x-show="type === 'percent'"><x-admin.field name="percent" label="Percent off" type="number" min="1" max="100" :value="$discount->type?->value === 'percent' ? $discount->value : null" /></div>
                <div x-show="type === 'fixed'" x-cloak><x-admin.field name="amount" label="Amount off ($)" :value="$discount->type?->value === 'fixed' ? \App\Support\Money::toDecimal($discount->value) : null" /></div>
                <x-admin.field name="min_subtotal" label="Minimum merchandise subtotal ($)" :value="\App\Support\Money::toDecimal($discount->min_subtotal)" />
                <x-admin.field name="starts_at" label="Starts" type="datetime-local" :value="$discount->starts_at?->format('Y-m-d\TH:i')" />
                <x-admin.field name="ends_at" label="Ends" type="datetime-local" :value="$discount->ends_at?->format('Y-m-d\TH:i')" />
                <x-admin.field name="usage_limit" label="Total uses allowed" type="number" min="1" :value="$discount->usage_limit" help="Blank = unlimited" />
                <x-admin.field name="usage_limit_per_customer" label="Uses per customer (by email)" type="number" min="1" :value="$discount->usage_limit_per_customer" help="Blank = unlimited" />
                <x-admin.checkbox name="is_active" label="Active" :checked="$discount->is_active" class="sm:col-span-2" />
            </div>
        </x-admin.card>
        <button class="btn-primary btn-sm mt-4">Save</button>
    </form>
    @if ($discount->exists)
        <form method="post" action="{{ route('admin.discounts.destroy', $discount) }}" class="mt-6" onsubmit="return confirm('Delete this code?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Delete code</button></form>
    @endif
</x-admin-layout>
