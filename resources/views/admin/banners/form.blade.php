<x-admin-layout :title="$banner->exists ? 'Edit banner' : 'New banner'">
    <a href="{{ route('admin.banners.index') }}" class="text-sm underline">&larr; Banners</a>
    <form method="post" enctype="multipart/form-data" action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" class="mt-4 max-w-2xl">
        @csrf @if($banner->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4">
                <div><label for="placement" class="label">Where</label>
                    <select id="placement" name="placement" class="input">@foreach (\App\Models\Banner::PLACEMENTS as $k => $l)<option value="{{ $k }}" @selected(old('placement', $banner->placement) === $k)>{{ $l }}</option>@endforeach</select></div>
                <x-admin.field name="eyebrow" label="Small text above the title (optional)" :value="$banner->eyebrow" />
                <x-admin.field name="title" label="Title / message" :value="$banner->title" required />
                <x-admin.field name="subtitle" label="Subtitle" type="textarea" rows="2" :value="$banner->subtitle" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="button_label" label="Button text" :value="$banner->button_label" />
                    <x-admin.field name="button_url" label="Button link" :value="$banner->button_url" help="e.g. /shop or /product-category/body-soaps" />
                    <x-admin.field name="starts_at" label="Show from" type="datetime-local" :value="$banner->starts_at?->format('Y-m-d\TH:i')" />
                    <x-admin.field name="ends_at" label="Show until" type="datetime-local" :value="$banner->ends_at?->format('Y-m-d\TH:i')" />
                    <x-admin.field name="sort_order" label="Order" type="number" :value="$banner->sort_order ?? 0" />
                </div>
                <div>
                    <label for="image" class="label">Image (hero/promo)</label>
                    @if ($banner->imageUrl())<img src="{{ $banner->imageUrl() }}" alt="" class="mb-2 h-32 rounded object-cover"><x-admin.checkbox name="remove_image" label="Remove image" class="mb-2" />@endif
                    <input id="image" type="file" name="image" accept="image/*" class="text-sm">
                    <p class="mt-1 text-xs text-stone-500">Hero: landscape, at least 1920×900 px. Without an image a branded gold background is used.</p>
                </div>
                <x-admin.checkbox name="is_active" label="Active" :checked="$banner->is_active" />
            </div>
        </x-admin.card>
        <button class="btn-primary btn-sm mt-4">Save</button>
    </form>
    @if ($banner->exists)
        <form method="post" action="{{ route('admin.banners.destroy', $banner) }}" class="mt-6" onsubmit="return confirm('Delete this banner?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Delete banner</button></form>
    @endif
</x-admin-layout>
