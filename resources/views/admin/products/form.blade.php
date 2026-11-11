@php
    use App\Support\Money;
    $isNew = ! $product->exists;
    $rows = old('variants') ? array_values(old('variants')) : $product->variants->map(fn ($v) => [
        'id' => $v->id, 'name' => $v->name, 'sku' => $v->sku,
        'price' => Money::toDecimal($v->price), 'sale_price' => Money::toDecimal($v->sale_price),
        'sale_starts_at' => $v->sale_starts_at?->format('Y-m-d\TH:i'), 'sale_ends_at' => $v->sale_ends_at?->format('Y-m-d\TH:i'),
        'stock' => $v->stock_quantity ?? 0, 'stock_original' => $v->exists ? $v->stock_quantity : null,
        'track_inventory' => $v->track_inventory ?? true, 'is_active' => $v->is_active ?? true, 'weight_grams' => $v->weight_grams,
    ])->values()->all();
@endphp
<x-admin-layout :title="$isNew ? 'New product' : $product->name">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.products.index') }}" class="text-sm underline">&larr; All products</a>
        @unless ($isNew)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('product.show', $product) }}" target="_blank" class="btn-outline btn-sm">View in store ↗</a>
                @if ($product->status->value === 'archived')
                    <form method="post" action="{{ route('admin.products.restore', $product) }}">@csrf<button class="btn-outline btn-sm">Restore</button></form>
                @else
                    <form method="post" action="{{ route('admin.products.archive', $product) }}">@csrf<button class="btn-outline btn-sm">Archive</button></form>
                @endif
                <form method="post" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Permanently delete {{ addslashes($product->name) }}? This cannot be undone.')">@csrf @method('delete')<button class="btn btn-sm border border-red-300 bg-white text-red-700 hover:bg-red-50">Delete</button></form>
            </div>
        @endunless
    </div>

    <form method="post" action="{{ $isNew ? route('admin.products.store') : route('admin.products.update', $product) }}" class="grid gap-6 xl:grid-cols-[1fr_340px]">
        @csrf @unless($isNew) @method('put') @endunless
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Details">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="name" label="Name" :value="$product->name" required class="sm:col-span-2" />
                    <x-admin.field name="slug" label="URL slug" :value="$product->slug" help="/product/your-slug — leave blank to generate from the name." />
                    <div>
                        <label for="category_id" class="label">Category</label>
                        <select id="category_id" name="category_id" class="input"><option value="">— None —</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <x-admin.field name="short_description" label="Short description" type="textarea" rows="2" :value="$product->short_description" help="Shown under the price and on search results." class="sm:col-span-2" />
                </div>
            </x-admin.card>

            <x-admin.card title="Product information">
                <div class="space-y-5">
                    <x-admin.rich-text name="description" label="Description (incl. directions & skin type)" :value="$product->description" />
                    <x-admin.rich-text name="benefits" label="Benefits / feature ingredients" :value="$product->benefits" help="Only include claims you can substantiate." />
                    <x-admin.rich-text name="ingredients" label="Ingredients (INCI list)" :value="$product->ingredients" />
                </div>
            </x-admin.card>

            <x-admin.card title="Variants, prices & stock">
                <div x-data="{ rows: {{ Js::from($rows) }}, add() { this.rows.push({ id: null, name: '', sku: '', price: '', sale_price: '', sale_starts_at: '', sale_ends_at: '', stock: 0, stock_original: null, track_inventory: true, is_active: true, weight_grams: '' }) } }" class="space-y-4">
                    <p class="text-sm text-stone-600">Each variant is a purchasable option (e.g. a size). Products with one option use a single “Default” variant. Stock edits here are recorded in the inventory history; use <a class="underline" href="{{ route('admin.inventory.index') }}">Inventory</a> to restock with a reason.</p>
                    <template x-for="(row, i) in rows" :key="i">
                        <fieldset class="rounded-lg border border-stone-200 p-4">
                            <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-stone-500" x-text="'Variant ' + (i + 1)"></legend>
                            <input type="hidden" :name="`variants[${i}][id]`" :value="row.id">
                            <input type="hidden" :name="`variants[${i}][stock_original]`" :value="row.stock_original ?? ''">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label" :for="`v${i}name`">Option name</label><input class="input" :id="`v${i}name`" :name="`variants[${i}][name]`" x-model="row.name" placeholder="Default or e.g. 5.3 oz" required></div>
                                <div><label class="label" :for="`v${i}sku`">SKU</label><input class="input" :id="`v${i}sku`" :name="`variants[${i}][sku]`" x-model="row.sku"></div>
                                <div><label class="label" :for="`v${i}price`">Price ($)</label><input class="input" :id="`v${i}price`" :name="`variants[${i}][price]`" x-model="row.price" inputmode="decimal" required></div>
                                <div><label class="label" :for="`v${i}stock`">Stock on hand</label><input type="number" min="0" class="input" :id="`v${i}stock`" :name="`variants[${i}][stock]`" x-model="row.stock"></div>
                                <div><label class="label" :for="`v${i}sale`">Sale price ($)</label><input class="input" :id="`v${i}sale`" :name="`variants[${i}][sale_price]`" x-model="row.sale_price" inputmode="decimal" placeholder="Optional"></div>
                                <div><label class="label" :for="`v${i}ss`">Sale starts</label><input type="datetime-local" class="input" :id="`v${i}ss`" :name="`variants[${i}][sale_starts_at]`" x-model="row.sale_starts_at"></div>
                                <div><label class="label" :for="`v${i}se`">Sale ends</label><input type="datetime-local" class="input" :id="`v${i}se`" :name="`variants[${i}][sale_ends_at]`" x-model="row.sale_ends_at"></div>
                                <div><label class="label" :for="`v${i}w`">Weight (g)</label><input type="number" min="0" class="input" :id="`v${i}w`" :name="`variants[${i}][weight_grams]`" x-model="row.weight_grams"></div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-5 text-sm">
                                <label class="flex items-center gap-2"><input type="hidden" :name="`variants[${i}][track_inventory]`" value="0"><input type="checkbox" value="1" :name="`variants[${i}][track_inventory]`" x-model="row.track_inventory" class="rounded border-stone-300 text-umber-700"> Track stock</label>
                                <label class="flex items-center gap-2"><input type="hidden" :name="`variants[${i}][is_active]`" value="0"><input type="checkbox" value="1" :name="`variants[${i}][is_active]`" x-model="row.is_active" class="rounded border-stone-300 text-umber-700"> Available for sale</label>
                                <button type="button" class="ml-auto text-red-700 underline" x-show="rows.length > 1" @click="if (confirm('Remove this variant?')) rows.splice(i, 1)">Remove variant</button>
                            </div>
                        </fieldset>
                    </template>
                    <button type="button" class="btn-outline btn-sm" @click="add()">Add variant</button>
                </div>
            </x-admin.card>

            <x-admin.card title="Search engine listing">
                <div class="grid gap-4">
                    <x-admin.field name="meta_title" label="Page title" :value="$product->meta_title" help="Defaults to the product name." />
                    <x-admin.field name="meta_description" label="Meta description" type="textarea" rows="2" :value="$product->meta_description" help="Defaults to the short description." />
                </div>
            </x-admin.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-admin.card title="Visibility">
                <div class="space-y-4">
                    <div>
                        <label for="status" class="label">Status</label>
                        <select id="status" name="status" class="input">
                            @foreach (\App\Enums\ProductStatus::cases() as $s)<option value="{{ $s->value }}" @selected(old('status', $product->status?->value) === $s->value)>{{ $s->label() }}</option>@endforeach
                        </select>
                        <p class="mt-1 text-xs text-stone-500">Only Active products appear in the store. Archive to hide a product while keeping it for order history.</p>
                    </div>
                    <x-admin.checkbox name="is_featured" label="Feature on homepage" :checked="$product->is_featured" />
                    <x-admin.field name="sort_order" label="Sort order" type="number" :value="$product->sort_order ?? 0" help="Lower numbers show first." />
                </div>
            </x-admin.card>
            <button class="btn-primary w-full">{{ $isNew ? 'Create product' : 'Save product' }}</button>
        </div>
    </form>

    @unless ($isNew)
        <x-admin.card title="Photos" class="mt-6">
            @if ($product->images->isNotEmpty())
                <form method="post" action="{{ route('admin.products.images.update', $product) }}">
                    @csrf @method('patch')
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($product->images as $image)
                            <div class="rounded-lg border border-stone-200 p-2">
                                <img src="{{ $image->url() }}" alt="{{ $image->alt }}" class="aspect-square w-full rounded object-cover">
                                @if ($loop->first)<p class="mt-1 text-xs font-semibold text-umber-700">Main photo</p>@endif
                                <label class="mt-2 block text-xs text-stone-600">Alt text<input name="images[{{ $image->id }}][alt]" value="{{ $image->alt }}" class="input mt-1 py-1 text-xs"></label>
                                <label class="mt-2 block text-xs text-stone-600">Order<input type="number" min="0" name="images[{{ $image->id }}][sort_order]" value="{{ $image->sort_order }}" class="input mt-1 py-1 text-xs"></label>
                                <button form="delete-image-{{ $image->id }}" class="mt-2 text-xs text-red-700 underline">Delete</button>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn-outline btn-sm mt-4">Save photo order &amp; alt text</button>
                </form>
                @foreach ($product->images as $image)
                    <form id="delete-image-{{ $image->id }}" method="post" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Delete this photo?')">@csrf @method('delete')</form>
                @endforeach
            @else
                <p class="text-sm text-stone-600">No photos yet — the store shows a branded placeholder.</p>
            @endif
            <form method="post" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-3 border-t border-stone-100 pt-4 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="images" class="label">Upload photos</label>
                    <input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required class="block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-gold-100 file:px-4 file:py-2 file:text-sm file:font-semibold">
                    <p class="mt-1 text-xs text-stone-500">JPG, PNG or WebP, up to 8 MB each. Square images around 1200×1200 px look best.</p>
                </div>
                <button class="btn-primary btn-sm">Upload</button>
            </form>
        </x-admin.card>
    @endunless
</x-admin-layout>
