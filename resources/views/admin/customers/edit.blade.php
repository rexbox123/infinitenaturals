<x-admin-layout :title="'Edit '.$customer->name">
    <a href="{{ route('admin.customers.show', $customer) }}" class="text-sm underline">&larr; Back</a>
    <form method="post" action="{{ route('admin.customers.update', $customer) }}" class="mt-4 max-w-xl">
        @csrf @method('put')
        <x-admin.card>
            <div class="grid gap-4">
                <x-admin.field name="name" label="Name" :value="$customer->name" required />
                <x-admin.field name="email" label="Email" type="email" :value="$customer->email" required />
                <x-admin.field name="phone" label="Phone" :value="$customer->phone" />
                <x-admin.checkbox name="is_active" label="Account active" help="Disabled customers cannot sign in. Their orders are kept." :checked="$customer->is_active" />
            </div>
        </x-admin.card>
        <button class="btn-primary btn-sm mt-4">Save</button>
    </form>
</x-admin-layout>
