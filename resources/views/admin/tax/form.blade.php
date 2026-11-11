<x-admin-layout :title="$rate->exists ? 'Edit tax rate' : 'New tax rate'">
    <a href="{{ route('admin.tax-rates.index') }}" class="text-sm underline">&larr; Sales tax</a>
    <form method="post" action="{{ $rate->exists ? route('admin.tax-rates.update', $rate) : route('admin.tax-rates.store') }}" class="mt-4 max-w-xl">
        @csrf @if($rate->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4">
                <div><label for="state" class="label">State</label>
                    <select id="state" name="state" class="input">@foreach (config('store.shipping_states') as $code => $name)<option value="{{ $code }}" @selected(old('state', $rate->state) === $code)>{{ $name }}</option>@endforeach</select></div>
                <x-admin.field name="rate" label="Rate (%)" type="number" step="0.0001" min="0" max="25" :value="$rate->rate" required help="Combined rate, e.g. 7 for 7%." />
                <x-admin.checkbox name="applies_to_shipping" label="Shipping charges are taxable in this state" :checked="$rate->applies_to_shipping" />
                <x-admin.checkbox name="is_active" label="Active" :checked="$rate->is_active" />
            </div>
        </x-admin.card>
        <button class="btn-primary btn-sm mt-4">Save</button>
    </form>
    @if ($rate->exists)
        <form method="post" action="{{ route('admin.tax-rates.destroy', $rate) }}" class="mt-6" onsubmit="return confirm('Delete this rate?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Delete</button></form>
    @endif
</x-admin-layout>
