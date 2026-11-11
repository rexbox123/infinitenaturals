<x-admin-layout title="Banners & promotions">
    @foreach (\App\Models\Banner::PLACEMENTS as $placement => $label)
        <x-admin.card :title="$label" class="mb-6">
            <x-slot:actions><a href="{{ route('admin.banners.create', ['placement' => $placement]) }}" class="btn-outline btn-sm">Add</a></x-slot:actions>
            @forelse ($banners[$placement] ?? [] as $banner)
                <div class="flex items-center justify-between gap-4 border-b border-stone-100 py-3 last:border-0">
                    <div class="flex items-center gap-3">
                        @if ($banner->imageUrl())<img src="{{ $banner->imageUrl() }}" alt="" class="h-12 w-20 rounded object-cover">@endif
                        <div>
                            <p class="font-semibold">{{ $banner->title }}</p>
                            <p class="text-xs text-stone-500">{{ $banner->subtitle ? Str::limit($banner->subtitle, 80) : '' }} @if($banner->starts_at || $banner->ends_at)· {{ $banner->starts_at?->format('M j') ?? '…' }} – {{ $banner->ends_at?->format('M j') ?? '…' }}@endif</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-admin.badge :color="$banner->is_active ? 'green' : 'gray'">{{ $banner->is_active ? 'Active' : 'Hidden' }}</x-admin.badge>
                        <a href="{{ route('admin.banners.edit', $banner) }}" class="text-umber-700 underline">Edit</a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-600">None.</p>
            @endforelse
        </x-admin.card>
    @endforeach
</x-admin-layout>
