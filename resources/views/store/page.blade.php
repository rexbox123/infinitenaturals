<x-store-layout :title="$page->title" :description="$page->meta_description">
    <div class="border-b border-gold-200 bg-cream">
        <div class="container-store py-12 text-center">
            <h1 class="font-serif text-4xl">{{ $page->title }}</h1>
            <div class="divider-gold"></div>
        </div>
    </div>
    <article class="container-store prose prose-store max-w-3xl py-12 prose-h3:font-serif prose-h3:text-xl">
        @unless ($page->is_published)
            <p class="not-prose mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Preview: this page is not published.</p>
        @endunless
        {!! $page->body !!}
    </article>
</x-store-layout>
