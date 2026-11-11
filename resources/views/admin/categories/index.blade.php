<x-admin-layout title="Categories">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.categories.create') }}" class="btn-primary btn-sm">Add category</a></div>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">URL</th><th class="px-4 py-3">Products</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @foreach ($categories as $category)
            <tr>
                <td class="px-4 py-3 font-semibold">{{ $category->name }}</td>
                <td class="px-4 py-3 text-stone-600">/product-category/{{ $category->slug }}</td>
                <td class="px-4 py-3">{{ $category->products_count }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$category->is_active ? 'green' : 'gray'">{{ $category->is_active ? 'Visible' : 'Hidden' }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.categories.edit', $category) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin-layout>
