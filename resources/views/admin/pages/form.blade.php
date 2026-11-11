<x-admin-layout :title="$page->exists ? 'Edit page' : 'New page'">
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.pages.index') }}" class="text-sm underline">&larr; Pages</a>
        @if ($page->exists)<a href="{{ $page->url() }}" target="_blank" class="btn-outline btn-sm">View ↗</a>@endif
    </div>
    <form method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="mt-4 grid gap-6 xl:grid-cols-[1fr_300px]">
        @csrf @if($page->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4">
                <x-admin.field name="title" label="Title" :value="$page->title" required />
                @if ($page->is_system)
                    <p class="text-sm text-stone-600">URL: <strong>/{{ $page->slug }}</strong> (built-in pages keep their address)</p>
                @else
                    <x-admin.field name="slug" label="URL slug" :value="$page->slug" help="Leave blank to generate from the title." />
                @endif
                @if ($page->slug === 'contact-us')
                    <p class="rounded bg-gold-50 p-3 text-sm">This text appears above the contact details; the contact form and phone/email/address (from Settings) are added automatically.</p>
                @endif
                <x-admin.rich-text name="body" label="Content" :value="$page->body" />
                <x-admin.field name="meta_description" label="Meta description (search engines)" type="textarea" rows="2" :value="$page->meta_description" />
            </div>
        </x-admin.card>
        <div class="space-y-4">
            <x-admin.card>
                <div class="space-y-4">
                    <x-admin.checkbox name="is_published" label="Published" :checked="$page->is_published" />
                    <x-admin.checkbox name="show_in_footer" label="Link in footer" :checked="$page->show_in_footer" />
                    <x-admin.field name="sort_order" label="Footer order" type="number" :value="$page->sort_order ?? 0" />
                </div>
            </x-admin.card>
            <button class="btn-primary w-full">Save page</button>
        </div>
    </form>
    @if ($page->exists && ! $page->is_system)
        <form method="post" action="{{ route('admin.pages.destroy', $page) }}" class="mt-6" onsubmit="return confirm('Delete this page?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Delete page</button></form>
    @endif
</x-admin-layout>
