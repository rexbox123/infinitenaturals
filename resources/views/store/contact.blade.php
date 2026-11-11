<x-store-layout title="Contact Us" :description="$page?->meta_description">
    <div class="border-b border-gold-200 bg-cream">
        <div class="container-store py-12 text-center">
            <h1 class="font-serif text-4xl">{{ $page?->title ?? 'Contact Us' }}</h1>
            <div class="divider-gold"></div>
        </div>
    </div>
    <div class="container-store grid gap-12 py-12 lg:grid-cols-2">
        <div>
            @if ($page?->body)<div class="prose prose-store">{!! $page->body !!}</div>@endif
            <dl class="mt-8 space-y-4">
                @if (setting('store.phone'))<div><dt class="eyebrow">Phone</dt><dd class="mt-1 text-lg"><a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store.phone')) }}" class="link">{{ setting('store.phone') }}</a></dd></div>@endif
                @if (setting('store.email'))<div><dt class="eyebrow">Email</dt><dd class="mt-1 text-lg"><a href="mailto:{{ setting('store.email') }}" class="link">{{ setting('store.email') }}</a></dd></div>@endif
                @if (setting('store.address'))<div><dt class="eyebrow">Location</dt><dd class="mt-1 text-lg">{{ setting('store.address') }}</dd></div>@endif
            </dl>
        </div>
        <form method="post" action="{{ route('contact.store') }}" class="space-y-4 rounded-2xl border border-gold-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <h2 class="font-serif text-2xl">Send us a message</h2>
            {{-- Honeypot field, hidden from people --}}
            <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="label">Phone <span class="font-normal text-stone-500">(optional)</span></label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="input">
                </div>
                <div>
                    <label for="subject" class="label">Subject <span class="font-normal text-stone-500">(optional)</span></label>
                    <input id="subject" name="subject" value="{{ old('subject') }}" class="input">
                </div>
            </div>
            <div>
                <label for="message" class="label">Message</label>
                <textarea id="message" name="message" rows="6" required class="input">{{ old('message') }}</textarea>
                @error('message')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary w-full sm:w-auto">Send message</button>
        </form>
    </div>
</x-store-layout>
