<x-admin-layout title="Homepage">
    <p class="mb-4 max-w-3xl text-sm text-stone-600">Edit the text sections of the homepage. Hero slides, the announcement bar and promotion blocks are managed under <a href="{{ route('admin.banners.index') }}" class="text-umber-700 underline">Banners</a>; featured products are chosen with “Feature on homepage” on each product.</p>
    <form method="post" action="{{ route('admin.homepage.update') }}" class="max-w-3xl">
        @csrf @method('put')
        <x-admin.card>
            <div class="grid gap-4">
                @foreach ($fields as $key => $label)
                    <x-admin.field :name="str_replace('.', '_', $key)" :label="$label" :type="str_ends_with($key, '_body') ? 'textarea' : 'text'" rows="5" :value="$settings->all()[$key] ?? \App\Services\Settings::DEFAULTS[$key] ?? ''" />
                @endforeach
            </div>
        </x-admin.card>
        <div class="mt-4 flex gap-2"><button class="btn-primary btn-sm">Save homepage</button><a href="{{ route('home') }}" target="_blank" class="btn-outline btn-sm">View ↗</a></div>
    </form>
</x-admin-layout>
