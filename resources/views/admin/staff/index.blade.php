<x-admin-layout title="Staff accounts">
    <div class="mb-4 flex justify-between gap-3">
        <div class="text-sm text-stone-600">
            <p><strong>Administrator</strong>: everything, including settings, payments and staff. <strong>Store manager</strong>: products, orders, refunds, customers, promotions and content. <strong>Fulfillment staff</strong>: orders, shipping updates and inventory.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="btn-primary btn-sm shrink-0">Add staff</a>
    </div>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @foreach ($staff as $member)
            <tr>
                <td class="px-4 py-3 font-semibold">{{ $member->name }}@if($member->is(auth()->user())) <span class="text-xs text-stone-500">(you)</span>@endif</td>
                <td class="px-4 py-3">{{ $member->email }}</td>
                <td class="px-4 py-3">{{ $member->roleLabel() }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$member->is_active ? 'green' : 'gray'">{{ $member->is_active ? 'Active' : 'Disabled' }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.staff.edit', $member) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin-layout>
