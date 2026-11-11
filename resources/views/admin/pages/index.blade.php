<x-admin-layout title="Pages">
    <div class="mb-4 flex justify-end"><a href="{{ route('admin.pages.create') }}" class="btn-primary btn-sm">New page</a></div>
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">URL</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Updated</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @foreach ($pages as $page)
            <tr>
                <td class="px-4 py-3 font-semibold">{{ $page->title }}@if($page->is_system) <x-admin.badge>Built-in</x-admin.badge>@endif</td>
                <td class="px-4 py-3"><a href="{{ $page->url() }}" target="_blank" class="text-stone-600 underline">/{{ $page->slug }}</a></td>
                <td class="px-4 py-3"><x-admin.badge :color="$page->is_published ? 'green' : 'amber'">{{ $page->is_published ? 'Published' : 'Draft' }}</x-admin.badge></td>
                <td class="px-4 py-3 text-stone-600">{{ $page->updated_at->format('M j, Y') }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.pages.edit', $page) }}" class="text-umber-700 underline">Edit</a></td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin-layout>
