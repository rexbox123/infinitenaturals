<x-admin-layout :title="$category->exists ? 'Edit category' : 'New category'">
    <a href="{{ route('admin.categories.index') }}" class="text-sm underline">&larr; Categories</a>
    <form method="post" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="mt-4 max-w-2xl">
        @csrf @if($category->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4">
                <x-admin.field name="name" label="Name" :value="$category->name" required />
                <x-admin.field name="slug" label="URL slug" :value="$category->slug" help="Leave blank to generate from the name." />
                <x-admin.field name="description" label="Description" type="textarea" :value="$category->description" />
                <x-admin.field name="sort_order" label="Sort order" type="number" :value="$category->sort_order ?? 0" />
                <div>
                    <label for="image" class="label">Image</label>
                    @if ($category->imageUrl())<img src="{{ $category->imageUrl() }}" alt="" class="mb-2 h-24 rounded object-cover"><x-admin.checkbox name="remove_image" label="Remove current image" class="mb-2" />@endif
                    <input id="image" type="file" name="image" accept="image/*" class="text-sm">
                </div>
                <x-admin.checkbox name="is_active" label="Visible in the store" :checked="$category->is_active" />
            </div>
        </x-admin.card>
        <div class="mt-4 flex gap-2"><button class="btn-primary btn-sm">Save</button></div>
    </form>
    @if ($category->exists)
        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" class="mt-6" onsubmit="return confirm('Delete this category?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Delete category</button></form>
    @endif
</x-admin-layout>
